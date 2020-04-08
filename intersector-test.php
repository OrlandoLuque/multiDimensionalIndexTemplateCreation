<?php
//source: https://stackoverflow.com/questions/2255842/detecting-coincident-subset-of-two-coincident-line-segments/2255848#2255848

IntersectTest::Main();

class IntersectTest {
    public static function PrintPoints(array $pf): void {
        if ($pf == null || count($pf) < 1) {
            echo "Doesn't intersect";
        } else if (count($pf) == 1) {
            echo $pf[0];
        } else if (count($pf) == 2) {
            echo $pf[0] . " -- " . $pf[1];
        }
    }

    /**
     * @param Vertex $a1
     * @param Vertex $a2
     * @param Vertex $b1
     * @param Vertex $b2
     * @throws Exception
     */
    public static function TestIntersect(Vertex $a1, Vertex $a2, Vertex $b1, Vertex $b2): void {
        echo "----------------------------------------------------------";
        echo "Does      " . $a1 . " -- " . $a2;
        echo "intersect " . $b1 . " -- " . $b2 . " and if so, where?";
        echo "";
        /** @var array $result */
        $result = Intersector::intersection($a1, $a2, $b1, $b2);
        IntersectTest::PrintPoints($result);
    }

    public static function Main(): void {
        echo "----------------------------------------------------------";
        echo "line segments intersect";
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(100, 100),
            new Vertex(100, 0),
            new Vertex(0, 100));
        IntersectTest::TestIntersect(new Vertex(5, 17),
            new Vertex(100, 100),
            new Vertex(100, 29),
            new Vertex(8, 100));
        echo "----------------------------------------------------------";
        echo "";

        echo "----------------------------------------------------------";
        echo "just touching points and lines cross";
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(25, 25),
            new Vertex(25, 25),
            new Vertex(100, 75));
        echo "----------------------------------------------------------";
        echo "";

        echo "----------------------------------------------------------";
        echo "parallel";
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(0, 100),
            new Vertex(100, 0),
            new Vertex(100, 100));
        echo "----------------------------------------------------------";
        echo "";

        echo "----";
        echo "lines cross but segments don't intersect";
        IntersectTest::TestIntersect(new Vertex(50, 50),
            new Vertex(100, 100),
            new Vertex(0, 25),
            new Vertex(25, 0));
        echo "----------------------------------------------------------";
        echo "";

        echo "----------------------------------------------------------";
        echo "coincident but do not overlap!";
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(25, 25),
            new Vertex(75, 75),
            new Vertex(100, 100));
        echo "----------------------------------------------------------";
        echo "";

        echo "----------------------------------------------------------";
        echo "touching points and coincident!";
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(25, 25),
            new Vertex(25, 25),
            new Vertex(100, 100));
        echo "----------------------------------------------------------";
        echo "";

        echo "----------------------------------------------------------";
        echo "overlap/coincident";
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(75, 75),
            new Vertex(25, 25),
            new Vertex(100, 100));
        IntersectTest::TestIntersect(new Vertex(0, 0),
            new Vertex(100, 100),
            new Vertex(0, 0),
            new Vertex(100, 100));
        echo "----------------------------------------------------------";
        echo "";

        //while (!System.Console.KeyAvailable) { }
    }

}
