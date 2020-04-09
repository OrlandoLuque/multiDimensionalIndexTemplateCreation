<?php
// https://www.php.net/manual/en/intro.bc.php
$num1 = 0; // (string) 0 => '0'
$num2 = -0.000005; // (string) -0.000005 => '-5.05E-6'
//echo bcadd($num1, $num2, 6); // => '0.000000'

//setlocale(LC_NUMERIC, 'de_DE'); // uses a decimal comma
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

if (false) {
    $width = 200;
    $height = 200;
    newImage($width, $height, $img, $col);
    $r = imageline($img, 0, 0, $width - 1, 0, $col['blk']);
    $r = imageline($img, $width - 1, 0, $width - 1, $height - 1, $col['blk']);
    $r = imageline($img, $width - 1, $height - 1, 0, $height - 1, $col['blk']);
    $r = imageline($img, 0, $height - 1, 0, 0, $col['blk']);
    $polyA = new polygon();        // Create a new polygon and add some vertices to it
    $polyA->addv(0, 0);
    $polyA->addv(0, 20, 0, 40, 1); //
    $polyA->addv(0, 60);
    $polyA->addv(0, 80);
    $polyA->addv(10, 80);
    $polyA->addv(25, 0); //
    $polyA = new polygon();        // Create a new polygon and add some vertices to it
    $polyA->addv(0, 0);
    $polyA->addv(0, 20, 0, 40, 1);
    $polyA->addv(0, 60);
    $polyA->addv(0, 80);
    $polyA->addv(45, 80);
    $polyA->addv(45, 60, 45, 40, 1);
    $polyA->addv(45, 20);
    $polyA->addv(45, 0);
    /*$polyA = new polygon();        // Create a new polygon and add some vertices to it
    $polyA->addv(0,0);
    $polyA->addv(0,80);
    $polyA->addv(40,80);
    $polyA->addv(40,0);
    $polyA->addv(10,0,0,20,1);
    $polyA = new polygon();        // Create a new polygon and add some vertices to it
    $polyA->addv(0,0,0,20,1);
    $polyA->addv(0,40,20,40,1);
    $polyA->addv(40,40,40,20,1);
    $polyA->addv(40,0,20,0,1);*/
    $polyA = new polygon();        // Create a new polygon and add some vertices to it
    $polyA->addv(0, 0);
    $polyA->addv(0, 80); //-
    $polyA->addv(40, 0);
    $polyA->addv(40, 80); //-

    if ($polyA->isPolySelfIntersect()) {
        drawPolyAt(50, 50, $img, $polyA, $col, "grn");
    } else {
        drawPolyAt(50, 50, $img, $polyA, $col, "red");
    }
    $r = imageGif($img, "poly_ex_autointersection.gif");
    echo '<p><div align="center"><strong>EXAMPLE 1 - intersections</strong><br><img src="poly_ex_autointersection.gif" xwidth="600" xheight="200"><br></div></p>';

    die();
}

$h1 = new Vertex(-1, 0);
$h2 = new Vertex(1, 0);
$v1 = new Vertex(0, -1);
$v2 = new Vertex(0, 1);
$p5 = new Vertex(0, 0);
$rVertical = Polygon::vertexIntsLine($p5, $v1, $v2); // |
$rHorizontal = Polygon::vertexIntsLine($p5, $h1, $h2); // -
$r = Polygon::ints($p5, $p5, $v1, $v2, $n, $ix, $iy, $alphaP, $alphaQ);

$h1 = new Vertex(0, 15);
$h2 = new Vertex(31, 15);
$v1 = new Vertex(15, 0);
$v2 = new Vertex(15, 31);
$v1 = new Vertex(0, 0);
$v2 = new Vertex(31, 31);
newImage(32, 32, $img, $col);
for ($x = 0; $x < 32; $x++) {
    for ($y = 0; $y < 32; $y++) {
        $p5 = new Vertex($x, $y);
        $rVertical = Polygon::vertexIntsLine($p5, $v1, $v2); // |
        $rHorizontal = Polygon::vertexIntsLine($p5, $h1, $h2); // -
        if ($rVertical && $rHorizontal) {
            $r = imagesetpixel($img, $x, $y, $col['red']);
        } else if ($rVertical || $rHorizontal) {
            $r = imagesetpixel($img, $x, $y, $col['blu']);
        }
    }
}
//$r = imageGif($img,"poly_exi.gif");
//echo '<p><div align="center"><strong>EXAMPLE 1 - intersections</strong><br><img src="poly_exi.gif" width="600" height="200"><br></div></p>';
//die();

//////////////////////////////////////////////////////////////////////////////////////////////

