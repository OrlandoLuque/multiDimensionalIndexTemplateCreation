import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.atomic.AtomicInteger;

/// Thread-safe template store (replaces Redis for in-memory mode)
public class TemplateStore {
    private final Map<String, Integer> templates = new HashMap<>();
    private final List<String> generations = new ArrayList<>();
    private final AtomicInteger counter = new AtomicInteger(1);

    /// Store template with 8-symmetry deduplication. Returns [templateId, symmetryOpIndex, isNew]
    public synchronized int[] storeDedup(byte[][] templateGrid, String genString) {
        List<byte[][]> transforms = Matrix.allTransforms(templateGrid);
        List<byte[]> hashes = new ArrayList<>(8);
        for (byte[][] t : transforms) {
            hashes.add(Matrix.binCode(t));
        }

        // Check if any variant already exists
        for (int i = 0; i < hashes.size(); i++) {
            String key = hexEncode(hashes.get(i));
            Integer existingId = templates.get(key);
            if (existingId != null) {
                String op = Matrix.TRANSFORM_NAMES[i];
                generations.add(genString + "->" + op + "->" + existingId);
                return new int[]{existingId, i, 0}; // not new
            }
        }

        // New template
        int id = counter.getAndIncrement();
        templates.put(hexEncode(hashes.get(0)), id);
        generations.add(genString + "->eq->" + id);
        return new int[]{id, 0, 1}; // is new
    }

    public int templateCount() {
        return counter.get() - 1;
    }

    public synchronized int generationCount() {
        return generations.size();
    }

    private static String hexEncode(byte[] data) {
        StringBuilder sb = new StringBuilder(data.length * 2);
        for (byte b : data) {
            sb.append(String.format("%02x", b & 0xFF));
        }
        return sb.toString();
    }
}
