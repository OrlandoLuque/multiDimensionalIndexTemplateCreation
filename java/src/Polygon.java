import java.util.ArrayList;
import java.util.List;

public class Polygon {
    public List<Vertex> vertices;
    public double xMin;
    public double xMax;
    public double yMin;
    public double yMax;

    public Polygon() {
        vertices = new ArrayList<>();
        xMin = Double.MAX_VALUE;
        xMax = -Double.MAX_VALUE;
        yMin = Double.MAX_VALUE;
        yMax = -Double.MAX_VALUE;
    }

    public void addv(double x, double y) {
        vertices.add(new Vertex(x, y));
        updateBounds(x, y);
    }

    public void addvArc(double x, double y, double xc, double yc, int d) {
        vertices.add(new Vertex(x, y, xc, yc, d));
        updateBounds(x, y);
    }

    public void finalizeBounds() {
        for (Vertex v : vertices) {
            if (v.seg.d != 0) {
                recalcBounds();
                return;
            }
        }
    }

    private void updateBounds(double x, double y) {
        if (x < xMin) xMin = x;
        if (x > xMax) xMax = x;
        if (y < yMin) yMin = y;
        if (y > yMax) yMax = y;
    }

    public void recalcBounds() {
        xMin = Double.MAX_VALUE;
        xMax = -Double.MAX_VALUE;
        yMin = Double.MAX_VALUE;
        yMax = -Double.MAX_VALUE;
        int n = vertices.size();
        for (int i = 0; i < n; i++) {
            double x = vertices.get(i).x;
            double y = vertices.get(i).y;
            if (x < xMin) xMin = x;
            if (x > xMax) xMax = x;
            if (y < yMin) yMin = y;
            if (y > yMax) yMax = y;

            if (vertices.get(i).seg.d != 0) {
                int j = (i + 1) % n;
                double xc = vertices.get(i).seg.xc;
                double yc = vertices.get(i).seg.yc;
                double r = Intersector.dist(vertices.get(i).x, vertices.get(i).y, xc, yc);
                double aStart = Intersector.angle(xc, yc, vertices.get(i).x, vertices.get(i).y);
                double aEnd = Intersector.angle(xc, yc, vertices.get(j).x, vertices.get(j).y);
                double[] cardinalAngles = {0.0, Math.PI / 2.0, Math.PI, 3.0 * Math.PI / 2.0};
                double[][] cardinalOffsets = {{r, 0.0}, {0.0, r}, {-r, 0.0}, {0.0, -r}};
                for (int k = 0; k < 4; k++) {
                    double ca = cardinalAngles[k];
                    boolean inArc;
                    if (vertices.get(i).seg.d == -1) {
                        if (aEnd <= aStart) {
                            inArc = ca >= aEnd && ca <= aStart;
                        } else {
                            inArc = ca >= aEnd || ca <= aStart;
                        }
                    } else {
                        if (aEnd >= aStart) {
                            inArc = ca >= aStart && ca <= aEnd;
                        } else {
                            inArc = ca >= aStart || ca <= aEnd;
                        }
                    }
                    if (inArc) {
                        double px = xc + cardinalOffsets[k][0];
                        double py = yc + cardinalOffsets[k][1];
                        if (px < xMin) xMin = px;
                        if (px > xMax) xMax = px;
                        if (py < yMin) yMin = py;
                        if (py > yMax) yMax = py;
                    }
                }
            }
        }
    }

    public int vertexCount() {
        return vertices.size();
    }

    private int nextIdx(int i) {
        return (i + 1) % vertices.size();
    }

    public void moveBy(double dx, double dy) {
        for (Vertex v : vertices) {
            v.x += dx;
            v.y += dy;
            if (v.seg.d != 0) {
                v.seg.xc += dx;
                v.seg.yc += dy;
            }
        }
        xMin += dx; xMax += dx;
        yMin += dy; yMax += dy;
    }

    public void scale(double sx, double sy) {
        for (Vertex v : vertices) {
            v.x *= sx;
            v.y *= sy;
            if (v.seg.d != 0) {
                v.seg.xc *= sx;
                v.seg.yc *= sy;
            }
        }
        recalcBounds();
    }

