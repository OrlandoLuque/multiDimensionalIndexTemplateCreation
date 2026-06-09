import java.util.List;
import java.util.concurrent.ForkJoinPool;
import java.util.concurrent.atomic.AtomicInteger;

/// Author: Orlando Jose Luque Moraira
/// Mechanical port from Rust to Java 25 for performance comparison

public class Main {

    public static void main(String[] args) {
        boolean compare = false;
        boolean verify = false;
        boolean heavy = false;
        for (String a : args) {
            if ("--compare".equals(a)) compare = true;
            if ("--verify".equals(a)) verify = true;
            if ("--heavy".equals(a)) heavy = true;
        }

        if (verify) {
            runVerify();
            return;
        }
        if (heavy) {
            runBench("=== HEAVY benchmark (grids 16+32+64+128) ===", new long[]{16, 32, 64, 128});
            return;
        }
        if (compare) {
            runBench("=== Parallel subtask test (grids 16+32) ===", new long[]{16, 32});
            return;
        }

        System.out.println("=============================================================================");
        System.out.println(" multiDimensionalIndexTemplateCreation (Java)");
        System.out.println(" Polygons vs grids intersection calculator");
        System.out.println(" Author: Orlando Jose Luque Moraira");
        System.out.println("=============================================================================\n");

        // Configuration (same as Rust)
        String[] polyNames = {"drop", "box", "circle"};
        Polygon[] polys = {
            Polygon.createDrop(0.2, 0.8),
            Polygon.createBox(1.0),
            Polygon.createCircle(1.0),
        };
        double[] scales = {128.0};
        long[] gridSizes = {16};
        double angleStep = 0.5;
        List<Double> angles = Templates.getAngles(angleStep);
        long maxPerTask = 500_000;

        List<SubTask> subtasks = TaskUtil.createSubtasks(
            polyNames, polys, scales, gridSizes, angles.size(), maxPerTask);
        int totalTasks = subtasks.size();

        int threads = Runtime.getRuntime().availableProcessors();
        System.out.printf("Config: %d polygons, %d scales, %d grids, %d angles (step %.1fdeg)%n",
            polyNames.length, scales.length, gridSizes.length, angles.size(), angleStep);
        TaskUtil.printSummary(subtasks);
        System.out.printf("Threads: %d | Mode: In-memory (single process)%n%n", threads);

        runInMemory(subtasks, angles, totalTasks, threads);
    }

    private static void runInMemory(List<SubTask> subtasks, List<Double> angles,
                                     int totalTasks, int threads) {
        TemplateStore store = new TemplateStore();
        AtomicInteger completed = new AtomicInteger(0);
        long globalStart = System.nanoTime();

        ForkJoinPool pool = new ForkJoinPool(threads);
        try {
            pool.submit(() -> subtasks.parallelStream().forEach(st -> {
                processSubtask(st, angles, store, completed, totalTasks);
            })).get();
        } catch (Exception e) {
            e.printStackTrace();
        }
        pool.shutdown();

        double elapsed = (System.nanoTime() - globalStart) / 1_000_000_000.0;
        System.out.println("\n=== COMPLETE ===");
        System.out.printf("Total time: %.2fs%n", elapsed);
        System.out.printf("Unique templates: %d%n", store.templateCount());
        System.out.printf("Total combinations: %d%n", store.generationCount());
    }

    private static void processSubtask(SubTask st, List<Double> angles,
                                        TemplateStore store,
                                        AtomicInteger completed, int totalTasks) {
        long taskStart = System.nanoTime();
        Polygon scaled = Polygon.scaledCopy(st.poly, st.scale, st.scale);
        long gx = st.gridSize;
        long gy = st.gridSize;
        int newCount = 0;

        for (int angleIdx = st.angleStart; angleIdx < st.angleEnd; angleIdx++) {
            double angle = angles.get(angleIdx);
            Polygon rotated = Polygon.rotatedCopy(scaled, Templates.angleToRadians(angle));

            for (long x = 0; x < gx; x++) {
                for (long y = 0; y < gy; y++) {
                    Polygon moved = rotated.copy();
                    moved.moveBy((double) x, (double) y);

                    long[] gxr = {
                        (long) Math.floor(moved.xMin / gx),
                        (long) Math.ceil(moved.xMax / gx),
                    };
                    long[] gyr = {
                        (long) Math.floor(moved.yMin / gy),
                        (long) Math.ceil(moved.yMax / gy),
                    };

                    byte[][] tpl = Templates.getTemplateGridFast(
                        gxr[0], gyr[0], gxr[1], gyr[1], gx, gy, moved);

                    String genString = String.format("%s-s%d-x%d,y%d-a%s-dx%d,dy%d",
                        st.polyName, (long) st.scale, gx, gy, formatAngle(angle), x, y);

                    int[] result = store.storeDedup(tpl, genString);
                    if (result[2] == 1) newCount++;
                }
            }
        }

        int done = completed.incrementAndGet();
        double elapsed = (System.nanoTime() - taskStart) / 1_000_000_000.0;
        System.out.printf("  [%d/%d] %s s%d %dx%d a[%d..%d] | %d new | %.2fs%n",
            done, totalTasks,
            st.polyName, (long) st.scale,
            gx, gy, st.angleStart, st.angleEnd,
            newCount, elapsed);
    }