$a1 = new Vertex( 9,4, 9, 9, -1);
$a2 = new Vertex(9,14);
$a1 = new Vertex( 9,4, 9, 9, -1);
$a2 = new Vertex(4,9);
$polyA = new polygon();        // Create a new polygon and add some vertices to it
$polyA->add($a1);
$polyA->add($a2);
$c = new Vertex(9, 9);
newImage(20, 20, $img, $col);
$r = imageline($img, 0, 0, 19, 0, $col['blk']);
$r = imageline($img, 19, 0, 19, 19, $col['blk']);
$r = imageline($img, 19, 19, 0, 19, $col['blk']);
$r = imageline($img, 0, 19, 0, 0, $col['blk']);
/**
 * @param int $x
 * @param int $y
 * @param Vertex $c
 * @param Vertex $a1
 * @param Vertex $a2
 * @param $img
 * @param $col
 */
function checkingLineArcIntersection(int $x, int $y, Vertex $c, Vertex $a1, Vertex $a2, $img, $col): bool {
    $target = new Vertex($x, $y);
    $r = Intersector::lineArcIntersection($c, $target, $a1, $a2);
    if (count($r) > 0) {
        //$r = imagesetpixel($img, $x, $y, $col['grn']);
        $r = imageline($img, $c->x, $c->y, $x, $y, $col['grn']);
        return true;
    } else {
        $r = imageline($img, $c->x, $c->y, $x, $y, $col['red']);
    }
    return false;
}

if(false) {
    for ($x = 0; $x < 20; $x++) {
        $r1 = checkingLineArcIntersection($x, 0, $c, $a1, $a2, $img, $col); //top
        $r2 = checkingLineArcIntersection($x, 19, $c, $a1, $a2, $img, $col); //bottom
        $r3 = checkingLineArcIntersection(0, $x, $c, $a1, $a2, $img, $col); //left
        $r4 = checkingLineArcIntersection(19, $x, $c, $a1, $a2, $img, $col); //right
        $a = 1;
    }
    directDrawPolyAt(0, 0, $img, $polyA, $col, "blk");
    $r = imageGif($img, "poly_exArcInterception.gif");
    echo '<p><div align="center"><strong>EXAMPLE X - arc interception</strong><br><img src="poly_exArcInterception.gif" style="image-rendering: pixelated" width="' . 40 . '" height="' . 40 . '"><br></div></p>';
}

//////////////////////////////////////////////////////////////////////////////////////////////


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

//$r = $polyB->completelyContains($polyA);
/*
$polyA = new polygon();
$polyA->addv( 16,131);
$polyA->addv( 71,166);
$polyA->addv(105,138);
$polyA->addv( 25, 63);
$polyA->addv(118, 75);
*/

$polyA = new polygon();
$polyA->addv( 3,11);
$polyA->addv( 9,15);
$polyA->addv(12,12);
$polyA->addv( 6, 3);
$polyA->addv(15, 5);
$polyA = new polygon();
$polyA->addv( 3,11);
$polyA->addv( 9,15);
$polyA->addv( 11,15);
$polyA->addv(12,12);
$polyA->addv( 6, 3);
$polyA->addv(15, 5);

$polyA = new polygon();
$polyA->addv( 0,0);
$polyA->addv( 0,5, 0, 10, +1);
$polyA->addv(0,15);
$polyA->addv( 0, 20);
$polyA->addv(20, 20);
$polyA->addv(20, 5, 20, 0, -1);
$polyA->addv(15, 0);