    public void rotate(double xr, double yr, double angle) {
        double cosA = Math.cos(angle);
        double sinA = Math.sin(angle);
        for (Vertex v : vertices) {
            double x = v.x - xr;
            double y = v.y - yr;
            v.x = x * cosA - y * sinA + xr;
            v.y = x * sinA + y * cosA + yr;
            if (v.seg.d != 0) {
                double cx = v.seg.xc - xr;
                double cy = v.seg.yc - yr;
                v.seg.xc = cx * cosA - cy * sinA + xr;
                v.seg.yc = cx * sinA + cy * cosA + yr;
            }
        }
        recalcBounds();
    }

    public Polygon brect() {
        Polygon p = new Polygon();
        p.addv(xMin, yMin);
        p.addv(xMax, yMin);
        p.addv(xMax, yMax);
        p.addv(xMin, yMax);
        return p;
    }

    /// Check if a point is inside using winding number ray-casting
    public boolean isInside(double vx, double vy) {
        Vertex testPoint = new Vertex(vx, vy);
        Vertex infinity = new Vertex(-10_000_000.0, vy);
        int n = vertices.size();
        int windingNumber = 0;

        for (int i = 0; i < n; i++) {
            int j = nextIdx(i);
            Vertex q = vertices.get(i);
            Vertex r = vertices.get(j);

            List<Vertex> onEdge;
            if (q.seg.d == 0) {
                onEdge = Intersector.intersection(testPoint, testPoint, q, r);
            } else {
                onEdge = Intersector.lineArcIntersection(testPoint, testPoint, q, r, false);
            }
            if (!onEdge.isEmpty()) {
                return true;
            }

            List<Vertex> intList;
            if (q.seg.d == 0) {
                intList = Intersector.intersection(infinity, testPoint, q, r);
            } else {
                intList = Intersector.lineArcIntersection(infinity, testPoint, q, r, true);
            }

            if (intList.size() == 2 && q.seg.d != 0) {
                boolean qIntercepts = !Intersector.intersection(infinity, testPoint, q, q).isEmpty();
                boolean rIntercepts = !Intersector.intersection(infinity, testPoint, r, r).isEmpty();
                if (qIntercepts ^ rIntercepts) {
                    windingNumber += 1;
                }
            } else if (intList.size() == 1) {
                boolean qIntercepts = !Intersector.intersection(infinity, testPoint, q, q).isEmpty();
                boolean rIntercepts = !Intersector.intersection(infinity, testPoint, r, r).isEmpty();
                if ((!qIntercepts && !rIntercepts)
                    || (qIntercepts && isVerticalVertex(i))) {
                    windingNumber += 1;
                }
            }
        }
        return windingNumber % 2 == 1;
    }

    private int verticalDirection(int t, int pointForArc) {
        Vertex v = vertices.get(t);
        int n = nextIdx(t);
        Vertex next = vertices.get(n);
        if (v.seg.d == 0) {
            if (v.y < next.y) return 1;
            else if (v.y > next.y) return -1;
            else return 0;
        } else {
            double a;
            if (pointForArc >= 0) {
                a = Intersector.angle(v.seg.xc, v.seg.yc, vertices.get(pointForArc).x, vertices.get(pointForArc).y);
            } else {
                a = Intersector.angle(v.seg.xc, v.seg.yc, v.x, v.y);
            }
            double cosA = Math.cos(a);
            int td = v.seg.d;
            if (Math.abs(cosA) < 1e-10) {
                double sinA = Math.sin(a);
                double effectiveCos;
                if (pointForArc >= 0) {
                    effectiveCos = sinA * td;
                } else {
                    effectiveCos = -sinA * td;
                }
                return (effectiveCos > 0.0 ? 1 : -1) * td;
            } else {
                return (cosA > 0.0 ? 1 : -1) * td;
            }
        }
    }

    private boolean isHorizontalLine(int t) {
        Vertex v = vertices.get(t);
        return v.seg.d == 0 && v.y == vertices.get(nextIdx(t)).y;
    }

    private boolean isVerticalVertex(int i) {
        int n = vertices.size();
        int prev = (i == 0) ? n - 1 : i - 1;
        int prevPost = i;
        while (isHorizontalLine(prev)) {
            prevPost = prev;
            prev = (prev == 0) ? n - 1 : prev - 1;
        }
        int prevDirection = verticalDirection(prev, prevPost);
        int direction = verticalDirection(i, -1);
        return (prevDirection == 1 && direction == 1) || (prevDirection == -1 && direction == -1);
    }

