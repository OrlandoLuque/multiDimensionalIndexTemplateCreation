import java.util.ArrayList;
import java.util.List;

public class Intersector {
    public static final double EPSILON = 0.00001;

    public static double dist(double x1, double y1, double x2, double y2) {
        return Math.sqrt((x1 - x2) * (x1 - x2) + (y1 - y2) * (y1 - y2));
    }

    public static double angle(double xc, double yc, double x1, double y1) {
        double d = dist(xc, yc, x1, y1);
        if (!(isZero(d))) {
            if (Math.asin((y1 - yc) / d) >= 0.0) {
                return Math.acos((x1 - xc) / d);
            } else {
                return 2.0 * Math.PI - Math.acos((x1 - xc) / d);
            }
        } else {
            return 0.0;
        }
    }

    private static boolean isZero(double v) {
        return v == 0.0;
    }

    private static boolean isEqual(double a, double b) {
        return a == b;
    }

    /// Line-line segment intersection
    public static List<Vertex> intersection(Vertex a1, Vertex a2, Vertex b1, Vertex b2) {
        List<Vertex> result = new ArrayList<>();

        // Both points
        if (a1.equals(a2) && b1.equals(b2)) {
            if (a1.equals(b1)) result.add(a1.copy());
            return result;
        }
        // b is a point
        if (b1.equals(b2)) {
            if (pointOnLine(b1, a1, a2)) result.add(b1.copy());
            return result;
        }
        // a is a point
        if (a1.equals(a2)) {
            if (pointOnLine(a1, b1, b2)) result.add(a1.copy());
            return result;
        }

        double uaT = (b2.x - b1.x) * (a1.y - b1.y) - (b2.y - b1.y) * (a1.x - b1.x);
        double ubT = (a2.x - a1.x) * (a1.y - b1.y) - (a2.y - a1.y) * (a1.x - b1.x);
        double uB = (b2.y - b1.y) * (a2.x - a1.x) - (b2.x - b1.x) * (a2.y - a1.y);

        if (!(-EPSILON < uB && uB < EPSILON)) {
            double ua = uaT / uB;
            double ub = ubT / uB;
            if (ua >= 0.0 && ua <= 1.0 && ub >= 0.0 && ub <= 1.0) {
                result.add(new Vertex(
                    a1.x + ua * (a2.x - a1.x),
                    a1.y + ua * (a2.y - a1.y)
                ));
            }
        } else {
            // Parallel or coincident
            if ((-EPSILON < uaT && uaT < EPSILON) || (-EPSILON < ubT && ubT < EPSILON)) {
                if (a1.equals(a2)) {
                    result.addAll(oneDIntersection(b1, b2, a1, a2));
                } else {
                    result.addAll(oneDIntersection(a1, a2, b1, b2));
                }
            }
        }
        return result;
    }

    private static List<Double> overlapIntervals(double ub1, double ub2) {
        List<Double> result = new ArrayList<>();
        double l = Math.min(ub1, ub2);
        double r = Math.max(ub1, ub2);
        double a = Math.max(0.0, l);
        double b = Math.min(1.0, r);
        if (a > b) {
            return result;
        } else if (a == b) {
            result.add(a);
        } else {
            result.add(a);
            result.add(b);
        }
        return result;
    }

    private static List<Vertex> oneDIntersection(Vertex a1, Vertex a2, Vertex b1, Vertex b2) {
        double denomx = a2.x - a1.x;
        double denomy = a2.y - a1.y;
        double ub1, ub2;
        if (Math.abs(denomx) > Math.abs(denomy)) {
            ub1 = (b1.x - a1.x) / denomx;
            ub2 = (b2.x - a1.x) / denomx;
        } else {
            ub1 = (b1.y - a1.y) / denomy;
            ub2 = (b2.y - a1.y) / denomy;
        }

        List<Vertex> result = new ArrayList<>();
        for (double f : overlapIntervals(ub1, ub2)) {
            result.add(new Vertex(a2.x * f + a1.x * (1.0 - f), a2.y * f + a1.y * (1.0 - f)));
        }
        return result;
    }

