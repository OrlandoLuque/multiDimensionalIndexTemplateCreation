import java.util.ArrayList;
import java.util.List;

public class TaskUtil {

    /// Create subtasks with approximately equal work, splitting by angle ranges
    public static List<SubTask> createSubtasks(
            String[] polyNames, Polygon[] polys,
            double[] scales, long[] gridSizes,
            int angleCount, long maxCombinations) {
        List<SubTask> tasks = new ArrayList<>();
        int id = 0;

        for (int p = 0; p < polyNames.length; p++) {
            for (double scale : scales) {
                for (long gridSize : gridSizes) {
                    long positions = gridSize * gridSize;
                    int anglesPerSubtask = (int) Math.max(maxCombinations / positions, 1);
                    int angleStart = 0;

                    while (angleStart < angleCount) {
                        int angleEnd = Math.min(angleStart + anglesPerSubtask, angleCount);
                        long combos = (long)(angleEnd - angleStart) * positions;

                        tasks.add(new SubTask(id, polyNames[p], polys[p], scale, gridSize,
                            angleStart, angleEnd, combos));
                        id++;
                        angleStart = angleEnd;
                    }
                }
            }
        }
        return tasks;
    }

    /// Print task distribution summary
    public static void printSummary(List<SubTask> tasks) {
        long totalCombos = 0;
        long minCombos = Long.MAX_VALUE;
        long maxCombos = 0;
        for (SubTask t : tasks) {
            totalCombos += t.combinations;
            if (t.combinations < minCombos) minCombos = t.combinations;
            if (t.combinations > maxCombos) maxCombos = t.combinations;
        }
        System.out.printf("Tasks: %d | Total combinations: %d | Per task: %d-%d (ratio %.1fx)%n",
            tasks.size(), totalCombos, minCombos, maxCombos,
            (double) maxCombos / Math.max(minCombos, 1));
    }
}
