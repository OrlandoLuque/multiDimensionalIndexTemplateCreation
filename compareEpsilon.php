<?php
/**
 * Comparison test: exact mode vs epsilon mode.
 *
 * For each polygon/scale/grid/angle combination, generates the template grid
 * twice (once with exact comparisons, once with epsilon) and logs any differences.
 *
 * Usage: php compareEpsilon.php [angleStep] [maxAngles]
 *   angleStep  - degrees between angles (default: 0.5)
 *   maxAngles  - max angles to test (default: all 720)
 *
 * Examples:
 *   php compareEpsilon.php              Full comparison (slow)
 *   php compareEpsilon.php 5 72         Quick test: every 5 degrees
 *   php compareEpsilon.php 0.5 100      First 100 angles at 0.5 step
 *
 * Author: Orlando Jose Luque Moraira
 */

require_once(__DIR__ . '/libs/polygon.php');
require_once(__DIR__ . '/libs/matrix-utils.php');
require_once(__DIR__ . '/libs/Templates.php');

$angleStep = (float)($argv[1] ?? 0.5);
$maxAngles = (int)($argv[2] ?? 0);

$logFile = __DIR__ . '/epsilon_comparison.log';
$logHandle = fopen($logFile, 'w');

$polygons = [
    'drop'   => Templates::getDropPolygonWithDimensions(1/5, 4/5),
    'box'    => Templates::getSquarePolygonWithDimensions(1),
    'circle' => Templates::getCircleWithRadius(1),
];

$scales = [128];
$gridSizes = [16];
$angles = Templates::getAnglesToTest($angleStep);
if ($maxAngles > 0) $angles = array_slice($angles, 0, $maxAngles);

$totalDifferences = 0;
$totalCells = 0;
$totalTemplates = 0;

echo "=== Epsilon Comparison Test ===\n";
echo "Angles: " . count($angles) . " (step: {$angleStep}°)\n";
echo "Polygons: " . implode(', ', array_keys($polygons)) . "\n";
echo "Scales: " . implode(', ', $scales) . "\n";
echo "Grids: " . implode(', ', $gridSizes) . "\n";
echo "Log: $logFile\n\n";

fwrite($logHandle, "=== Epsilon Comparison Test ===\n");
fwrite($logHandle, "Date: " . date('Y-m-d H:i:s') . "\n");
fwrite($logHandle, "Epsilon value: " . Intersector::$MyEpsilon . "\n\n");

foreach ($polygons as $polyName => $poly) {
    foreach ($scales as $scale) {
        $scaledPoly = Templates::getScalatedPolygonCopy($poly, $scale, $scale);

        foreach ($gridSizes as $gridSize) {
            $gridX = $gridSize;
            $gridY = $gridSize;

            echo "Processing: $polyName s$scale ${gridX}x${gridY}...\n";

            foreach ($angles as $angle) {
                $rotatedPoly = Templates::getRotatedPolygonCopy($scaledPoly, Templates::angleToRadians($angle));

                // Test at position (0, 0)
                $movedPoly = $rotatedPoly->copy_poly();
                $movedPoly->move(0, 0);
                $box = $movedPoly->bRect();
                $boxVertex = [
                    0 => ['x' => $box->first->x, 'y' => $box->first->y],
                    1 => ['x' => $box->first->nextV->nextV->x, 'y' => $box->first->nextV->nextV->y]
                ];
                $gridXRange = [floor($boxVertex[0]['x'] / $gridX), ceil($boxVertex[1]['x'] / $gridX)];
                $gridYRange = [floor($boxVertex[0]['y'] / $gridY), ceil($boxVertex[1]['y'] / $gridY)];
                $grid = Templates::getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);

                // Run in EXACT mode
                Intersector::$epsilonMode = false;
                $exactResult = Templates::getTemplateGrid($grid, $movedPoly);

                // Run in EPSILON mode
                Intersector::$epsilonMode = true;
                $epsilonResult = Templates::getTemplateGrid($grid, $movedPoly);

                // Reset to exact mode
                Intersector::$epsilonMode = false;

                // Compare results
                $totalTemplates++;
                $diffs = [];
                foreach ($exactResult as $ix => $column) {
                    foreach ($column as $iy => $exactVal) {
                        $totalCells++;
                        $epsilonVal = $epsilonResult[$ix][$iy] ?? -1;
                        if ($exactVal !== $epsilonVal) {
                            $labels = [0 => 'OUT', 1 => 'MAYBE', 2 => 'IN'];
                            $diffs[] = [
                                'cell' => "[$ix][$iy]",
                                'exact' => $labels[$exactVal] ?? $exactVal,
                                'epsilon' => $labels[$epsilonVal] ?? $epsilonVal,
                            ];
                        }
                    }
                }

                if (!empty($diffs)) {
                    $totalDifferences += count($diffs);
                    $msg = "$polyName-s$scale-${gridX}x${gridY}-a$angle: " . count($diffs) . " difference(s)\n";
                    echo "  DIFF at angle $angle: " . count($diffs) . " cell(s)\n";
                    fwrite($logHandle, $msg);
                    foreach ($diffs as $d) {
                        $detail = "  {$d['cell']}: {$d['exact']} -> {$d['epsilon']}\n";
                        fwrite($logHandle, $detail);
                    }
                    fwrite($logHandle, "\n");
                }
            }
        }
    }
}

echo "\n=== Results ===\n";
echo "Templates compared: $totalTemplates\n";
echo "Cells compared: $totalCells\n";
echo "Differences found: $totalDifferences\n";
if ($totalDifferences > 0) {
    echo "Difference rate: " . round($totalDifferences / $totalCells * 100, 4) . "%\n";
}
echo "Log written to: $logFile\n";

fwrite($logHandle, "\n=== Summary ===\n");
fwrite($logHandle, "Templates compared: $totalTemplates\n");
fwrite($logHandle, "Cells compared: $totalCells\n");
fwrite($logHandle, "Differences found: $totalDifferences\n");
if ($totalDifferences > 0) {
    fwrite($logHandle, "Difference rate: " . round($totalDifferences / $totalCells * 100, 4) . "%\n");
}
fclose($logHandle);
