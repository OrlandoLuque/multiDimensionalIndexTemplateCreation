import java.util.ArrayList;
import java.util.List;

/// 2D matrix operations and binary encoding for template deduplication
public class Matrix {
    public static final byte OUT = 0;
    public static final byte MAYBE = 1;
    public static final byte IN = 2;

    public static final String[] TRANSFORM_NAMES = {"eq", "rCC", "rC", "r180", "fLR", "fTB", "fTLBR", "fTRBL"};

    public static int[] dimensions(byte[][] m) {
        if (m.length == 0) return new int[]{0, 0};
        return new int[]{m.length, m[0].length};
    }

    public static byte[][] equal(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[tx][ty];
        for (int x = 0; x < tx; x++) {
            System.arraycopy(m[x], 0, r[x], 0, ty);
        }
        return r;
    }

    public static byte[][] rotateClockwise90(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[ty][tx];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[ty - y - 1][x] = m[x][y];
            }
        }
        return r;
    }

    public static byte[][] rotateCounterClockwise90(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[ty][tx];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[y][tx - x - 1] = m[x][y];
            }
        }
        return r;
    }

    public static byte[][] rotate180(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[tx][ty];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[tx - x - 1][ty - y - 1] = m[x][y];
            }
        }
        return r;
    }

    public static byte[][] flipLR(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[tx][ty];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[tx - x - 1][y] = m[x][y];
            }
        }
        return r;
    }

    public static byte[][] flipTB(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[tx][ty];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[x][ty - y - 1] = m[x][y];
            }
        }
        return r;
    }

    public static byte[][] flipTLBR(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[ty][tx];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[ty - y - 1][tx - x - 1] = m[x][y];
            }
        }
        return r;
    }

    public static byte[][] flipTRBL(byte[][] m) {
        int tx = m.length;
        if (tx == 0) return new byte[0][0];
        int ty = m[0].length;
        byte[][] r = new byte[ty][tx];
        for (int x = 0; x < tx; x++) {
            for (int y = 0; y < ty; y++) {
                r[y][x] = m[x][y];
            }
        }
        return r;
    }

    public static List<byte[][]> allTransforms(byte[][] m) {
        List<byte[][]> list = new ArrayList<>(8);
        list.add(equal(m));
        list.add(rotateClockwise90(m));
        list.add(rotateCounterClockwise90(m));
        list.add(rotate180(m));
        list.add(flipLR(m));
        list.add(flipTB(m));
        list.add(flipTLBR(m));
        list.add(flipTRBL(m));
        return list;
    }

    /// Binary encoding: 2 bytes header (width, height) + 2 bits per cell
    public static byte[] binCode(byte[][] m) {
        int[] dims = dimensions(m);
        int tx = dims[0], ty = dims[1];
        int capacity = 2 + (tx * ty + 3) / 4;
        byte[] result = new byte[capacity];
        int pos = 0;
        result[pos++] = (byte) tx;
        result[pos++] = (byte) ty;

        int byteVal = 0;
        int pair = 0;
        for (int y = 0; y < ty; y++) {
            for (int x = 0; x < tx; x++) {
                byteVal = (byteVal << 2) | m[x][y];
                pair++;
                if (pair == 4) {
                    result[pos++] = (byte) byteVal;
                    byteVal = 0;
                    pair = 0;
                }
            }
        }
        // Match PHP/Rust behavior: don't flush remaining bits
        // Trim to actual size
        byte[] trimmed = new byte[pos];
        System.arraycopy(result, 0, trimmed, 0, pos);
        return trimmed;
    }

    public static String toString(byte[][] m) {
        int[] dims = dimensions(m);
        int tx = dims[0], ty = dims[1];
        StringBuilder sb = new StringBuilder();
        for (int y = 0; y < ty; y++) {
            for (int x = 0; x < tx; x++) {
                sb.append('\t');
                sb.append(m[x][y]);
            }
            sb.append('\n');
        }
        return sb.toString();
    }
}
