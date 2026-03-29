use std::collections::HashMap;
use std::sync::atomic::{AtomicU32, Ordering};
use std::sync::Mutex;
use crate::polygon::{self, Polygon};
use crate::matrix::{self, Matrix};

pub const OUT: u8 = 0;
pub const MAYBE: u8 = 1;
pub const IN: u8 = 2;

/// Thread-safe template store (replaces Redis)
pub struct TemplateStore {
    /// hash -> template_id
    pub templates: Mutex<HashMap<Vec<u8>, u32>>,
    /// generation records: "params->op->id"
    pub generations: Mutex<Vec<String>>,
    /// next template ID
    pub counter: AtomicU32,
}

impl TemplateStore {
    pub fn new() -> Self {
        TemplateStore {
            templates: Mutex::new(HashMap::new()),
            generations: Mutex::new(Vec::new()),
            counter: AtomicU32::new(1),
        }
    }

    /// Store template with 8-symmetry deduplication. Returns (template_id, symmetry_op, is_new)
    pub fn store_dedup(&self, template_grid: &Matrix, gen_string: &str) -> (u32, &'static str, bool) {
        let transforms = matrix::all_transforms(template_grid);
        let hashes: Vec<Vec<u8>> = transforms.iter().map(|m| matrix::bin_code(m)).collect();

        let mut map = self.templates.lock().unwrap();

        // Check if any variant already exists
        for (i, hash) in hashes.iter().enumerate() {
            if let Some(&id) = map.get(hash) {
                let op = matrix::TRANSFORM_NAMES[i];
                let record = format!("{}->{}->{}", gen_string, op, id);
                self.generations.lock().unwrap().push(record);
                return (id, op, false);
            }
        }

        // New template
        let id = self.counter.fetch_add(1, Ordering::SeqCst);
        map.insert(hashes[0].clone(), id);
        let record = format!("{}->eq->{}", gen_string, id);
        self.generations.lock().unwrap().push(record);
        (id, "eq", true)
    }

    pub fn template_count(&self) -> u32 {
        self.counter.load(Ordering::SeqCst) - 1
    }

    pub fn generation_count(&self) -> usize {
        self.generations.lock().unwrap().len()
    }
}

/// Classify each grid cell against the polygon
pub fn get_template_grid(grid: &[Vec<Polygon>], poly: &Polygon) -> Matrix {
    let mut r: Matrix = Vec::new();
    for (ix, column) in grid.iter().enumerate() {
        r.push(Vec::new());
        for cell in column.iter() {
            // Fast bounding box rejection
            let val = if cell.x_min > poly.x_max || cell.x_max < poly.x_min
                || cell.y_min > poly.y_max || cell.y_max < poly.y_min
            {
                OUT
            } else if poly.completely_contains(cell) {
                IN
            } else if cell.completely_contains(poly) || poly.is_poly_intersect(cell) {
                MAYBE
            } else {
                OUT
            };
            r[ix].push(val);
        }
    }
    r
}

/// Generate the grid of square cells
pub fn get_grid(sx: i64, sy: i64, ex: i64, ey: i64, grid_x: i64, grid_y: i64) -> Vec<Vec<Polygon>> {
    let dx = ex - sx;
    let dy = ey - sy;
    let mut grid = Vec::new();
    for x in 0..dx {
        let mut column = Vec::new();
        let sx_cell = (sx + x) as f64 * grid_x as f64;
        let ex_cell = (sx + x + 1) as f64 * grid_x as f64;
        for y in 0..dy {
            let sy_cell = (sy + y) as f64 * grid_y as f64;
            let ey_cell = (sy + y + 1) as f64 * grid_y as f64;
            column.push(polygon::create_square(sx_cell, sy_cell, ex_cell, ey_cell));
        }
        grid.push(column);
    }
    grid
}

pub fn angle_to_radians(angle: f64) -> f64 {
    angle * std::f64::consts::PI / 180.0
}

pub fn get_angles(step: f64) -> Vec<f64> {
    let mut angles = Vec::new();
    let mut i = 0.0;
    while i < 360.0 {
        angles.push(i);
        i += step;
    }
    angles
}
