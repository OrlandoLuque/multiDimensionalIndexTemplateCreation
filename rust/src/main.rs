mod vertex;
mod intersector;
mod polygon;
mod matrix;
mod templates;
mod comparison_test;
mod task;

use std::sync::Arc;
use std::sync::atomic::{AtomicUsize, Ordering};
use std::time::Instant;
use rayon::prelude::*;

use polygon::{create_drop, create_box, create_circle, scaled_copy, rotated_copy};
use templates::{TemplateStore, angle_to_radians, get_angles};

/// Author: Orlando Jose Luque Moraira

fn main() {
    if std::env::args().any(|a| a == "--compare") {
        comparison_test::run_comparison();
        return;
    }

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
    let max_combinations_per_task: u64 = 500_000;

    // Create balanced subtasks
    let subtasks = task::create_subtasks(
        &polygons, &scales, &grid_sizes, angles.len(), max_combinations_per_task,
    );

    let store = Arc::new(TemplateStore::new());
    let completed = Arc::new(AtomicUsize::new(0));
    let total_tasks = subtasks.len();

    println!("Config: {} polygons, {} scales, {} grids, {} angles (step {}deg)",
        polygons.len(), scales.len(), grid_sizes.len(), angles.len(), angle_step);
    task::print_summary(&subtasks);
    println!("Threads: {}\n", rayon::current_num_threads());

    let global_start = Instant::now();

    // Process all subtasks in parallel with rayon work-stealing
    subtasks.par_iter().for_each(|subtask| {
        let task_start = Instant::now();
        let scaled = scaled_copy(&subtask.poly, subtask.scale, subtask.scale);
        let grid_x = subtask.grid_size;
        let grid_y = subtask.grid_size;
        let mut task_new = 0u32;

        for angle_idx in subtask.angle_start..subtask.angle_end {
            let angle = angles[angle_idx];
            let rotated = rotated_copy(&scaled, angle_to_radians(angle));

            for x in 0..grid_x {
                for y in 0..grid_y {
                    let mut moved = rotated.clone();
                    moved.move_by(x as f64, y as f64);

                    let gxr = [
                        (moved.x_min / grid_x as f64).floor() as i64,
                        (moved.x_max / grid_x as f64).ceil() as i64,
                    ];
                    let gyr = [
                        (moved.y_min / grid_y as f64).floor() as i64,
                        (moved.y_max / grid_y as f64).ceil() as i64,
                    ];

                    let template_grid = templates::get_template_grid_fast(
                        gxr[0], gyr[0], gxr[1], gyr[1], grid_x, grid_y, &moved,
                    );

                    let gen_string = format!("{}-s{}-x{},y{}-a{}-dx{},dy{}",
                        subtask.poly_name, subtask.scale as i64,
                        grid_x, grid_y, angle, x, y);

                    let (_id, _op, is_new) = store.store_dedup(&template_grid, &gen_string);
                    if is_new { task_new += 1; }
                }
            }
        }

        let done = completed.fetch_add(1, Ordering::Relaxed) + 1;
        let elapsed = task_start.elapsed();
        println!("  [{}/{}] {} s{} {}x{} a[{}..{}] | {} new | {:.2}s",
            done, total_tasks,
            subtask.poly_name, subtask.scale as i64,
            grid_x, grid_y,
            subtask.angle_start, subtask.angle_end,
            task_new, elapsed.as_secs_f64());
    });

    let total_elapsed = global_start.elapsed();
    println!("\n=== COMPLETE ===");
    println!("Total time: {:.2}s", total_elapsed.as_secs_f64());
    println!("Unique templates: {}", store.template_count());
    println!("Total combinations: {}", store.generation_count());
}
