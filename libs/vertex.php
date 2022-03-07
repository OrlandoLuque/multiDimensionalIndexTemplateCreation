<?php
/*------------------------------------------------------------------------------
** File:        vertex.php
** Description: PHP class for a polygon vertex. Used as the base object to
**              build a class of polygons.
** Version:     1.6
** Author:      Brenor Brophy
** Email:       brenor dot brophy at gmail dot com
** Homepage:    www.brenorbrophy.com
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2005-2010 BRENOR BROPHY
**
** The source code included in this package is free software; you can
** redistribute it and/or modify it under the terms of the GNU General Public
** License as published by the Free Software Foundation. This license can be
** read at:
**
** http://www.opensource.org/licenses/gpl-license.php
**
** This program is distributed in the hope that it will be useful, but WITHOUT
** ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
** FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
**------------------------------------------------------------------------------
**
** Based on the paper "Efficient Clipping of Arbitary Polygons" by Gunther
** Greiner (greiner at informatik dot uni-erlangen dot de) and Kai Hormann
** (hormann at informatik dot tu-clausthal dot de), ACM Transactions on Graphics
** 1998;17(2):71-83.
**
** Available at:
**
**      http://www2.in.tu-clausthal.de/~hormann/papers/Greiner.1998.ECO.pdf
**
** Another useful site describing the algorithm and with some example
** C code by Ionel Daniel Stroe is at:
**
**              http://davis.wpi.edu/~matt/courses/clipping/
**
** The algorithm is extended by Brenor Brophy to allow polygons with
** arcs between vertices.
**
** Rev History
** -----------------------------------------------------------------------------
** 1.0  08/25/2005      Initial Release
** 1.1  09/04/2005      Added software license language to header comments
** 1.2  09/07/2005      Minor fix to polygon.php - no change to this file
** 1.3  04/16/2006      Minor fix to polygon.php - no change to this file
** 1.4  03/19/2009      Minor change to comments in this file. Significant
**                      change to polygon.php
** 1.5  07/16/2009      No change to this file
** 1.6  15/05/2010      No change to this file
*/

//namespace OSM\Tools;

class Segment
{
    /*------------------------------------------------------------------------------
    ** This class contains the information about the segments between vetrices. In
    ** the original algorithm these were just lines. In this extended form they
    ** may also be arcs. By creating a separate object for the segment and then
    ** referencing to it forward & backward from the two vertices it links it is
    ** easy to track in various directions through the polygon linked list.
    */
    public $xc;
    public $yc;               // Coordinates of the center of the arc
    public $d;                         // Direction of the arc, -1 = clockwise, +1 = anti-clockwise,
                                    // A 0 indicates this is a line
    /*
    ** Construct a segment
    */
    public function __construct($xc=0, $yc=0, $d=0)
    {
        $this->xc = $xc;
        $this->yc = $yc;
        $this->d = $d;
    }
    /*
    ** Return the contents of a segment
    */
    public function Xc()
    {
        return $this->xc ;
    }
    public function Yc()
    {
        return $this->yc ;
    }
    public function d()
    {
        return $this->d ;
    }
    /*
    ** Set Xc/Yc
    */
    public function setXc($xc)
    {
        $this->xc = $xc;
    }
    public function setYc($yc)
    {
        $this->yc = $yc;
    }
} // end of class segment

class Vertex
{
    /*------------------------------------------------------------------------------
    ** This class is almost exactly as described in the paper by Gunter/Greiner
    ** with some minor additions for segments. Basically it is a node in a doubly
    ** linked list with a few extra control variables used by the algorithm
    ** for boolean operations. The only methods in the class are used to encapsulate
    ** the properties.
    */
    public $x;
    public $y;                 // Coordinates of the vertex
    public $nextV;
    public $prevV;         // References to the next and previous vetices in the polygon
    public $nSeg;
    public $pSeg;           // References to next & previous segments
    public $nextPoly;              // Reference to another polygon in a list
    public $intersect;             // TRUE if vertex is an intersection (with another polgon)
    public $neighbor;              // Ref to the corresponding intersection vertex in another polygon
    public $alpha;                 // Intersection points relative distance from previous vertex
    public $entry;                 // TRUE if intersection is an entry point to another polygon
    // FALSE if it is an exit point
    public $checked;               // Boolean - TRUE if vertex has been checked
    public $id;                    // A random ID assigned to make the vertex unique

