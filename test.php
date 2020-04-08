<?php
// https://www.php.net/manual/en/intro.bc.php
$num1 = 0; // (string) 0 => '0'
$num2 = -0.000005; // (string) -0.000005 => '-5.05E-6'
//echo bcadd($num1, $num2, 6); // => '0.000000'

setlocale(LC_NUMERIC, 'de_DE'); // uses a decimal comma
$num2 = 1.2; // (string) 1.2 => '1,2'
//echo bcsub($num1, $num2, 1); // => '0.0'
//echo '\n/n ------- ' . bcdiv(1, 3, 100);

require_once ('polygon.php');
require_once ('polygon-draw.php');

function getGridsFromSupportedSizes($gridSupportedSizes, $horizontal = true, $vertical = true) {
    $r = [];
    foreach ($gridSupportedSizes as $val) {
        $r[] = [$val, $val];
        if ($horizontal) {
            $r[] = [$val * 2, $val];
        }
        if ($vertical) {
            $r[] = [$val, $val * 2];
        }
    }
    return $r;
}
function getDropPolygonWithDimensions($radius, $length) {
    $dropPoly = new polygon();        // Create a new polygon and add some vertices to it
    $dropPoly->addv(-$radius,$length,0,$length,-1);        // Arc with center 60,90 Clockwise
    $dropPoly->addv($radius, $length);
    $dropPoly->addv(0, 0);
    return $dropPoly;
}
function getCircleWithRadius($radius) {
    $circlePoly = new polygon();        // Create a new polygon and add some vertices to it
    $circlePoly->addv(  0,-$radius,0,0,-$radius);        // Arc with center 60,90 Clockwise
    $circlePoly->addv(  0, $radius,0,0,-$radius);
}
function getSquarePolygonWithDimensions($sideLength) {
    $sideLength /= 2;
    /*$boxPoly = new polygon();
    $boxPoly->addv(-$sideLength, -$sideLength);
    $boxPoly->addv($sideLength, -$sideLength);
    $boxPoly->addv($sideLength, $sideLength);
    $boxPoly->addv(-$sideLength, $sideLength);*/
    return getSquarePolyFromXYXY(-$sideLength, -$sideLength, $sideLength, $sideLength);
}
function getSquarePolyFromXYXY($sx, $sy, $ex, $ey){
    $boxPoly = new polygon();
    $boxPoly->addv( $sx, $sy);
    $boxPoly->addv( $ex, $sy);
    $boxPoly->addv( $ex, $ey);
    $boxPoly->addv( $sx, $ey);
    return $boxPoly;
}
function getAnglesToTest($step) {
    $r = [];
    for ($i = 0; $i < 360; $i += $step) {
        $r[] = $i * pi() / 180;
    }
    return $r;
}

/**
 * @param polygon $polygon
 * @param $angle
 */
function getRotatedPolygonCopy($polygon, $angle) {
    /** @var polygon $r */
    $r = $polygon->copy_poly();
    $r->rotate(0, 0, $angle);
    return $r;
}
/**
 * @param polygon $polygon
 * @param $angle
 */
function getScalatedPolygonCopy($polygon, $xScale, $yScale) {
    /** @var polygon $r */
    $r = $polygon->copy_poly();
    $r->scale($xScale, $yScale);
    return $r;
}

$v1 = new Vertex(-1, 0);
$v2 = new Vertex(1, 0);
$v3 = new Vertex(0, -1);
$v4 = new Vertex(0, 1);
$v5 = new Vertex(0, 0);
$r = Polygon::vertexIntsLine($v5, $v3, $v4); // |
$r = Polygon::vertexIntsLine($v5, $v1, $v2); // -
$r = Polygon::ints($v5, $v5, $v3, $v4, $n, $ix, $iy, $alphaP, $alphaQ);

$polyA = new polygon();        // Create a new polygon and add some vertices to it
$polyA->addv( 0,0);
$polyA->addv( 0,1);
$polyA->addv(1,1);
$polyA->addv( 1, 0);

$polyB = new polygon;          // Create a second polygon with some more points
$polyB->addv( 0,0);
$polyB->addv( 0,2);
$polyB->addv(2,2);
$polyB->addv( 2, 0);

$r = $polyA->isPolyInside($polyB);

