/// A vertex in a polygon, stored in array-based structure
public class Vertex {
    public double x;
    public double y;
    public Segment seg;

    public Vertex(double x, double y) {
        this.x = x;
        this.y = y;
        this.seg = new Segment();
    }

    public Vertex(double x, double y, double xc, double yc, int d) {
        this.x = x;
        this.y = y;
        this.seg = new Segment(xc, yc, d);
    }

    public Vertex copy() {
        Vertex v = new Vertex(x, y);
        v.seg = seg.copy();
        return v;
    }

    public boolean roughlyEquals(Vertex other) {
        return Math.abs(x - other.x) < 0.001 && Math.abs(y - other.y) < 0.001;
    }

    public boolean equals(Vertex other) {
        return x == other.x && y == other.y;
    }

    public boolean equalsXY(double ox, double oy) {
        return x == ox && y == oy;
    }

    /// Check if this point is between two other points (on a line segment)
    public boolean isInside(Vertex a, Vertex b) {
        double minX = Math.min(a.x, b.x);
        double maxX = Math.max(a.x, b.x);
        double minY = Math.min(a.y, b.y);
        double maxY = Math.max(a.y, b.y);
        return x >= minX && x <= maxX && y >= minY && y <= maxY;
    }
}
