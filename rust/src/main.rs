mod vertex;
mod intersector;
mod polygon;
mod matrix;
mod templates;
mod comparison_test;

use std::sync::Arc;
use std::time::Instant;
use rayon::prelude::*;

use polygon::{Polygon, create_drop, create_box, create_circle, scaled_copy, rotated_copy};
use templates::{TemplateStore, get_template_grid, get_grid, angle_to_radians, get_angles};

/// Author: Orlando Jose Luque Moraira

fn main() {
    // Run comparison test if --compare flag
    if std::env::args().any(|a| a == "--compare") {
        comparison_test::run_comparison();
        return;
    }

    println!("=============================================================================");
    println!(" multiDimensionalIndexTemplateCreation (Rust)");
    println!(" Polygons vs grids intersection calculator");
    println!(" Author: Orlando Jose Luque Moraira");
    println!("=============================================================================\n");

    // Configuration (matching PHP defaults for comparison)
    let polygons: Vec<(&str, Polygon)> = vec![
        ("drop", create_drop(0.2, 0.8)),
        ("box", create_box(1.0)),
        ("circle", create_circle(1.0)),
    ];
    let scales: Vec<f64> = vec![128.0];
    let grid_sizes: Vec<i64> = vec![16];
    let angle_step = 0.5;
    let angles = get_angles(angle_step);

    let store = Arc::new(TemplateStore::new());

    println!("Config: {} polygons, {} scales, {} grids, {} angles (step {}deg)",
        polygons.len(), scales.len(), grid_sizes.len(), angles.len(), angle_step);

    // Build task list
    struct Task {
        poly_name: String,
        poly: Polygon,
        scale: f64,
        grid_size: i64,
    }

    let mut tasks: Vec<Task> = Vec::new();
    for (name, poly) in &polygons {
        for &scale in &scales {
            for &grid_size in &grid_sizes {
                tasks.push(Task {
                    poly_name: name.to_string(),
                    poly: poly.clone(),
                    scale,
                    grid_size,
                });
            }
        }
    }

    let total_tasks = tasks.len();
    println!("Tasks: {}\n", total_tasks);

    let global_start = Instant::now();

    // Process tasks in parallel with rayon
    tasks.par_iter().enumerate().for_each(|(task_idx, task)| {
        let task_start = Instant::now();
        let scaled = scaled_copy(&task.poly, task.scale, task.scale);
        let grid_x = task.grid_size;
        let grid_y = task.grid_size;

        let mut task_combinations = 0u64;
        let mut task_templates = 0u32;

        for angle in &angles {
            let rotated = rotated_copy(&scaled, angle_to_radians(*angle));

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
                        task.poly_name, task.scale as i64, grid_x, grid_y, angle, x, y);

                    let (_id, _op, is_new) = store.store_dedup(&template_grid, &gen_string);
                    if is_new { task_templates += 1; }
                    task_combinations += 1;
                }
            }
        }

        let elapsed = task_start.elapsed();
        println!("  Task {}/{}: {} s{} {}x{} | {} combinations, {} new templates | {:.2}s",
            task_idx + 1, total_tasks, task.poly_name, task.scale as i64,
            grid_x, grid_y, task_combinations, task_templates, elapsed.as_secs_f64());
    });

    let total_elapsed = global_start.elapsed();
    println!("\n=== COMPLETE ===");
    println!("Total time: {:.2}s", total_elapsed.as_secs_f64());
    println!("Unique templates: {}", store.template_count());
    println!("Total combinations: {}", store.generation_count());
}