define("OUT", 0);
define("IN", 2);
define("MAYBE", 1);
$polygonScales = [32, 64, 128, 256, 512, 1024];
$gridSupportedSizes = [16, 32, 64, 128, 256, 512];
$grids = getGridsFromSupportedSizes($gridSupportedSizes);


$circlePoly = getCircleWithRadius(1);
$boxPoly = getSquarePolygonWithDimensions(1);        // Create a new polygon and add some vertices to it
$dropPoly = getDropPolygonWithDimensions(1, 6);
$polys = [$boxPoly, $circlePoly, $dropPoly];
$angles = getAnglesToTest(0.5);

//$bBoxA = $polyA->bRect();
//$polyA->scale();
$templates = [];
$hashToTemplatesIdDictionary = [];
$idToTemplatesDictionary = [];
foreach ($polys as $originalPoly) {
    foreach ($polygonScales as $polygonScale) {
        $scalatedPoly = getScalatedPolygonCopy($originalPoly, $polygonScale, $polygonScale);
        foreach ($grids as $gridDimensions) {
            $gridX = $gridDimensions[0];
            $gridY = $gridDimensions[1];
            foreach ($angles as $angleIndex => $angle) {
                $poly = getRotatedPolygonCopy($scalatedPoly, $angle);
                $box = $poly->bRect();
                $boxVertex = [0 => [x => $box->first->x, y => $box->first->y]
                    , 1 => [x => $box->first->nextV->nextV->x, y => $box->first->nextV->nextV->y]];
                $template = [];
                $gridXRange = [floor($boxVertex[0][x] / $gridX), floor($boxVertex[1][x] / $gridX)];
                $gridYRange = [floor($boxVertex[0][y] / $gridY), floor($boxVertex[1][y] / $gridY)];
                $grid = getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);
                list($templateGrid, $templateHash) = getTemplateGrid($grid, $poly);
            }
        }
    }
}

/**
 * @param $grid
 * @param polygon $poly
 * @return array
 */
function getTemplateGrid($grid, $poly) {
    $r = []; $sr = '';
    foreach($grid as $ix => $row) {
        $r[$ix] = [];
        /** @var polygon $cell */
        foreach($row as $iy => $cell) {
            if ($cell->isPolyInside($poly)) {
                $intersectResult = IN;
            } else if ($poly->isPolyInside($cell)
                || $poly->isPolyIntersect($cell)) {
                $intersectResult = MAYBE;
            } else {
                $intersectResult = OUT;
            }
            $r[$ix][$iy] = $intersectResult;
            $sr .= $intersectResult;
        }
        $sr .= '|';
    }
    return [$r, $sr];
}
function getGrid($sx, $sy, $ex, $ey, $gridX, $gridY) {
    $unDecimal = 0.0000000001; /////////// para 4 dígitos
    $unDecimal = 0.0;
    $dx = $ex - $sx;
    $dy = $ey - $sy;
    $grid = [];
    for ($x = 0; $x < $dx; $x++) {
        $grid[$x] = [];
        $sxCell = ($sx + $x) * $gridX;
        $exCell = ($sx + $x + 1) * $gridX - $unDecimal;
        for ($y = 0; $y < $dy; $y++) {
            $syCell = ($sy + $y) * $gridX;
            $eyCell = ($sy + $y + 1) * $gridY - $unDecimal;
            $grid[$x][$y] = getSquarePolyFromXYXY($sxCell, $syCell, $exCell, $eyCell);
        }
    }
    return $grid;
}

/*
class Point {
    public float $x, $y;

    function __construct($x, $y) {
        $this->x = $x;
        $this->y = $y;
    }
}

function IsIntersecting(Point $a, Point $b, Point $c, Point $d): boolean {
    float denominator = ((b.X - a.X) * (d.Y - c.Y)) - ((b.Y - a.Y) * (d.X - c.X));
    float numerator1 = ((a.Y - c.Y) * (d.X - c.X)) - ((a.X - c.X) * (d.Y - c.Y));
    float numerator2 = ((a.Y - c.Y) * (b.X - a.X)) - ((a.X - c.X) * (b.Y - a.Y));

    // Detect coincident lines (has a problem, read below)
    if (denominator == 0) return numerator1 == 0 && numerator2 == 0;

    float r = numerator1 / denominator;
    float s = numerator2 / denominator;

    return (r >= 0 && r <= 1) && (s >= 0 && s <= 1);
}*/
