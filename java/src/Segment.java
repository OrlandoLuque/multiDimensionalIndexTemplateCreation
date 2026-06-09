/// Segment between vertices: line (d=0) or arc (d=-1 clockwise, d=1 counter-clockwise)
public class Segment {
    public double xc;
    public double yc;
    public int d; // -1 = clockwise arc, 0 = line, 1 = counter-clockwise arc

    public Segment() {
        this.xc = 0.0;
        this.yc = 0.0;
        this.d = 0;
    }

    public Segment(double xc, double yc, int d) {
        this.xc = xc;
        this.yc = yc;
        this.d = d;
    }

    public Segment copy() {
        return new Segment(xc, yc, d);
    }
}
