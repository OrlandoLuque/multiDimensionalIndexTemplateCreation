<?php
//sources:
//  https://stackoverflow.com/questions/2255842/detecting-coincident-subset-of-two-coincident-line-segments/2255848#2255848
//https://stackoverflow.com/questions/1073336/circle-line-segment-collision-detection-algorithm

// port of this JavaScript code with some changes:
//   http://www.kevlindev.com/gui/math/intersection/Intersection.js
// found here:
//   http://stackoverflow.com/questions/563198/how-do-you-detect-where-two-line-segments-intersect/563240#563240

//require_once('vertex.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'vertex.php');

class Intersector {
    /** @var float */
    static $MyEpsilon = 0.00001;

    private static function OverlapIntervals($ub1, $ub2): array {
        $l = min($ub1, $ub2);
        $r = max($ub1, $ub2);
        $A = max(0, $l);
        $B = min(1, $r);
        if ($A > $B) {// no intersection
            return [];
        } else if ($A == $B) {
            return [$A];
        } else { // if (A < B)
            return [$A, $B];
        }
    }

    // IMPORTANT: $a1 and $a2 cannot be the same, e.g. $a1--$a2 is a true segment, not a point
    // $b1/$b2 may be the same ($b1--$b2 is a point)
    /**
     * @param Vertex $a1
     * @param Vertex $a2
     * @param Vertex $b1
     * @param Vertex $b2
     * @return array
     */
    private static function oneD_Intersection(Vertex $a1, Vertex $a2, Vertex $b1, Vertex $b2): array {
        //float ua1 = 0.0f; // by definition
        //float ua2 = 1.0f; // by definition
        /** @var float $ub1 */
        /** @var float $ub2 */
        //$ub1 = 0; $ub2 = 0;

        /** @var float $denomx */
        /** @var float $denomy */
        $denomx = $a2->X() - $a1->X();
        $denomy = $a2->Y() - $a1->Y();

        if (abs($denomx) > abs($denomy)) {
            $ub1 = ($b1->X() - $a1->X()) / $denomx;
            $ub2 = ($b2->X() - $a1->X()) / $denomx;
        } else {
            $ub1 = ($b1->Y() - $a1->Y()) / $denomy;
            $ub2 = ($b2->Y() - $a1->Y()) / $denomy;
        }

        $ret = [];//new List<Vertex>();

        /** @var array $interval */
        $interval = Intersector::OverlapIntervals($ub1, $ub2);
        /** @var float $f */
        foreach ($interval as $f) {
            /** @var float $x */
            /** @var float $y */
            $x = $a2->X() * $f + $a1->X() * (1.0 - $f);
            $y = $a2->Y() * $f + $a1->Y() * (1.0 - $f);
            /** @var Vertex $p */
            $p = new Vertex($x, $y);
            $ret[] = $p;
        }
        return $ret;
    }

    /**
     * @param Vertex $p
     * @param Vertex $a1
     * @param Vertex $a2
     * @return bool
     * @throws Exception
     */
    private static function pointOnLine(Vertex $p, Vertex $a1, Vertex $a2): bool {
        /** @var float $dummyU */
        $dummyU = 0.0;
        $d = Intersector::distFromSeg($p, $a1, $a2, Intersector::$MyEpsilon, $dummyU);
        return $d < Intersector::$MyEpsilon
            && ($p->x >= $a1->x && $p->x <= $a2->x || $p->x >= $a2->x && $p->x <= $a1->x)
            && ($p->y >= $a1->y && $p->y <= $a2->y || $p->y >= $a2->y && $p->y <= $a1->y);
    }

