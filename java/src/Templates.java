import java.util.ArrayList;
import java.util.List;

public class Templates {
    public static final byte OUT = 0;
    public static final byte MAYBE = 1;
    public static final byte IN = 2;

    /// Classify each grid cell against the polygon using lightweight cells
    public static byte[][] getTemplateGridFast(
            long sx, long sy, long ex, long ey,
            long gridX, long gridY,
            Polygon poly) {
        int dx = (int)(ex - sx);
        int dy = (int)(ey - sy);
        byte[][] r = new byte[dx][dy]; // initialized to OUT (0)

        for (int x = 0; x < dx; x++) {
            double cxMin = (sx + x) * (double) gridX;
            double cxMax = (sx + x + 1) * (double) gridX;
            for (int y = 0; y < dy; y++) {
                double cyMin = (sy + y) * (double) gridY;
                double cyMax = (sy + y + 1) * (double) gridY;

                // Bbox rejection
                if (cxMin > poly.xMax || cxMax < poly.xMin
                    || cyMin > poly.yMax || cyMax < poly.yMin) {
                    continue; // already OUT
                }

                // Build cell polygon only when needed
                Polygon cell = Polygon.createSquare(cxMin, cyMin, cxMax, cyMax);

                if (poly.completelyContains(cell)) {
                    r[x][y] = IN;
                } else if (cell.completelyContains(poly) || poly.isPolyIntersect(cell)) {
                    r[x][y] = MAYBE;
                }
                // else stays OUT
            }
        }
        return r;
    }

    public static double angleToRadians(double angle) {
        return angle * Math.PI / 180.0;
    }

    public static List<Double> getAngles(double step) {
        List<Double> angles = new ArrayList<>();
        double i = 0.0;
        while (i < 360.0) {
            angles.add(i);
            i += step;
        }
        return angles;
    }
}
