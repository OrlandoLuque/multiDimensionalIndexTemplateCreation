<?php
//source: https://stackoverflow.com/questions/2255842/detecting-coincident-subset-of-two-coincident-line-segments/2255848#2255848

// port of this JavaScript code with some changes:
//   http://www.kevlindev.com/gui/math/intersection/Intersection.js
// found here:
//   http://stackoverflow.com/questions/563198/how-do-you-detect-where-two-line-segments-intersect/563240#563240

require_once('vertex.php');

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
        return $d < Intersector::$MyEpsilon;
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
}