/*$polyA = new polygon();        // Create a new polygon and add some vertices to it
$polyA->addv(0,0);
$polyA->addv(0,20,0,40,1);
$polyA->addv(0,60);
$polyA->addv(0,80);
$polyA->addv(45,80);
$polyA->addv(45,60,45,40,1);
$polyA->addv(45,20);
$polyA->addv(45,0);*/
$extraMargin = 6;
newImage($polyA->x_max + $extraMargin * 2,$polyA->y_max + $extraMargin * 2, $img, $col);
newImage($polyA->x_max + $extraMargin * 2,$polyA->y_max + $extraMargin * 2, $img2, $col);
//directDrawPolyAt(30, 30, $img, $polyA, $col, "blk");
//drawPolyAt(30, 30, $img2, $polyA, $col, "blk");
$polyA->move($extraMargin, $extraMargin);
directDrawPolyAt(0, 0, $img, $polyA, $col, "blk");
drawPolyAt(0, 0, $img2, $polyA, $col, "blk");
$r = imageGif($img,"poly_ex4insidePolygon.gif");
$r = imageGif($img2,"poly_ex4insidePolygon2.gif");
/////// echo '<p><div align="center"><strong>EXAMPLE 4 - points inside or outside polygon</strong><br><img src="poly_ex4insidePolygon.gif" style="image-rendering: pixelated" width="' . ($polyA->x_max + 60) * 4 . '" height="' . ($polyA->y_max + 60) * 4 . '"><br></div></p>';
/////// echo '<p><div align="center"><strong>EXAMPLE 4 - points inside or outside polygon</strong><br><img src="poly_ex4insidePolygon2.gif" style="image-rendering: pixelated" width="' . ($polyA->x_max + 60) * 4 . '" height="' . ($polyA->y_max + 60) * 4 . '"><br></div></p>';
//die();
/*
$toCheck = [[2, 2], [15, 3], [6, 3], [15, 3], [5, 11]];
foreach ($toCheck as $coords) {
    $v = new Vertex($coords[0], $coords[1]);
    $rv = $polyA->isInside($v);
    if ($rv){
        $r = imagesetpixel($img, $coords[0], $coords[1], $col['grn']);
    } else {
        $r = imagesetpixel($img, $coords[0], $coords[1], $col['red']);
    }
}
$r = imageGif($img,"poly_ex4insidePolygon.gif");
echo '<p><div align="center"><strong>EXAMPLE 4 - points inside or outside polygon</strong><br><img src="poly_ex4insidePolygon.gif" style="image-rendering: pixelated" width="' . ($polyA->x_max + 1) * 4 . '" height="' . ($polyA->y_max + 1) * 4 . '"><br></div></p>';
//die();
*/
if(false) {
    newImage($polyA->x_max + $extraMargin * 2, $polyA->y_max + $extraMargin * 2, $im, $colors);               // Create a new image to draw our polygons
    directDrawPolyAt(0, 0, $im, $polyA, $colors, "red");
    $r = imageGif($im, "poly_ex2polygon.gif");
    echo '<p><div align="center"><strong>EXAMPLE 2 - poligon used on example 3</strong><br><img src="poly_ex2polygon.gif" style="image-rendering: pixelated" width="' . ($polyA->x_max + $extraMargin) * 4 . '" height="' . ($polyA->y_max + $extraMargin) * 4 . '"><br></div></p>';

    newImage($polyA->x_max + $extraMargin * 2, $polyA->y_max + $extraMargin * 2, $img, $col);
    directDrawPolyAt(0, 0, $img, $polyA, $colors, "red");
    for ($x = 0; $x < $polyA->x_max + $extraMargin * 2; $x++) {
        for ($y = 0; $y < $polyA->y_max + $extraMargin * 2; $y++) {
            $p5 = new Vertex($x, $y);
            //$r1 = $polyA->isInside($p5);
            $a = 1;
            $r1 = $polyA->isInside($p5, true);
            if ($r1) {
                $r = imagesetpixel($img, $x, $y, $col['grn']);
            } else {
                //$r = imagesetpixel($img, $x, $y, $col['blu']);
            }
        }
    }
    $r = imageGif($img, "poly_ex_vertex_inside.gif");
    echo '<p><div align="center"><strong>EXAMPLE 3 - vertex is inside</strong><br><img src="poly_ex_vertex_inside.gif" style="image-rendering: pixelated" width="' . ($polyA->x_max + $extraMargin) * 4 . '" height="' . ($polyA->y_max + $extraMargin) * 4 . '"><br></div></p>';
    die();
}

$polyA = new polygon();
$polyA->addv( 0,0);
$polyA->addv( 0,5, 0, 10, +1);
$polyA->addv(0,15);
$polyA->addv( 0, 20);
$polyA->addv(20, 20);
$polyA->addv(20, 5, 20, 0, -1);
$polyA->addv(15, 0);
$polyB = $polyA->bRect();

$maxX = $polyA->x_max + $extraMargin * 2;
$maxY = $polyA->y_max + $extraMargin * 2;
newImage($maxX, $maxY, $img, $col);
$r = imageline($img, 0, $maxY - 8, $maxX, $maxY - 8, $col["grn"]);
$r = imageline($img, 4, 0, 4, $maxY, $col["grn"]);
drawPolyAt(4, 8, $img, $polyA, $col, "blk");
drawPolyAt(4, 8, $img, $polyB, $col, "red");
$r = imageGif($img, "poly_ex_bounding_rectangle.gif");
echo '<p><div align="center"><strong>EXAMPLE y - bounding rectangle</strong><br><img src="poly_ex_bounding_rectangle.gif" style="image-rendering: pixelated" width="' . ($polyA->x_max + $extraMargin) * 4 . '" height="' . ($polyA->y_max + $extraMargin) * 4 . '"><br></div></p>';
//die();

define("OUT", 0);
define("IN", 2);
define("MAYBE", 1);
$polygonScales = [32, 64, 128, 256, 512, 1024];
$gridSupportedSizes = [16, 32, 64, 128, 256, 512];
$grids = getGridsFromSupportedSizes($gridSupportedSizes);


