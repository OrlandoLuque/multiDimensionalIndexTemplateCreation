mod vertex;
mod intersector;
mod polygon;
mod matrix;
mod templates;
mod comparison_test;
mod task;
mod redis_store;

use std::sync::Arc;
use std::sync::atomic::{AtomicUsize, Ordering};
use std::time::Instant;
use rayon::prelude::*;

use polygon::{create_drop, create_box, create_circle, scaled_copy, rotated_copy};
use templates::{TemplateStore, angle_to_radians, get_angles};

/// Author: Orlando Jose Luque Moraira

fn main() {
    let args: Vec<String> = std::env::args().collect();

    if args.iter().any(|a| a == "--compare") {
        comparison_test::run_comparison();
        return;
    }

    let use_redis = args.iter().any(|a| a == "--redis");
    let redis_host = args.iter().position(|a| a == "--redis-host")
        .and_then(|i| args.get(i + 1)).map(|s| s.as_str()).unwrap_or("127.0.0.1");
    let redis_port: u16 = args.iter().position(|a| a == "--redis-port")
        .and_then(|i| args.get(i + 1)).and_then(|s| s.parse().ok()).unwrap_or(6379);

    println!("=============================================================================");
    println!(" multiDimensionalIndexTemplateCreation (Rust)");
    println!(" Polygons vs grids intersection calculator");
    println!(" Author: Orlando Jose Luque Moraira");
    println!("=============================================================================\n");

    // Configuration
    let polygons = vec![
        ("drop", create_drop(0.2, 0.8)),
        ("box", create_box(1.0)),
        ("circle", create_circle(1.0)),
    ];
    let scales: Vec<f64> = vec![128.0];
    let grid_sizes: Vec<i64> = vec![16];
    let angle_step = 0.5;
    let angles = get_angles(angle_step);
    let max_per_task: u64 = 500_000;

    let subtasks = task::create_subtasks(
        &polygons, &scales, &grid_sizes, angles.len(), max_per_task,
    );
    let total_tasks = subtasks.len();

    println!("Config: {} polygons, {} scales, {} grids, {} angles (step {}deg)",
        polygons.len(), scales.len(), grid_sizes.len(), angles.len(), angle_step);
    task::print_summary(&subtasks);
    println!("Threads: {} | Mode: {}\n",
        rayon::current_num_threads(),
        if use_redis { "Redis (multi-process)" } else { "In-memory (single process)" });

    if use_redis {
        run_with_redis(&subtasks, &angles, redis_host, redis_port, total_tasks);
    } else {
        run_in_memory(&subtasks, &angles, total_tasks);
    }
}

fn run_in_memory(subtasks: &[task::SubTask], angles: &[f64], total_tasks: usize) {
    let store = Arc::new(TemplateStore::new());
    let completed = Arc::new(AtomicUsize::new(0));
    let global_start = Instant::now();

    subtasks.par_iter().for_each(|st| {
        process_subtask(st, angles, Some(&store), None, &completed, total_tasks);
    });

    println!("\n=== COMPLETE ===");
    println!("Total time: {:.2}s", global_start.elapsed().as_secs_f64());
    println!("Unique templates: {}", store.template_count());
    println!("Total combinations: {}", store.generation_count());
}

fn run_with_redis(subtasks: &[task::SubTask], angles: &[f64],
                  host: &str, port: u16, total_tasks: usize) {
    let redis = match redis_store::RedisStore::connect(host, port) {
        Ok(r) => Arc::new(r),
        Err(e) => { eprintln!("ERROR: {}", e); return; }
    };
    println!("Connected to Redis at {}:{}", host, port);

    let completed = Arc::new(AtomicUsize::new(0));
    let global_start = Instant::now();

    // Each thread tries to lock tasks via Redis
    subtasks.par_iter().for_each(|st| {
        let task_key = format!("T{}-lock", st.id + 1);

        if !redis.try_lock_task(&task_key) {
            return; // Another process has this task
        }

        process_subtask(st, angles, None, Some(&redis), &completed, total_tasks);
        redis.complete_task(&task_key);
    });

    let count = redis.get_template_count("templateCount");
    println!("\n=== COMPLETE ===");
    println!("Total time: {:.2}s", global_start.elapsed().as_secs_f64());
    println!("Templates in Redis: {}", count);
}

fn process_subtask(
    st: &task::SubTask,
    angles: &[f64],
    mem_store: Option<&Arc<TemplateStore>>,
    redis: Option<&Arc<redis_store::RedisStore>>,
    completed: &Arc<AtomicUsize>,
    total_tasks: usize,
) {
    let task_start = Instant::now();
    let scaled = scaled_copy(&st.poly, st.scale, st.scale);
    let gx = st.grid_size;
    let gy = st.grid_size;
    let mut new_count = 0u32;
    let mut iteration = 0u64;

    for angle_idx in st.angle_start..st.angle_end {
        let angle = angles[angle_idx];
        let rotated = rotated_copy(&scaled, angle_to_radians(angle));

        for x in 0..gx {
            for y in 0..gy {
                let mut moved = rotated.clone();
                moved.move_by(x as f64, y as f64);

                let gxr = [
                    (moved.x_min / gx as f64).floor() as i64,
                    (moved.x_max / gx as f64).ceil() as i64,
                ];
                let gyr = [
                    (moved.y_min / gy as f64).floor() as i64,
                    (moved.y_max / gy as f64).ceil() as i64,
                ];

                let tpl = templates::get_template_grid_fast(
                    gxr[0], gyr[0], gxr[1], gyr[1], gx, gy, &moved,
                );

                let gen_string = format!("{}-s{}-x{},y{}-a{}-dx{},dy{}",
                    st.poly_name, st.scale as i64, gx, gy, angle, x, y);

                if let Some(store) = mem_store {
                    let (_, _, is_new) = store.store_dedup(&tpl, &gen_string);
                    if is_new { new_count += 1; }
                }

                if let Some(redis) = redis {
                    let transforms = matrix::all_transforms(&tpl);
                    let hashes: Vec<Vec<u8>> = transforms.iter()
                        .map(|m| matrix::bin_code(m)).collect();
                    let (_, is_new) = redis.store_template(
                        &hashes, &gen_string,
                        "templateCount", "templateList", "generatedSet",
                    );
                    if is_new { new_count += 1; }

                    // Keep lock alive periodically
                    iteration += 1;
                    if iteration % 1000 == 0 {
                        redis.keep_lock(&format!("T{}-lock", st.id + 1));
                    }
                }
            }
        }
    }

    let done = completed.fetch_add(1, Ordering::Relaxed) + 1;
    let elapsed = task_start.elapsed();
    println!("  [{}/{}] {} s{} {}x{} a[{}..{}] | {} new | {:.2}s",
        done, total_tasks,
        st.poly_name, st.scale as i64,
        gx, gy, st.angle_start, st.angle_end,
        new_count, elapsed.as_secs_f64());
}
