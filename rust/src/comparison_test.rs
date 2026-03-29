use crate::polygon::*;
use crate::templates::*;
use crate::matrix;

pub fn run_comparison() {
    let poly = create_box(1.0);
    let scaled = scaled_copy(&poly, 128.0, 128.0);
    let grid_x: i64 = 16;
    let grid_y: i64 = 16;

    let angles: Vec<f64> = vec![0.0, 1.0, 10.5, 22.5, 45.0, 67.5, 89.5, 90.0, 135.0, 180.0, 270.0];
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
                let grid = get_grid(gxr[0], gyr[0], gxr[1], gyr[1], grid_x, grid_y);
                let tpl = get_template_grid(&grid, &moved);
                let hash = matrix::bin_code(&tpl);
                let hex: String = hash.iter().map(|b| format!("{:02x}", b)).collect();
                println!("{}", hex);
            }
        }
    }
    eprintln!("DONE {} angles x {} positions", angles.len(), grid_x * grid_y);
}
