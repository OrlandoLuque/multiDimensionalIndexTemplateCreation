use crate::polygon::*;
use crate::templates::{self, *};
use crate::matrix;
use std::collections::HashMap;
use std::time::Instant;

pub fn run_comparison() {
    let polys: Vec<(&str, Polygon)> = vec![
        ("drop", create_drop(0.2, 0.8)),
        ("box", create_box(1.0)),
        ("circle", create_circle(1.0)),
    ];
    let scale = 128.0;
    let grid_x: i64 = 16;
    let grid_y: i64 = 16;
    let angles = get_angles(0.5);

    println!("=== Performance test (3 polygons, 720 angles, all positions) ===\n");

    let global_start = Instant::now();
    for (name, poly) in &polys {
        let start = Instant::now();
        let scaled = scaled_copy(poly, scale, scale);
        let mut store: HashMap<Vec<u8>, u32> = HashMap::new();
        let mut count = 0u32;
        let mut total = 0u64;

        for angle in &angles {
            let rotated = rotated_copy(&scaled, angle_to_radians(*angle));
            for x in 0..grid_x {
                for y in 0..grid_y {
                    let mut moved = rotated.clone();
                    moved.move_by(x as f64, y as f64);
                    let gxr = [(moved.x_min / grid_x as f64).floor() as i64,
                               (moved.x_max / grid_x as f64).ceil() as i64];
                    let gyr = [(moved.y_min / grid_y as f64).floor() as i64,
                               (moved.y_max / grid_y as f64).ceil() as i64];
                    // Point 4: lightweight cells (no Polygon objects for grid)
                    let tpl = templates::get_template_grid_fast(
                        gxr[0], gyr[0], gxr[1], gyr[1], grid_x, grid_y, &moved,
                    );
                    let transforms = matrix::all_transforms(&tpl);
                    let hashes: Vec<Vec<u8>> = transforms.iter().map(|m| matrix::bin_code(m)).collect();
                    let mut found = false;
                    for h in &hashes {
                        if store.contains_key(h) { found = true; break; }
                    }
                    if !found {
                        count += 1;
                        store.insert(hashes[0].clone(), count);
                    }
                    total += 1;
                }
            }
        }
        let elapsed = start.elapsed();
        println!("  {} s{} {}x{}: {} unique / {} combinations | {:.2}s",
            name, scale as i64, grid_x, grid_y, count, total, elapsed.as_secs_f64());
    }
    println!("\n  Total: {:.2}s", global_start.elapsed().as_secs_f64());
}
