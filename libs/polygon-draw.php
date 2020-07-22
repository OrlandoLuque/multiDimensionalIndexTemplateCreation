<?php
/*------------------------------------------------------------------------------
** File:        polyExample.php
** Description: Demo's the capability of the polygon class.
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
** 1.0  08/25/2005      Initial Release.
** 1.1  09/04/2005      Removed old html documentation from file.
**                      Added software license language to header comments.
**                      Added example code for new methods Move(),Rotate(),bRect()
**                      and isPolyInside().
**                      Added newImage() function to make the code a bit neater.
** 1.2  09/07/2005      Minor fix to v1p2/../v1p6%20unreleased/polygon.php - no change to this file
** 1.3  04/16/2006      Minor fix to v1p3/../v1p6%20unreleased/polygon.php
**                      Added example 6 - a test for the degenerate case where
**                      a vertex falls exactly on a line of the other polygon.
** 1.4  03/19/2009      Added example 7 - show how the new isPolyInside,
**                      isPolyOutside & isPolyIntersect methods are used.
** 1.5  07/11/2009      Added example 8 - show how the isPolySelfIntersect
**                      method is used.
** 1.6  15/05/2010      Added some round statements in the drawPolyAt() method
**                      to cleanup how perturbed vertices were drawn.
**						Added new examples for scale & translate methods and
**						new examples showing the degenerate vertex issue
*/

//require('polygon.php');         // Where all the good stuff is

/*
** A simple function that draws the polygons onto an image to demo the class
**
** $x,$y .. are an offset that will be added to all coordinates of the polygon
** $i    .. an image created with the imagecreate function
** $p    .. A polygon Object to be drawn (could be a list of polygons)
** $col  .. An array of allocated colors for the image
** $c    .. Index to the colors array - i.e. the draw color
**
**   Real Angle    0   45   90  135  180  225  270  315 360
** imgarc Angle  360  315  270  225  180  135   90   45   0 (To draw real angle)
** Thus imagearc Angle = 360 - Real Angle
**
** If d == -1 the arc is Anti-Clockwise, d == 1 the arc is clockwise
**
** imagearc only draws clockwise arcs, so if we have an Anti-Clockwise arc we
** must reverse the order of start-angle and end-angle.
**
** images have their origin point (0,0) at the top left corner. However in
** real world math the origin is at the bottom left. This really only matters
** for arcs (determining clockwise or anti-clockwise). Thus the points in
** the polygon are assumed to exist in real world coordinates. Thus they
** are 'inverted' in the y-axis to plot them on the image.
*/
function drawPolyAt($x, $y, &$i, $p, &$col, $c) {
    if ($i) {
        $sy = imagesy($i);
    }      // Determine the height of the image in pixels
    // All $y coords will be subtracted from this
    if ($p) // If a polygon exists
    {
        do              // For all polygons in the list
        {
            /** @var Vertex $v */
            /** @var Vertex $n */
            $v = $p->getFirst();           // get the first vertex of the first polygon
            do                                                      // For all vertices in this polygon
            {
                $n =& $v->Next();               // Get the next vertex
                if ($v->d() == 0)               // Check is this is an ARc segment
                { // It is a line
                    imageLine($i, round($x + $v->X()), round($sy - ($y + $v->Y()))
                                , round($x + $n->X()), round($sy - ($y + $n->Y()))
                                , $col[$c]);        // Draw a line vertex to vertex
                } else { // It is an Arc
                    $s = 360 - rad2deg($p->angle($v->Xc(), $v->Yc(), $v->X(), $v->Y()));   // Calc start angle
                    $e = 360 - rad2deg($p->angle($v->Xc(), $v->Yc(), $n->X(), $n->Y()));   // Calc end angle
                    $dia = round(2 * $p->dist($v->X(), $v->Y(), $v->Xc(), $v->Yc()));
                    if ($v->d() == -1)      // Clockwise
                    {
                        imagearc($i, round($x + $v->Xc()), round($sy - ($y + $v->Yc())), $dia, $dia, $s, $e, $col[$c]);
                    } else                    // Anti-Clockwise
                    {
                        imagearc($i, round($x + $v->Xc()), round($sy - ($y + $v->Yc())), $dia, $dia, $e, $s, $col[$c]);
                    }
                }
                $v =& $n;                       // Move to next vertex
            } while ($v->id() != $p->first->id());    // Keep drawing until the last vertex
            $p = $p->NextPoly();                   // Get the next polygon in the list
        } while ($p);
    }     // Keep drawing polygons as long as they exist
}

