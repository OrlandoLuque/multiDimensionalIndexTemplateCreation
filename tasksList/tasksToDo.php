<?php

$polygonScales = [128, 64, 1024, 512, 256, 128, 16];
$gridSupportedSizes = [16, 32, 64, 128, 256, 512];

////////////////////// SMALLER SET FOR TESTING PURPOSES /////////////////////////
//$polygonScales = [/*32, 64, */
//    128, 256, 512, 1024];
//$gridSupportedSizes = [16, 32, 64, 128, 256, 512];
///////////////////////////////////////////////

$grids = Templates::getGridsFromSupportedSizes($gridSupportedSizes, true, true);


$circlePoly = Templates::getCircleWithRadius(1);
$boxPoly = Templates::getSquarePolygonWithDimensions(1);        // Create a new polygon and add some vertices to it
$dropPoly = Templates::getDropPolygonWithDimensions(1 / 5, 4/5); // or 1/6
$polys = ['drop' => $dropPoly, 'box' => $boxPoly, 'circle' => $circlePoly];
$angles = Templates::getAnglesToTest(0.5);

$taskRedisKey = "lock";
$templateListRedisKey = 'templateList';
$generationSetRedisKey = 'generatedSet';
$templateCountRedisKey = 'templateCount';
$lastTemplateRedisKey = 'lastTemplate';

return [$polys, $polygonScales, $gridSupportedSizes, $angles,
    $taskRedisKey, $templateListRedisKey, $generationSetRedisKey,
    $templateCountRedisKey, $lastTemplateRedisKey];