    private static void runBench(String title, long[] gridSizes) {
        String[] polyNames = {"drop", "box", "circle"};
        Polygon[] polys = {
            Polygon.createDrop(0.2, 0.8),
            Polygon.createBox(1.0),
            Polygon.createCircle(1.0),
        };
        double[] scales = {128.0};
        List<Double> angles = Templates.getAngles(0.5);
        long maxPerTask = 500_000;

        List<SubTask> subtasks = TaskUtil.createSubtasks(
            polyNames, polys, scales, gridSizes, angles.size(), maxPerTask);
        TemplateStore store = new TemplateStore();
        AtomicInteger completed = new AtomicInteger(0);
        int total = subtasks.size();

        int threads = Runtime.getRuntime().availableProcessors();
        System.out.println(title);
        TaskUtil.printSummary(subtasks);
        System.out.printf("Threads: %d%n%n", threads);

        long start = System.nanoTime();

        ForkJoinPool pool = new ForkJoinPool(threads);
        try {
            pool.submit(() -> subtasks.parallelStream().forEach(st -> {
                long t = System.nanoTime();
                Polygon scaledPoly = Polygon.scaledCopy(st.poly, st.scale, st.scale);
                long gx = st.gridSize;
                long gy = st.gridSize;
                int newCount = 0;

                for (int ai = st.angleStart; ai < st.angleEnd; ai++) {
                    Polygon rotated = Polygon.rotatedCopy(scaledPoly, Templates.angleToRadians(angles.get(ai)));
                    for (long x = 0; x < gx; x++) {
                        for (long y = 0; y < gy; y++) {
                            Polygon moved = rotated.copy();
                            moved.moveBy((double) x, (double) y);
                            long[] gxr = {(long) Math.floor(moved.xMin / gx),
                                          (long) Math.ceil(moved.xMax / gx)};
                            long[] gyr = {(long) Math.floor(moved.yMin / gy),
                                          (long) Math.ceil(moved.yMax / gy)};
                            byte[][] tpl = Templates.getTemplateGridFast(
                                gxr[0], gyr[0], gxr[1], gyr[1], gx, gy, moved);
                            int[] res = store.storeDedup(tpl, "");
                            if (res[2] == 1) newCount++;
                        }
                    }
                }

                int done = completed.incrementAndGet();
                if (done % 5 == 0 || done == total) {
                    double elapsed = (System.nanoTime() - t) / 1_000_000_000.0;
                    System.out.printf("  [%d/%d] %s s%d %dx%d a[%d..%d] | %d new | %.2fs%n",
                        done, total, st.polyName, (long) st.scale,
                        gx, gy, st.angleStart, st.angleEnd,
                        newCount, elapsed);
                }
            })).get();
        } catch (Exception e) {
            e.printStackTrace();
        }
        pool.shutdown();

        double totalElapsed = (System.nanoTime() - start) / 1_000_000_000.0;
        System.out.printf("%n  Total: %.2fs | %d unique templates | %d combinations%n",
            totalElapsed, store.templateCount(), store.generationCount());
    }

    /// Single-threaded verification mode for deterministic comparison with Rust
    private static void runVerify() {
        String[] polyNames = {"drop", "box", "circle"};
        Polygon[] polys = {
            Polygon.createDrop(0.2, 0.8),
            Polygon.createBox(1.0),
            Polygon.createCircle(1.0),
        };
        double[] scales = {128.0};
        long[] gridSizes = {16};
        List<Double> angles = Templates.getAngles(0.5);
        long maxPerTask = 500_000;

        List<SubTask> subtasks = TaskUtil.createSubtasks(
            polyNames, polys, scales, gridSizes, angles.size(), maxPerTask);
        TemplateStore store = new TemplateStore();
        int total = subtasks.size();

        System.out.println("=== Single-threaded verification (grid 16) ===");
        TaskUtil.printSummary(subtasks);
        System.out.println("Threads: 1 (sequential)\n");

        long start = System.nanoTime();

        for (int idx = 0; idx < subtasks.size(); idx++) {
            SubTask st = subtasks.get(idx);
            long t = System.nanoTime();
            Polygon scaledPoly = Polygon.scaledCopy(st.poly, st.scale, st.scale);
            long gx = st.gridSize;
            long gy = st.gridSize;
            int newCount = 0;

            for (int ai = st.angleStart; ai < st.angleEnd; ai++) {
                Polygon rotated = Polygon.rotatedCopy(scaledPoly, Templates.angleToRadians(angles.get(ai)));
                for (long x = 0; x < gx; x++) {
                    for (long y = 0; y < gy; y++) {
                        Polygon moved = rotated.copy();
                        moved.moveBy((double) x, (double) y);
                        long[] gxr = {(long) Math.floor(moved.xMin / gx),
                                      (long) Math.ceil(moved.xMax / gx)};
                        long[] gyr = {(long) Math.floor(moved.yMin / gy),
                                      (long) Math.ceil(moved.yMax / gy)};
                        byte[][] tpl = Templates.getTemplateGridFast(
                            gxr[0], gyr[0], gxr[1], gyr[1], gx, gy, moved);
                        int[] res = store.storeDedup(tpl, "");
                        if (res[2] == 1) newCount++;
                    }
                }
            }

            double elapsed = (System.nanoTime() - t) / 1_000_000_000.0;
            System.out.printf("  [%d/%d] %s s%d %dx%d a[%d..%d] | %d new | %.2fs%n",
                idx + 1, total, st.polyName, (long) st.scale,
                gx, gy, st.angleStart, st.angleEnd,
                newCount, elapsed);
        }

        double totalElapsed = (System.nanoTime() - start) / 1_000_000_000.0;
        System.out.printf("%n  Total: %.2fs | %d unique templates | %d combinations%n",
            totalElapsed, store.templateCount(), store.generationCount());
    }

    private static String formatAngle(double angle) {
        if (angle == Math.floor(angle)) {
            return String.valueOf((long) angle);
        }
        return String.valueOf(angle);
    }
}