    /**
     * @param Vertex $p
     * @param Vertex $q0
     * @param Vertex $q1
     * @param double $radius
     * @param float $u
     * @return double
     * @throws Exception
     */
    private static function distFromSeg(Vertex $p, Vertex $q0, Vertex $q1, float $radius, float &$u): float {
        // formula here:
        //http://mathworld.wolfram.com/Point-LineDistance2-Dimensional.html
        // where x0,y0 = $p
        //       x1,y1 = $q0
        //       x2,y2 = $q1
        /** @var double $dx21 */
        /** @var double $dy21 */
        /** @var double $dx10 */
        /** @var double $dy10 */
        /** @var double $dx21 */
        $dx21 = $q1->X() - $q0->X();
        $dy21 = $q1->Y() - $q0->Y();
        $dx10 = $q0->X() - $p->X();
        $dy10 = $q0->Y() - $p->Y();
        $segLength = sqrt($dx21 * $dx21 + $dy21 * $dy21);
        if ($segLength < Intersector::$MyEpsilon) {
            throw new Exception("Expected line segment, not point.");
        }
        /** @var double $num */
        $num = abs($dx21 * $dy10 - $dx10 * $dy21);
        /** @var double $d */
        $d = $num / $segLength;
        return $d;
    }

    /** this is the general case. Really really general
     * @param Vertex $a1
     * @param Vertex $a2
     * @param Vertex $b1
     * @param Vertex $b2
     * @return array
     * @throws Exception
     */
    public static function intersection(Vertex $a1, Vertex $a2, Vertex $b1, Vertex $b2): array {
        if ($a1->equals($a2) && $b1->equals($b2)) {
            // both "segments" are points, return either point
            if ($a1->equals($b1)) {
                return [$a1];
            } else // both "segments" are different points, return empty set
            {
                return [];
            }
        } else if ($b1->equals($b2)) // b is a point, a is a segment
        {
            if (Intersector::pointOnLine($b1, $a1, $a2)) {
                return [$b1];
            } else {
                return [];
            }
        } else if ($a1->equals($a2)) // a is a point, b is a segment
        {
            if (Intersector::pointOnLine($a1, $b1, $b2)) {
                return [$a1];
            } else {
                return [];
            }
        }

        // at this point we know both a and b are actual segments

        /** @var float $ua_t */
        /** @var float $ub_t */
        /** @var float $u_b */
        $ua_t = ($b2->X() - $b1->X()) * ($a1->Y() - $b1->Y()) - ($b2->Y() - $b1->Y()) * ($a1->X() - $b1->X());
        $ub_t = ($a2->X() - $a1->X()) * ($a1->Y() - $b1->Y()) - ($a2->Y() - $a1->Y()) * ($a1->X() - $b1->X());
        $u_b = ($b2->Y() - $b1->Y()) * ($a2->X() - $a1->X()) - ($b2->X() - $b1->X()) * ($a2->Y() - $a1->Y());

        // Infinite lines intersect somewhere
        if (!(-Intersector::$MyEpsilon < $u_b && $u_b < Intersector::$MyEpsilon)) {  // e.g. $u_b != 0.0
            /** @var float $ua */
            /** @var float $ub */
            $ua = $ua_t / $u_b;
            $ub = $ub_t / $u_b;
            if (0.0 <= $ua && $ua <= 1.0 && 0.0 <= $ub && $ub <= 1.0) {
                // Intersection
                return [
                    new Vertex($a1->X() + $ua * ($a2->X() - $a1->X()),
                        $a1->Y() + $ua * ($a2->Y() - $a1->Y()))
                ];
            } else {
                // No Intersection
                return [];
            }
        } else { // lines (not just segments) are parallel or the same line
            // Coincident
            // find the common overlapping section of the lines
            // first find the distance (squared) from one point ($a1) to each point
            if ((-Intersector::$MyEpsilon < $ua_t && $ua_t < Intersector::$MyEpsilon)
                || (-Intersector::$MyEpsilon < $ub_t && $ub_t < Intersector::$MyEpsilon)) {
                if ($a1->equals($a2)) { // danger!
                    return Intersector::oneD_Intersection($b1, $b2, $a1, $a2);
                } else { // safe
                    return Intersector::oneD_Intersection($a1, $a2, $b1, $b2);
                }
            } else {
                // Parallel
                return [];
            }
        }
    }