function directDrawPolyAt($x, $y, &$i, $p, &$col, $c) {
    if ($i) {
        $sy = imagesy($i);
    }      // Determine the height of the image in pixels
    // All $y coords will be subtracted from this
    if ($p) // If a polygon exists
    {
        do              // For all polygons in the list
        {
            $v = $p->getFirst();           // get the first vertex of the first polygon
            do                                                      // For all vertices in this polygon
            {
                $n =& $v->Next();               // Get the next vertex
                if ($v->d() == 0)               // Check is this is an ARc segment
                { // It is a line
                    $r = imageLine($i, $x + $v->X(), $y + $v->Y(), $x + $n->X(), $y + $n->Y(), $col[$c]);        // Draw a line vertex to vertex
                } else { // It is an Arc
                    $s = rad2deg($p->angle($v->Xc(), $v->Yc(), $v->X(), $v->Y()));   // Calc start angle
                    $e = rad2deg($p->angle($v->Xc(), $v->Yc(), $n->X(), $n->Y()));   // Calc end angle
                    $dia = round(2 * $p->dist($v->X(), $v->Y(), $v->Xc(), $v->Yc()));
                    if ($v->d() == -1)      // Clockwise
                    {
                        $r = imagearc($i, $x + $v->Xc(), $y + $v->Yc(), $dia, $dia, $e, $s, $col[$c]);
                    } else                    // Anti-Clockwise
                    {
                        $r = imagearc($i, $x + $v->Xc(), $y + $v->Yc(), $dia, $dia, $s, $e, $col[$c]);
                    }
                }
                $v =& $n;                       // Move to next vertex
            } while ($v->id() != $p->first->id());    // Keep drawing until the last vertex
            $p = $p->NextPoly();                   // Get the next polygon in the list
        } while ($p);
    }     // Keep drawing polygons as long as they exist
}

/*
** Function to create an image and allocate a color table to it
*/
/** @var resource $i */
function newImage($width, $height, &$i, &$col) {
    if ($i) {
        imagedestroy($i);
    }                               // Delete any old image
    $i = imagecreate($width, $height);      // New image to draw our polygons
    $col["wht"] = imagecolorallocate($i, 255, 255, 255);   // Allocate some colors
    $col["blk"] = imagecolorallocate($i, 0, 0, 0);
    $col["red"] = imagecolorallocate($i, 255, 0, 0);
    $col["blu"] = imagecolorallocate($i, 0, 0, 255);
    $col["grn"] = imagecolorallocate($i, 0, 192, 0);
    $col["pur"] = imagecolorallocate($i, 255, 0, 255);
    $col["yel"] = imagecolorallocate($i, 255, 255, 0);
    $col["ora"] = imagecolorallocate($i, 240, 69, 0);
    $col["lgra"] = imagecolorallocate($i, 240, 240, 240);
    $col["dgra"] = imagecolorallocate($i, 120, 120, 120);
}

/**
 * @param int $cellOffsetX
 * @param int $cellOffsetY
 * @param Polygon $cell
 * @param resource $im
 * @param string $fgColor
 * @param string $bgColor
 * @param array $colors
 * @return void
 */
function paintCell($cellOffsetX, $cellOffsetY, $cell, &$im, $fgColor, $bgColor, $colors): void {
    directDrawPolyAt(-$cellOffsetX, -$cellOffsetY, $im, $cell, $colors, $fgColor);
    imagefill($im, ($cell->x_max + $cell->x_min) / 2 - $cellOffsetX
        , ($cell->y_max + $cell->y_min) / 2 - $cellOffsetY
        , $colors[$bgColor]);
}

?>
