public class SubTask {
    public int id;
    public String polyName;
    public Polygon poly;
    public double scale;
    public long gridSize;
    public int angleStart; // index into angles array
    public int angleEnd;   // exclusive
    public long combinations;

    public SubTask(int id, String polyName, Polygon poly, double scale, long gridSize,
                   int angleStart, int angleEnd, long combinations) {
        this.id = id;
        this.polyName = polyName;
        this.poly = poly;
        this.scale = scale;
        this.gridSize = gridSize;
        this.angleStart = angleStart;
        this.angleEnd = angleEnd;
        this.combinations = combinations;
    }
}