    /// Check if this polygon completely contains another polygon
    public boolean completelyContains(Polygon other) {
        if (other.xMin < xMin || other.xMax > xMax
            || other.yMin < yMin || other.yMax > yMax) {
            return false;
        }
        for (Vertex v : other.vertices) {
            if (!isInside(v.x, v.y)) {
                return false;
            }
        }
        int nSelf = vertices.size();
        int nOther = other.vertices.size();
        for (int i = 0; i < nSelf; i++) {
            int si = nextIdx(i);
            for (int j = 0; j < nOther; j++) {
                int oj = other.nextIdx(j);
                List<Vertex> ints = edgeIntersection(i, si, other, j, oj);
                if (ints.size() == 1) {
                    Vertex intV = ints.get(0);
                    Vertex sv = vertices.get(i);
                    Vertex sn = vertices.get(si);
                    Vertex cv = other.vertices.get(j);
                    Vertex cn = other.vertices.get(oj);
                    if (sv.seg.d == 0 && cv.seg.d == 0
                        && !(intV.equals(sv) || intV.equals(sn) || intV.equals(cv) || intV.equals(cn))) {
                        return false;
                    }
                } else if (ints.size() == 2) {
                    if (vertices.get(i).seg.d != 0 || other.vertices.get(j).seg.d != 0) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /// Check if this polygon intersects another polygon
    public boolean isPolyIntersect(Polygon other) {
        if (xMax < other.xMin || xMin > other.xMax
            || yMax < other.yMin || yMin > other.yMax) {
            return false;
        }
        int nSelf = vertices.size();
        int nOther = other.vertices.size();
        for (int i = 0; i < nSelf; i++) {
            int si = nextIdx(i);
            for (int j = 0; j < nOther; j++) {
                int oj = other.nextIdx(j);
                if (!edgeIntersection(i, si, other, j, oj).isEmpty()) {
                    return true;
                }
            }
        }
        return false;
    }

    private List<Vertex> edgeIntersection(int i, int ni, Polygon other, int j, int nj) {
        Vertex p1 = vertices.get(i);
        Vertex p2 = vertices.get(ni);
        Vertex q1 = other.vertices.get(j);
        Vertex q2 = other.vertices.get(nj);

        int pt = p1.seg.d;
        int qt = q1.seg.d;

        if (pt == 0 && qt == 0) {
            return Intersector.intersection(p1, p2, q1, q2);
        } else if (pt == 0 && qt != 0) {
            return Intersector.lineArcIntersection(p1, p2, q1, q2, false);
        } else if (pt != 0 && qt == 0) {
            return Intersector.lineArcIntersection(q1, q2, p1, p2, false);
        } else {
            return Intersector.intersection(p1, p2, q1, q2);
        }
    }

    public Polygon copy() {
        Polygon p = new Polygon();
        for (Vertex v : vertices) {
            p.vertices.add(v.copy());
        }
        p.xMin = xMin;
        p.xMax = xMax;
        p.yMin = yMin;
        p.yMax = yMax;
        return p;
    }

    // === Factory methods ===

    public static Polygon createDrop(double width, double height) {
        Polygon p = new Polygon();
        p.addvArc(-width, height, 0.0, height, -1);
        p.addv(width, height);
        p.addv(0.0, 0.0);
        p.finalizeBounds();
        return p;
    }

    public static Polygon createCircle(double radius) {
        Polygon p = new Polygon();
        p.addvArc(0.0, -radius, 0.0, 0.0, -1);
        p.addvArc(0.0, radius, 0.0, 0.0, -1);
        p.finalizeBounds();
        return p;
    }

    public static Polygon createBox(double side) {
        double half = side / 2.0;
        return createSquare(-half, -half, half, half);
    }

    public static Polygon createSquare(double sx, double sy, double ex, double ey) {
        Polygon p = new Polygon();
        p.addv(sx, sy);
        p.addv(ex, sy);
        p.addv(ex, ey);
        p.addv(sx, ey);
        return p;
    }

    public static Polygon scaledCopy(Polygon poly, double sx, double sy) {
        Polygon p = poly.copy();
        p.scale(sx, sy);
        return p;
    }

    public static Polygon rotatedCopy(Polygon poly, double angle) {
        Polygon p = poly.copy();
        p.rotate(0.0, 0.0, angle);
        return p;
    }
}
