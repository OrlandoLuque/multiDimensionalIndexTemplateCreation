<?php
/**
 * Debug tool for fill check anomalies.
 *
 * Reproduces a specific polygon/scale/angle combination and runs the fill check,
 * generating SVG debug images without needing Redis or GD.
 *
 * Usage:
 *   php debugFillCheck.php <polygon> <scale> <angle> [x y]
 *
 * Examples:
 *   php debugFillCheck.php drop 128 70.5              Run fill check, generate SVG
 *   php debugFillCheck.php drop 128 70.5 -2 1         Also test isInside at (-2, 1)
 *   php debugFillCheck.php circle 64 45               Test circle at 45 degrees
 *   php debugFillCheck.php box 256 30                 Test box at 30 degrees
 *
 * Output: SVG file in fill_check_debug/ directory
 *
 * Author: Orlando Jose Luque Moraira
 */

require_once(__DIR__ . '/libs/polygon.php');
require_once(__DIR__ . '/libs/matrix-utils.php');
require_once(__DIR__ . '/libs/Templates.php');

// --- Parse arguments ---

if ($argc < 4) {
    echo "Usage: php debugFillCheck.php <polygon> <scale> <angle> [x y]\n\n";
    echo "Polygons: drop, box, circle\n";
    echo "  drop   [width height]   default: 0.2 0.8\n";
    echo "  box    [side]           default: 1\n";
    echo "  circle [radius]         default: 1\n";
    echo "\nExamples:\n";
    echo "  php debugFillCheck.php drop 128 70.5\n";
    echo "  php debugFillCheck.php drop 128 70.5 -2 1\n";
    exit(1);
}

$polyType = $argv[1];
$scale = (float)$argv[2];
$angle = (float)$argv[3];
$testX = isset($argv[4]) ? (float)$argv[4] : null;
$testY = isset($argv[5]) ? (float)$argv[5] : null;

// --- Create polygon ---

switch ($polyType) {
    case 'drop':
        $poly = Templates::getDropPolygonWithDimensions(1/5, 4/5);
        break;
    case 'box':
        $poly = Templates::getSquarePolygonWithDimensions(1);
        break;
    case 'circle':
        $poly = Templates::getCircleWithRadius(1);
        break;
    default:
        // Try loading from config.json
        $configPath = __DIR__ . '/config.json';
        if (!file_exists($configPath)) $configPath = getcwd() . '/config.json';
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            if (isset($config['polygons'][$polyType])) {
                $def = $config['polygons'][$polyType];
                if ($def['type'] === 'custom') {
                    $poly = new polygon();
                    foreach ($def['vertices'] as $v) {
                        if (isset($v['arc'])) {
                            $poly->addv($v['x'], $v['y'], $v['arc']['cx'], $v['arc']['cy'], $v['arc']['d']);
                        } else {
                            $poly->addv($v['x'], $v['y']);
                        }
                    }
                } elseif ($def['type'] === 'drop') {
                    $poly = Templates::getDropPolygonWithDimensions($def['width'], $def['height']);
                } elseif ($def['type'] === 'box') {
                    $poly = Templates::getSquarePolygonWithDimensions($def['side']);
                } elseif ($def['type'] === 'circle') {
                    $poly = Templates::getCircleWithRadius($def['radius']);
                }
            }
        }
        if (!isset($poly)) {
            echo "ERROR: Unknown polygon '$polyType'. Use drop, box, circle, or a name from config.json\n";
            exit(1);
        }
}

echo "=== Fill Check Debug ===\n";
echo "Polygon: $polyType | Scale: $scale | Angle: $angle degrees\n\n";

// --- Scale and rotate ---

$scaled = Templates::getScalatedPolygonCopy($poly, $scale, $scale);
$rotated = Templates::getRotatedPolygonCopy($scaled, Templates::angleToRadians($angle));

echo "Polygon bounding box after transform:\n";
$box = $rotated->bRect();
echo "  x: [{$box->x_min}, {$box->x_max}]  y: [{$box->y_min}, {$box->y_max}]\n\n";

// --- Test specific point ---

if ($testX !== null && $testY !== null) {
    echo "--- Point test ---\n";
    $v = new Vertex($testX, $testY);
    $result = $rotated->isInside($v, true);
    echo "isInside($testX, $testY) = " . ($result ? 'true (INSIDE)' : 'false (OUTSIDE)') . "\n";

    // Also test neighbors
    echo "\nNeighborhood:\n";
    for ($dy = 2; $dy >= -2; $dy--) {
        $line = "  y=" . sprintf('%+d', $testY + $dy) . ": ";
        for ($dx = -2; $dx <= 2; $dx++) {
            $px = $testX + $dx;
            $py = $testY + $dy;
            $vt = new Vertex($px, $py);
            $r = $rotated->isInside($vt, true);
            $line .= $r ? '# ' : '. ';
        }
        echo "$line\n";
    }
    echo "  (# = inside, . = outside)\n\n";
}

// --- Run fill check ---

echo "--- Fill check ---\n";
$checkResult = Templates::checkNoLinesInPolygonFilling($rotated);

if ($checkResult === true) {
    echo "PASS: No anomalies detected.\n\n";
} else {
    $anomalies = $checkResult['anomalies'];
    echo "FAIL: " . count($anomalies) . " anomaly(ies) detected.\n\n";

    foreach ($anomalies as $i => $a) {
        $inside = $a['val'] ? 'true' : 'false';
        $mid = $a['y_minus_1'] ? 'true' : 'false';
        echo "Anomaly #$i at ({$a['x']}, {$a['y']}):\n";
        echo "  isInside({$a['x']}, " . ($a['y'] - 2) . ") = $inside  (y-2)\n";
        echo "  isInside({$a['x']}, " . ($a['y'] - 1) . ") = $mid     (y-1, suspected wrong)\n";
        echo "  isInside({$a['x']}, {$a['y']})   = $inside  (y)\n";
        echo "  Confirmed in column x={$a['prev_x']}\n\n";

        // Show neighborhood around anomaly
        echo "  Neighborhood at anomaly:\n";
        for ($dy = 3; $dy >= -3; $dy--) {
            $line = "    y=" . sprintf('%4d', $a['y'] + $dy) . ": ";
            for ($dx = -3; $dx <= 3; $dx++) {
                $px = $a['x'] + $dx;
                $py = $a['y'] + $dy;
                $r = isset($checkResult['result'][$px][$py]) ? $checkResult['result'][$px][$py] : null;
                if ($r === null) $line .= '? ';
                elseif ($r) $line .= '# ';
                else $line .= '. ';
            }
            echo "$line\n";
        }
        echo "    (# = inside, . = outside, ? = out of bounds)\n\n";
    }

    // Generate SVG
    $debugDir = __DIR__ . '/fill_check_debug';
    @mkdir($debugDir, 0755, true);
    $label = "$polyType-s$scale-a$angle";
    $svgFile = "$debugDir/debug_$label.svg";
    Templates::fillCheckDebugSVG($rotated, $checkResult, $svgFile);
    echo "SVG: $svgFile\n";
}
