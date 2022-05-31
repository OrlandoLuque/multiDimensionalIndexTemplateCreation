<?php

//source: https://stackoverflow.com/questions/2255842/detecting-coincident-subset-of-two-coincident-line-segments/2255848#2255848

//require_once(__DIR__ . DIRECTORY_SEPARATOR . 'intersector.php');
require_once('../libs/intersector.php');

IntersectTest::Main();
class IntersectTest
{
    public static function PrintPoints(array $pf): void
    {
        if ($pf == null || count($pf) < 1) {
            echo "Doesn't intersect";
        } elseif (count($pf) == 1) {
            echo $pf[0];
        } elseif (count($pf) == 2) {
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
    public static function TestIntersect(Vertex $a1, Vertex $a2, Vertex $b1, Vertex $b2): void
    {
        echo "----------------------------------------------------------\n";
        echo "Does " . $a1 . " -- " . $a2;
        echo " intersect " . $b1 . " -- " . $b2 . " and if so, where?\n";
        echo "\n";
        /** @var array $result */
        $result = Intersector::intersection($a1, $a2, $b1, $b2);
        IntersectTest::PrintPoints($result);
        echo "\n";
    }

    /**
     * @throws Exception
     */
    public static function Main(): void
    {
        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "line segments intersect\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(100, 100),
            new Vertex(100, 0),
            new Vertex(0, 100)
        );
        IntersectTest::TestIntersect(
            new Vertex(5, 17),
            new Vertex(100, 100),
            new Vertex(100, 29),
            new Vertex(8, 100)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "just touching points and lines cross\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(25, 25),
            new Vertex(25, 25),
            new Vertex(100, 75)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "parallel\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(0, 100),
            new Vertex(100, 0),
            new Vertex(100, 100)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        //echo "----\n";
        echo "lines cross but segments don't intersect\n";
        IntersectTest::TestIntersect(
            new Vertex(50, 50),
            new Vertex(100, 100),
            new Vertex(0, 25),
            new Vertex(25, 0)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "coincident but do not overlap!\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(25, 25),
            new Vertex(75, 75),
            new Vertex(100, 100)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "touching points and coincident!\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(25, 25),
            new Vertex(25, 25),
            new Vertex(100, 100)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "overlap/coincident\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(75, 75),
            new Vertex(25, 25),
            new Vertex(100, 100)
        );
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(100, 100),
            new Vertex(0, 0),
            new Vertex(100, 100)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "one line over another\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(100, 0),
            new Vertex(50, 0),
            new Vertex(150, 0)
        );
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(0, 100),
            new Vertex(0, 50),
            new Vertex(0, 150)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";

        echo "----------------------------------------------------------\n";
        echo "----------------------------------------------------------\n";
        echo "one line (in point format) over another\n";
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(100, 0),
            new Vertex(50, 0),
            new Vertex(50, 0)
        );
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(0, 100),
            new Vertex(0, 50),
            new Vertex(0, 50)
        );
        IntersectTest::TestIntersect(
            new Vertex(0, 0),
            new Vertex(100, 100),
            new Vertex(50, 50),
            new Vertex(50, 50)
        );
        echo "----------------------------------------------------------\n";
        echo "\n";
        //while (!System.Console.KeyAvailable) { }
    }
}
