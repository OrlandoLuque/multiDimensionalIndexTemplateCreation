<?php

// https://www.php.net/manual/en/intro.bc.php
$num1 = 0; // (string) 0 => '0'
$num2 = -0.000005; // (string) -0.000005 => '-5.05E-6'
//echo bcadd($num1, $num2, 6); // => '0.000000'

//setlocale(LC_NUMERIC, 'de_DE'); // uses a decimal comma
$num2 = 1.2; // (string) 1.2 => '1,2'
//echo bcsub($num1, $num2, 1); // => '0.0'
//echo '\n/n ------- ' . bcdiv(1, 3, 100);

require_once('libs/polygon.php');
//require_once('libs/database.php');
require_once('libs/Redis.lib.php');
require_once('libs/polygon-draw.php');
require_once('libs/matrix-utils.php');
require_once('libs/Templates.php');
require_once('libs/Task.php');

//////////////////////////////////////////////////////////////////////////////////////////////

/*define("OUT", 0);
define("IN", 2);
define("MAYBE", 1);*/
$polygonScales = [128, 64, 1024, 512, 256, 128, 16];
$gridSupportedSizes = [16, 32, 64, 128, 256, 512];

///////////////////////////////////////////////
$polygonScales = [/*32, 64, */
    128, 256, 512, 1024];
$gridSupportedSizes = [16, 32, 64, 128, 256, 512];
///////////////////////////////////////////////

$grids = Templates::getGridsFromSupportedSizes($gridSupportedSizes);


$circlePoly = Templates::getCircleWithRadius(1);
$boxPoly = Templates::getSquarePolygonWithDimensions(1);        // Create a new polygon and add some vertices to it
$dropPoly = Templates::getDropPolygonWithDimensions(1 / 5, 4/5); // or 1/6
$polys = ['drop' => $dropPoly, 'box' => $boxPoly, 'circle' => $circlePoly];
$angles = Templates::getAnglesToTest(0.5);

$redis = new Redis();
$result = $redis->connect('127.0.0.1', 6379);

$taskKey = "lock";
$templateListKey = 'templateList';
$generationSetKey = 'generatedSet';
$templateCountKey = 'templateCount';
$lastTemplateKey = 'lastTemplate';

$tasks = [];
$taskCount = 1;
/**
 * @param array $polys
 * @param array $polygonScales
 * @param array $gridSupportedSizes
 * @param int $taskCount
 * @param Redis $redis
 * @param array $angles
 * @param string $taskKey
 * @param string $templateListKey
 * @param string $generationSetKey
 * @param string $templateCountKey
 * @param string $lastTemplateKey
 * @param array $tasks
 * @return array
 */
function createTaskSubtasks(array $polys, array $polygonScales, array $gridSupportedSizes, int $taskCount, Redis $redis, array $angles, string $taskKey, string $templateListKey, string $generationSetKey, string $templateCountKey, string $lastTemplateKey, array $tasks): array
{
    foreach ($polys as $title => $polygon) {
        foreach ($polygonScales as $polygonScale) {
            foreach ($gridSupportedSizes as $gridPixelDensity) {
                $taskPrefix = "T$taskCount-";
                $gridDimensions = Templates::getGridsFromSupportedSizes([$gridPixelDensity]);
                $task = new Task();
                $task = $task
                    ->setRedis($redis)
                    ->setPolygons([$polygon])
                    ->setPolygonScales([$polygonScale])
                    ->setGridsDimensions($gridDimensions)
                    ->setAngles($angles)
                    ->setTaskKey($taskPrefix . $taskKey)
                    ->setTemplateListKey($taskPrefix . $templateListKey)
                    ->setGenerationSetKey($taskPrefix . $generationSetKey)
                    ->setTemplateCountKey($templateCountKey)
                    ->setLastTemplateKey($taskPrefix . $lastTemplateKey)
                    ->loadScripts();
                $tasks[] = $task;
                $taskCount++;
            }
        }
    }
    return array($task, $tasks);
}

list($task, $tasks) = createTaskSubtasks($polys, $polygonScales, $gridSupportedSizes, $taskCount, $redis, $angles, $taskKey, $templateListKey, $generationSetKey, $templateCountKey, $lastTemplateKey, $tasks);

//$bBoxA = $polyA->bRect();
//$polyA->scale();
//$templates = [];
//$hashToTemplatesIdDictionary = [];
//$idToTemplatesDictionary = [];

$inicio = date("Y-m-d H:i:s");

declare(ticks=1);

if (false) {

    // DO NOT WORK IN MY SETUP!!! Tests halted.
    // I was going to force redis persistence
    // but instead I have modified Redis settings
    function sig_handler($sig)
    {
        switch ($sig) {
            case SIGINT:
                # one branch for signal...
        }
        echo "HELLOWWW";
    }

    pcntl_signal(SIGINT, "sig_handler");
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGHUP, "sig_handler");

    die();
}



if (false) {
    /** @var Task $task */
    $task = new Task();
    $task = $task
        ->setRedis(null)
        ->setPolygons($polys)
        ->setPolygonScales($polygonScales)
        ->setGridsDimensions($grids)
        ->setAngles($angles)
        ->setTaskKey($taskKey)
        ->setTemplateListKey($templateListKey)
        ->setGenerationSetKey($generationSetKey)
        ->setTemplateCountKey($templateCountKey)
        ->setLastTemplateKey($lastTemplateKey)
        ->loadScripts();

    $result = $redis->eval($task->checkAndLockTastScript, [1]);
    $error = $redis->getLastError();
    $result2 = $redis->eval($task->checkAndLockTastScript, [1]);
    $error2 = $redis->getLastError();

    $a = 1;
    list($im, $colors, $img) = Templates::generateAndPersist($task, false, null /*, 'drop-s128-x32,y16-a54-dx2,dy4' */);
}
$taskNumber = 1;
foreach ($tasks as $task) {
    $successfulLock = $redis->eval($task->checkAndLockTastScript, [$task->taskKey]);
    if ($successfulLock) {
        Templates::generateAndPersist($task, true, null /*, 'drop-s128-x32,y16-a54-dx2,dy4' */);
    }
}