    private static boolean pointOnLine(Vertex p, Vertex a1, Vertex a2) {
        double d = distFromSeg(p, a1, a2);
        return d < EPSILON
            && (p.x >= Math.min(a1.x, a2.x) && p.x <= Math.max(a1.x, a2.x))
            && (p.y >= Math.min(a1.y, a2.y) && p.y <= Math.max(a1.y, a2.y));
    }

    private static double distFromSeg(Vertex p, Vertex q0, Vertex q1) {
        double dx21 = q1.x - q0.x;
        double dy21 = q1.y - q0.y;
        double dx10 = q0.x - p.x;
        double dy10 = q0.y - p.y;
        double segLength = Math.sqrt(dx21 * dx21 + dy21 * dy21);
        if (segLength < EPSILON) {
            return Double.MAX_VALUE;
        }
        return Math.abs(dx21 * dy10 - dx10 * dy21) / segLength;
    }

    /// Line-circle intersection
    public static List<Vertex> lineCircleIntersection(Vertex l1, Vertex l2, Vertex c, double radius) {
        List<Vertex> result = new ArrayList<>();
        double dx = l2.x - l1.x;
        double dy = l2.y - l1.y;
        double a = dx * dx + dy * dy;
        double b = 2.0 * (dx * (l1.x - c.x) + dy * (l1.y - c.y));
        double cc = (l1.x - c.x) * (l1.x - c.x) + (l1.y - c.y) * (l1.y - c.y) - radius * radius;
        double det = b * b - 4.0 * a * cc;

        if (a <= 0.0000001 || det < 0.0) {
            return result;
        } else if (isZero(det)) {
            double t = -b / (2.0 * a);
            result.add(new Vertex(l1.x + t * dx, l1.y + t * dy));
        } else {
            double t1 = (-b + Math.sqrt(det)) / (2.0 * a);
            double t2 = (-b - Math.sqrt(det)) / (2.0 * a);
            result.add(new Vertex(l1.x + t1 * dx, l1.y + t1 * dy));
            result.add(new Vertex(l1.x + t2 * dx, l1.y + t2 * dy));
        }
        return result;
    }

    /// Line-arc intersection
    public static List<Vertex> lineArcIntersection(
            Vertex l1, Vertex l2,
            Vertex a1, Vertex a2,
            boolean ignoreTouch) {
        double xc = a1.seg.xc;
        double yc = a1.seg.yc;
        double radius = dist(xc, yc, a1.x, a1.y);
        List<Vertex> circleInts = lineCircleIntersection(l1, l2, new Vertex(xc, yc), radius);

        boolean touch = circleInts.size() == 1
            && !a1.roughlyEquals(circleInts.get(0))
            && !a2.roughlyEquals(circleInts.get(0));
        if (touch && ignoreTouch) {
            return new ArrayList<>();
        }

        double arcAngle1 = angle(xc, yc, a1.x, a1.y);
        double arcAngle2 = angle(xc, yc, a2.x, a2.y);
        if (a1.seg.d == -1) {
            double tmp = arcAngle1;
            arcAngle1 = arcAngle2;
            arcAngle2 = tmp;
        }

        List<Vertex> result = new ArrayList<>();
        for (Vertex intV : circleInts) {
            if (intV.roughlyEquals(a1)) {
                intV = a1.copy();
            }
            if (intV.roughlyEquals(a2)) {
                intV = a2.copy();
            }
            if (intV.isInside(l1, l2)) {
                double intAngle = angle(xc, yc, intV.x, intV.y);
                boolean inArc;
                if (arcAngle2 >= arcAngle1) {
                    inArc = intAngle >= arcAngle1 && intAngle <= arcAngle2;
                } else {
                    inArc = intAngle <= arcAngle2 || intAngle >= arcAngle1;
                }
                if (inArc) {
                    result.add(intV);
                }
            }
        }
        return result;
    }
}