$circlePoly = getCircleWithRadius(1);
$boxPoly = getSquarePolygonWithDimensions(1);        // Create a new polygon and add some vertices to it
$dropPoly = getDropPolygonWithDimensions(1/5, 1); // or 1/6
$polys = ['drop' => $dropPoly, 'box' => $boxPoly, 'circle' => $circlePoly];
$angles = getAnglesToTest(0.5);

//$bBoxA = $polyA->bRect();
//$polyA->scale();
$templates = [];
$hashToTemplatesIdDictionary = [];
$idToTemplatesDictionary = [];
$templateCount = 0;
$calculatedTemplates = 0;
$inicio = date("Y-m-d H:i:s");
foreach ($polys as $indexPoly => $poly) {
    foreach ($polygonScales as $polygonScale) {
        $scalatedPoly = getScalatedPolygonCopy($poly, $polygonScale, $polygonScale);
        foreach ($grids as $gridDimensions) {
            $gridX = $gridDimensions[0];
            $gridY = $gridDimensions[1];
            /** @var float $angle */
            foreach ($angles as $angleIndex => $angle) {
                $rotatedPoly = getRotatedPolygonCopy($scalatedPoly, $angle);
                for ($x = 0; $x < $gridX; $x++) {
                    for ($y = 0; $y < $gridY; $y++) {
                        $hash = "$indexPoly-s$polygonScale-x$gridX,y$gridY-a$angle-dx$x,dy$y";
                        $movedPoly = $rotatedPoly->copy_poly();
                        $movedPoly->move($x, $y);
                        $box = $movedPoly->bRect();
                        $boxVertex = [0 => ['x' => $box->first->x, 'y' => $box->first->y]
                            , 1 => ['x' => $box->first->nextV->nextV->x, 'y' => $box->first->nextV->nextV->y]];
                        $template = [];
                        $gridXRange = [floor($boxVertex[0]['x'] / $gridX), floor($boxVertex[1]['x'] / $gridX)];
                        $gridYRange = [floor($boxVertex[0]['y'] / $gridY), floor($boxVertex[1]['y'] / $gridY)];
                        $grid = getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);
                        list($templateGridXY, $templateHashYX) = getTemplateGrid($grid, $movedPoly);
                        echo "\n$hash --> $templateHashYX ";
                        if (empty($hashToTemplatesIdDictionary[$templateHashYX])) {
                            $hashToTemplatesIdDictionary[$templateHashYX] = $templateGridXY;
                            $templateCount++;
                            echo "+++++++++++++++++++++ ";
                        } else {
                            echo "repeated! ";
                        }
                        $idToTemplatesDictionary[$hash] = $hashToTemplatesIdDictionary[$templateHashYX];
                        $calculatedTemplates++;
                        echo " $templateCount plantillas para $calculatedTemplates combinaciones ";
                    }
                }
            }
        }
    }
}
echo "Calculated templates: $calculatedTemplates - unique ones: $templateCount - desde $inicio a " . date("Y-m-d H:i:s");
/**
 * @param $grid
 * @param polygon $poly
 * @return array
 */
function getTemplateGrid($grid, $poly) {
    $r = []; $sr = '';
    foreach($grid as $iy => $row) {
        /** @var polygon $cell */
        foreach($row as $ix => $cell) {
            if ($poly->completelyContains($cell)) {
                $intersectResult = IN;
            } else if ($cell->completelyContains($poly)
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
    for ($y = 0; $y < $dy; $y++) {
        $syCell = ($sy + $y) * $gridX;
        $eyCell = ($sy + $y + 1) * $gridY - $unDecimal;
        for ($x = 0; $x < $dx; $x++) {
            $sxCell = ($sx + $x) * $gridX;
            $exCell = ($sx + $x + 1) * $gridX - $unDecimal;
            $grid[$y][$x] = getSquarePolyFromXYXY($sxCell, $syCell, $exCell, $eyCell);
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
    float denominator = ((b->X() - a->X()) * (d->Y() - c->Y())) - ((b->Y() - a->Y()) * (d->X() - c->X()));
    float numerator1 = ((a->Y() - c->Y()) * (d->X() - c->X())) - ((a->X() - c->X()) * (d->Y() - c->Y()));
    float numerator2 = ((a->Y() - c->Y()) * (b->X() - a->X())) - ((a->X() - c->X()) * (b->Y() - a->Y()));

    // Detect coincident lines (has a problem, read below)
    if (denominator == 0) return numerator1 == 0 && numerator2 == 0;

    float r = numerator1 / denominator;
    float s = numerator2 / denominator;

    return (r >= 0 && r <= 1) && (s >= 0 && s <= 1);
}*/