    public static function lineCircleIntersection(Vertex $l1, Vertex $l2, Vertex $c, float $radius) {
        // Find the points of intersection.)
        //float $dx, $dy, $A, $B, $C, $det, $t;

        $dx = $l2->x - $l1->x;
        $dy = $l2->y - $l1->y;

        $A = $dx * $dx + $dy * $dy;
        $B = 2 * ($dx * ($l1->x - $c->x) + $dy * ($l1->y - $c->y));
        $C = ($l1->x - $c->x) * ($l1->x - $c->x) +
            ($l1->y - $c->y) * ($l1->y - $c->y) -
            $radius * $radius;

        $det = $B * $B - 4 * $A * $C;
        if (($A <= 0.0000001) || ($det < 0)) {
            // No real solutions.
            return [];
        } else if ($det == 0) {
            // One solution.
            $t = -$B / (2 * $A);
            $intersection1 =
                new Vertex($l1->x + $t * $dx, $l1->y + $t * $dy);
            return [$intersection1];
        } else {
            // Two solutions.
            $t = (float)((-$B + sqrt($det)) / (2 * $A));
            $intersection1 =
                new Vertex($l1->x + $t * $dx, $l1->y + $t * $dy);
            $t = (float)((-$B - sqrt($det)) / (2 * $A));
            $intersection2 =
                new Vertex($l1->x + $t * $dx, $l1->y + $t * $dy);
            return [$intersection1, $intersection2];
        }
    }
    public static function lineArcIntersection(Vertex $l1, Vertex $l2, Vertex $a1, Vertex $a2, $ignoreLineArcTouch): array {
        $xc = $a1->Xc();
        $yc = $a1->Yc();
        $xs = $a1->X();
        $ys = $a1->Y();
        $type = $a1->d();
        $circleIntersections = Intersector::lineCircleIntersection($l1, $l2
            , new Vertex($a1->Xc(), $a1->Yc()), Intersector::dist($xc, $yc, $xs, $ys));
        $touch = count($circleIntersections) == 1
            && !$a1->roughtlyEquals($circleIntersections[0])
            && !$a2->roughtlyEquals($circleIntersections[0])
        ;
        if ($touch && $ignoreLineArcTouch) {
            return [];
        }
        $arcAngle1 = Intersector::angle($xc, $yc, $a1->x, $a1->y);
        $arcAngle2 = Intersector::angle($xc, $yc, $a2->x, $a2->y);
        $result = [];
        if ($type == -1) { // clock-wise
            $t = $arcAngle1;
            $arcAngle1 = $arcAngle2;
            $arcAngle2 = $t;
        }
        foreach ($circleIntersections as $intersection) {
            if ($intersection->roughtlyEquals($a1)) {
                $intersection = $a1;
            }
            if ($intersection->roughtlyEquals($a2)) {
                $intersection = $a2;
            }
            if ($intersection->isInside($l1, $l2)) {
                $intersectionAngle = Intersector::angle($xc, $yc, $intersection->x, $intersection->y);
                if ($arcAngle2 >= $arcAngle1) {
                    if ($arcAngle2 >= $intersectionAngle and $intersectionAngle >= $arcAngle1) {
                        $result[] = $intersection;
                    }
                } else {
                    if ($intersectionAngle <= $arcAngle2 or $intersectionAngle >= $arcAngle1) {
                        $result[] = $intersection;
                    }
                }
            }
        }
        return $result;
    }

    /*
     * * Return the distance between two points
     */
    public static function dist($x1, $y1, $x2, $y2) {
        return sqrt(($x1 - $x2) * ($x1 - $x2) + ($y1 - $y2) * ($y1 - $y2));
    }
    /*
     * * Calculate the angle between 2 points, where Xc,Yc is the center of a circle
     * * and x,y is a point on its circumference. All angles are relative to
     * * the 3 O'Clock position. Result returned in radians
     */

    public static function angle($xc, $yc, $x1, $y1) {
        $d = Intersector::dist($xc, $yc, $x1, $y1); // calc distance between two points
        if ($d != 0) {
            if (asin(($y1 - $yc) / $d) >= 0) {
                $a1 = acos(($x1 - $xc) / $d);
            } else {
                $a1 = 2 * pi() - acos(($x1 - $xc) / $d);
            }
        } else {
            $a1 = 0;
        }
        return $a1;
    }
}