    /*
    ** Construct a vertex
    */
    public function __construct(
        $x,
        $y,
        $xc=0,
        $yc=0,
        $d=0,
        $nextV=null,
        $prevV=null,
        $nextPoly=null,
        $intersect = false,
        $neighbor=null,
        $alpha=0,
        $entry=true,
        $checked=false
    )
    {
        $this->x = $x;
        $this->y = $y;
        $this->nextV = $nextV;
        $this->prevV = $prevV;
        $this->nextPoly = $nextPoly;
        $this->intersect = $intersect;
        $this->neighbor = $neighbor;
        $this->alpha = $alpha;
        $this->entry = $entry;
        $this->checked = $checked;
        $this->id = mt_rand(0, 1000000);
        /*
        ** Create a new Segment and set a reference to it. Segments are always
        ** placed after the vertex
        */
        $this->nSeg = new Segment($xc, $yc, $d);
        $this->pSeg = null;
    }
    /*
    ** Get id
    */
    public function id()
    {
        return $this->id;
    }
    /*
    ** Get/Set x/y
    */
    public function X()
    {
        return $this->x;
    }
    public function setX($x)
    {
        $this->x = $x;
    }
    public function Y()
    {
        return $this->y;
    }
    public function setY($y)
    {
        $this->y = $y;
    }
    /*
    ** Return contents of a segment. Default is to always return the next
    ** segment, unless previous is specified. The special case is where
    ** the vertex is an intersection, in that case the contents of the
    ** neighbor vertex's next or prev segment is returned. Whether next
    ** or previous is returned depends upon the entry value of the vertex
    ** This method ensures that the correct segment data is returned when
    ** a result polygon is being constructed.
    **
    ** For $g Next == TRUE and Prev == FALSE
    */
    public function Xc($g = true)
    {
        if ($this->isIntersect()) {
            if ($this->neighbor->isEntry()) {
                return $this->neighbor->nSeg->Xc();
            } else {
                return $this->neighbor->pSeg->Xc();
            }
        } elseif ($g) {
            return $this->nSeg->Xc();
        } else {
            return $this->pSeg->Xc();
        }
    }
    public function Yc($g = true)
    {
        if ($this->isIntersect()) {
            if ($this->neighbor->isEntry()) {
                return $this->neighbor->nSeg->Yc();
            } else {
                return $this->neighbor->pSeg->Yc();
            }
        } elseif ($g) {
            return $this->nSeg->Yc();
        } else {
            return $this->pSeg->Yc();
        }
    }

    public function d($g = true)
    {
        if ($this->isIntersect()) {
            if ($this->neighbor->isEntry()) {
                return $this->neighbor->nSeg->d();
            } else {
                return (-1*$this->neighbor->pSeg->d());
            }
        } elseif ($g) {
            return $this->nSeg->d();
        } else {
            return (-1*$this->pSeg->d());
        }
    }
    /*
    ** Set Xc/Yc (Only for segment pointed to by Nseg)
    */
    public function setXc($xc)
    {
        $this->nSeg->setXc($xc);
    }
    public function setYc($yc)
    {
        $this->nSeg->setYc($yc);
    }
    /*
    ** Set/Get the reference to the next vertex
    */
    public function setNext($nextV)
    {
        $this->nextV = $nextV;
    }
    public function &Next(): Vertex
    {
        return $this->nextV;
    }
    /*
    ** Set/Get the reference to the previous vertex
    */
    public function setPrev($prevV)
    {
        $this->prevV = $prevV;
    }
    public function &Prev(): Vertex
    {
        return $this->prevV;
    }
    /*
    ** Set/Get the reference to the next segment
    */
    public function setNseg($nSeg)
    {
        $this->nSeg = $nSeg;
    }
    public function &Nseg()
    {
        return $this->nSeg;
    }
    /*
    ** Set/Get the reference to the previous segment
    */
    public function setPseg($pSeg)
    {
        $this->pSeg = $pSeg;
    }
    public function &Pseg()
    {
        return $this->pSeg;
    }
    /*
    ** Set/Get reference to the next Polygon
    */
    public function setNextPoly($nextPoly)
    {
        $this->nextPoly = $nextPoly;
    }
    public function &NextPoly()
    {
        return $this->nextPoly;
    }
    /*
    ** Set/Get reference to neighbor polygon
    */
    public function setNeighbor($neighbor)
    {
        $this->neighbor = $neighbor;
    }
    public function &Neighbor()
    {
        return $this->neighbor;
    }
    /*
    ** Get alpha
    */
    public function Alpha()
    {
        return $this->alpha;
    }
    /*
    ** Test for intersection
    */
    public function isIntersect()
    {
        return $this->intersect;
    }
    /*
    ** Set/Test for checked flag
    */
    public function setChecked($check = true)
    {
        $this->checked = $check;
        if ($this->neighbor && !$this->neighbor->isChecked()) {
            $this->neighbor->setChecked();
        }
    }
    public function isChecked()
    {
        return $this->checked;
    }
    /*
    ** Set/Test entry
    */
    public function setEntry($entry = true)
    {
        $this->entry = $entry;
    }
    public function isEntry()
    {
        return $this->entry;
    }
    /*
    ** Print Vertex used for debugging
    */
    public function print_vertex()
    {
        print("(".$this->x.")(".$this->y.") ");
        if ($this->nSeg->d() != 0) {
            print(" c(".$this->nSeg->Xc().")(".$this->nSeg->Yc().")(".$this->nSeg->d().") ");
        }
        if ($this->intersect) {
            print("Intersection with alpha=".$this->alpha." ");
            if ($this->entry) {
                print(" Entry");
            } else {
                print(" Exit");
            }
        }
        if ($this->checked) {
            print(" Checked");
        } else {
            print(" Unchecked");
        }
        print("<br>");
    }

