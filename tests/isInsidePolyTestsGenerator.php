<?php

require_once('../libs/polygon.php');
//require_once('../libs/database.php');
require_once('../libs/Redis.lib.php');
require_once('../libs/polygon-draw.php');
require_once('../libs/matrix-utils.php');
require_once('../libs/Templates.php');


$polys['mixed1'] = $polyA = new polygon();
$polyA->addv(0, 0);
$polyA->addv(0, 5, 0, 10, +1);
$polyA->addv(0, 15);
$polyA->addv(0, 20);
$polyA->addv(20, 20);
$polyA->addv(20, 5, 20, 0, -1);
$polyA->addv(15, 0);


$polys['mixed1_forzando'] = $polyA = new polygon();
$polyA->addv(0, 0);
$polyA->addv(0, 5, 5, 10, +1);
$polyA->addv(0, 15);
$polyA->addv(0, 20);
$polyA->addv(20, 20);
$polyA->addv(20, 5, 25, 0, -1);
$polyA->addv(15, 0);

$polys['t1'] = $polyA = new polygon();
$polyA->addv(0, -2);
$polyA->addv(0, 80);
$polyA->addv(40, 80);
$polyA->addv(40, 0);
$polyA->addv(10, 0, 0, 20, 1);


$polys['t2'] = $polyA = new polygon();
$polyA->addv(0, 0, 0, 20, 1);
$polyA->addv(0, 40, 20, 40, 1);
$polyA->addv(40, 40, 40, 20, 1);
$polyA->addv(40, 0, 20, 0, 1);


$polys['mixed2'] = $polyA = new polygon();        // Create a new polygon and add some vertices to it
$polyA->addv(0, 0);
$polyA->addv(0, 20, 0, 40, 1);
$polyA->addv(0, 60);
$polyA->addv(0, 80);
$polyA->addv(45, 80);
$polyA->addv(45, 60, 45, 40, 1);
$polyA->addv(45, 20);
$polyA->addv(45, 0);

$polys['circle0'] = $polyA = new polygon();
$polyA->addv(24, 0, 0, 0, +1);

$polys['circle1'] = $polyA = new polygon();
$polyA->addv(24, 0, 0, 0, +1);
$polyA->addv(-24, 0, 0, 0, +1);

$polys['circle2'] = $polyA = new polygon();
$polyA->addv(24, 0, 0, 0, -1);
$polyA->addv(-24, 0, 0, 0, -1);

$polys['circle3'] = $polyA = new polygon();
$polyA->addv(-24, 0, 0, 0, -1);
$polyA->addv(24, 0, 0, 0, -1);

$polys['circle4'] = $polyA = new polygon();
$polyA->addv(-24, 0, 0, 0, +1);
$polyA->addv(24, 0, 0, 0, +1);



$polys['box16'] = $polyA = new polygon();
$polyA->addv(0, 112);
$polyA->addv(16, 112);
$polyA->addv(16, 128);
$polyA->addv(0, 128);

$dropPoly = Templates::getDropPolygonWithDimensions(1 / 5, 4/5);
$dropPoly = Templates::getScalatedPolygonCopy($dropPoly, 128, 128);
//$dropPoly = Templates::getRotatedPolygonCopy($dropPoly
//    , Templates::angleToRadians($angle));
$polys['drop128'] = $dropPoly;

$polys['t3'] = $polyA = new polygon();        // Create a new polygon and add some vertices to it
$polyA->addv(0, 0);
$polyA->addv(0, 80); //-
$polyA->addv(40, 0);
$polyA->addv(40, 80);

$polys['t4'] = $polyA = new polygon();
$polyA->addv(16, 131);
$polyA->addv(71, 166);
$polyA->addv(105, 138);
$polyA->addv(25, 63);
$polyA->addv(118, 75);

$polys['t5'] = $polyA = new polygon();
$polyA->addv(3, 11);
$polyA->addv(9, 15);
$polyA->addv(12, 12);
$polyA->addv(6, 3);
$polyA->addv(15, 5);
$polys['t6'] = $polyA = new polygon();
$polyA->addv(3, 11);
$polyA->addv(9, 15);
$polyA->addv(11, 15);
$polyA->addv(12, 12);
$polyA->addv(6, 3);
$polyA->addv(15, 5);

//$p5 = new Vertex(25, 128);
//$r1 = $movedPoly->isInside($p5, true);
//$2 = $movedPoly->completelyContains($polyA);
//$p0 = new Vertex(0, 0);
//$r3 = $polys['circle1']->isInside($p0, true);
$v = new Vertex(17, -4); //false??
$r1 = $polys['mixed1']->isInside($v, true);
$v = new Vertex(21, 0); //true
//$v = new Vertex(26, 0); //true
//$r1 = $polys['mixed1']->isInside($v, true);
//$v = new Vertex(20, 0); //false
//$v = new Vertex(20, 0); //false
//$v = new Vertex(42, 40); //false
//$r1 = $polys['t2']->isInside($v, true);
//$v = new Vertex(1, 0);
//$r1 = $polys['mixed1']->isInside($v, true);
//$v = new Vertex(10, 5); //true
//$v = new Vertex(10, 15); //true
//$r1 = $polys['mixed1']->isInside($v, true);
//$v = new Vertex(18, 112);
//$r1 = $polys['box16']->isInside($v, true); //false
//$v = new Vertex(25, 20);
//$r1 = $polys['mixed1']->isInside($v, true); //false
//$v = new Vertex(41, 0); //false
//$v = new Vertex(42, 0); //false
//$r1 = $polys['t1']->isInside($v, true); //false
foreach ($polys as $name => $poly) {
    echo "\n----- Probando $name -----\n";
    $filepath = '../examples/testBatteries/' . $name;
    Templates::polyFillTestToImage($poly, "$filepath-empty.gif", "$filepath-fillingtest.gif");
}