    public function intersectVertex($v)
    {
        return ($this->x == $v->x && $this->y == $v->y);
    }

    public function isVerticalVertex()
    {
        /** @var Vertex $p */
        /** @var Vertex $n */
        /** @var Vertex $t */
        $prev = $this->prevV;
        $prevPost = $this;
        while ($this->isHorizontalLine($prev)) {
            $prevPost = $prev;
            $prev = $prev->prevV;
        }
        $previousDirection = $this->verticalDirection($prev, $prevPost);
        $direction = $this->verticalDirection($this);
        return ($previousDirection == 1 && $direction == 1)
             || ($previousDirection == -1 && $direction == -1)
             //|| ($previousDirection == 1 && $direction == 0)
             //|| ($previousDirection == -1 && $direction == 0)
             //|| ($previousDirection == 0 && $direction == 1)
             //|| ($previousDirection == 0 && $direction == -1)
             ;
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function toString()
    {
        $d = $this->d();
        if (0 === $d) {
            return "[{$this->x}, {$this->y}]";
        } else {
            return "[{$this->x}, {$this->y}] - c[{$this->Xc()}, {$this->Yc()}], {$d}";
        }
    }
    public function lineToString($v)
    {
        return "[{$this->toString()} -> {$v->toString()}]";
    }

    public function isInside(Vertex $v1, Vertex $v2)
    {
        return ($v1->x >= $this->x && $this->x >= $v2->x || $v1->x <= $this->x && $this->x <= $v2->x)
            && ($v1->y >= $this->y && $this->y >= $v2->y || $v1->y <= $this->y && $this->y <= $v2->y);
    }
    /**
     * @param Vertex $v
     * @return bool
     */
    public function equals($v)
    {
        return $this->x == $v->x && $this->y == $v->y;
    }
    /**
     * @param Vertex $v
     * @return bool
     */
    public function roughtlyEquals($v)
    {
        return abs($this->x - $v->x) < 0.001
            && abs($this->y - $v->y) < 0.001;
    }
    /**
     * @param int $x
     * @param int $y
     * @return bool
     */
    public function equalsXY($x, $y)
    {
        return $this->x == $x && $this->y == $y;
    }

    /**
     * @param Vertex $t
     * @return int
     */
    private function verticalDirection(Vertex $t, Vertex $pointToUseForArc = null): int
    {
        $td = $t->d();
        if ($td == 0) {
            $n = $t->nextV;
            if ($t->y < $n->y) {
                return 1;
            } elseif ($t->y > $n->y) {
                return -1;
            }
            return 0;
        } else {
            if (!empty($pointToUseForArc)) {
                $a = Polygon::angle(
                    $t->Xc(),
                    $t->Yc(),
                    $pointToUseForArc->x,
                    $pointToUseForArc->y
                );
            } else {
                $a = Polygon::angle($t->Xc(), $t->Yc(), $t->x, $t->y);
            }
            $cos = cos($a);
            if ((abs($cos) < 1e-10)) { //cos == 0
                //$cos = 0;
                $sin = sin($a);
                if (!empty($pointToUseForArc)) {
                    if ($t->nextV->equals($pointToUseForArc)) {
                        //si el punto de corte es en el vértice final, no hay que contar
                        // hacia donde se va, sino de donde se vino!
                        $cos = $sin * $td;
                    } else {
                        $cos = + $sin * $td;
                    }
                } else {
                    $cos = -$sin * $td;
                }
            }
            return ($cos > 0 ? 1 : -1) * $td;

            /*
                        $a += ($td > 0 ? pi() / 2 : -pi() / 2);
                        $sen = sin($a);
                        if ($sen == 0) {
                            $sen = sin($a + ($td > 0 ? 0.001 : -0.001));
                        }
                        if ($sen > 0) {
                            $direction = 1;
                        } else / *if ($sen < 1)* / {
                            $direction = -1;
                        };*/
        }
        //return $direction;
    }

    /**
      * @param Vertex $t
      * @return int
      */
    private function isHorizontalLine(Vertex $t): int
    {
        $td = $t->d();
        //$direction = 0;
        if ($td == 0
                && $t->y == $t->nextV->y) {
            return true;
        } else {
            return false;
        }
    }
} //end of class vertex
