<?php

include_once('matrix-utils.php');

define("OUT", 0);
define("IN", 2);
define("MAYBE", 1);

$matrixClass = 'MatrixUtil';
// rC <==> rCC por ser contrarias mientras que todas las
// demás son 'reflejas': si las repites una vez se deshace
$matrixMethods = ['equal' => 'eq', 'rotateClockwise90' => 'rCC'
    , 'rotateCounterClockwise90' => 'rC', 'rotate180' => 'r180'
    , 'flipLR' => 'fLR', 'flipTB' => 'fTB'
    , 'flipTLBR' => 'fTLBR', 'flipTRBL' => 'fTRBL'];
$matrixMethodsIndex = ['eq', 'rCC'
    , 'rC', 'r180'
    , 'fLR', 'fTB'
    , 'fTLBR', 'fTRBL'];

class Templates
{
    public function __construct()
    {
    }

    #region Generating

    /** Executes task
     * @param Task $task
     * @param bool $printNextAndDie
     * @param string|null $forceLast
     * @return void
     */
    public static function generateAndPersist(Task $task, bool $printNextAndDie = false, string $forceLast = null, int $taskNumber = 0, int $totalTasks = 0): void
    {
        $inicio = date("Y-m-d H:i:s");

        $lastTime = null;


        if (false) {
            $result = Database::connect([
                'username' => 'root'
            ]);

            Database::query("
                CREATE DATABASE `orlando-Luque` /*!40100 COLLATE 'utf8_bin' */;
            ");
        }
        //Connecting to Redis server on localhost
        if (empty($task->redis)) {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
        } else {
            $redis = $task->redis;
        }

        ///////$redis->flushDB();

        ////echo "Connection to server sucessfully done\n\n";
        //check whether server is running or not
        ////echo "Server is running: " . $redis->ping() . "\n";
        ////$redis->set("japapa", "japapa japa jacaca caca", 120);
        ////echo $redis->get("japapa") . "\n";
        ////$redis->del("japapa");


        /*
        $r = $redis->incr('i');
        $r = $redis->get('i');

        $r = $redis->lLen('1');
        $r = $redis->rPush('1', '1', '2', '3');
        $r = $redis->rPush('1', '1');
        $r = $redis->lLen('1');
        $r = $redis->get('1');
        $r = $redis->lLen('1');

        $r = $redis->set('1', null);
        $r = $redis->get('1');
        $r = $r;*/
        /*
        $r = $redis->set("
        UiiiiU", "hola");
        $r = $redis->del("
        UiiiiU", "hola");
        */
        //$redis->hSet("
        //UiiiiU", "hola");
        /*
        $mc = new memcache();
        $mc->addServer("localhost", 11211);

        $mc->set("foo", "Hello!");
        $mc->set("bar", "Memcached...");

        $arr = array(
            $mc->get("foo"),
            $mc->get("bar")
        );
        var_dump($arr);
        */
        //redisStoreOnlyNewTemplates
        //$redis->flushDB();


        $templateCount = $redis->lLen($task->templateListKey);
        $calculatedTemplates = $redis->lLen($task->generationSetKey);
        $last = $redis->get($task->lastTemplateKey);
        if (false === $templateCount) {
            $templateCount = 0;
        }
        if (false === $calculatedTemplates) {
            $calculatedTemplates = 0;
        }

        if (!empty($forceLast)) {
            $last = $forceLast;
        }

        // Calculate total combinations for progress display
        $totalCombinations = 0;
        foreach ($task->polygons as $poly) {
            foreach ($task->polygonScales as $polygonScale) {
                foreach ($task->gridsDimensions as $gridDimensions) {
                    $totalCombinations += count($task->angles) * $gridDimensions[0] * $gridDimensions[1];
                }
            }
        }

        // Describe task contents
        $polyNames = array_keys($task->polygons);
        $scaleList = implode(', ', $task->polygonScales);
        $gridList = implode(', ', array_map(fn($g) => "{$g[0]}x{$g[1]}", $task->gridsDimensions));
        $taskLabel = $totalTasks > 0 ? " (task $taskNumber / $totalTasks)" : '';

        echo "\n\n=== {$task->taskKey}$taskLabel ===\n";
        echo "  Polygons: " . implode(', ', $polyNames) . " | Scales: $scaleList | Grids: $gridList | Angles: " . count($task->angles) . "\n";
        echo "  $totalCombinations combinations | $templateCount templates so far | $calculatedTemplates already processed\n";
        if ($last) echo "  Resuming from: $last\n";
        echo "\n";
        $continue = false;
        if (false !== $last && strlen($last) > 0) {
            $continue = true;
            $data = explode('-', $last);
            $last = [];
            $last[0] = $data[0];
            $last[1] = ltrim($data[1], 's');
            $coord = explode(',', $data[2]);
            $last[2] = ltrim($coord[0], 'x');
            $last[3] = ltrim($coord[1], 'y');
            $last[4] = ltrim($data[3], 'a');
            $disp = explode(',', $data[4]);
            $last[5] = ltrim($disp[0], 'dx');
            $last[6] = ltrim($disp[1], 'dy');
        }

        foreach ($task->polygons as $indexPoly => $poly) {
            if ($continue && $indexPoly != $last[0]) {
                continue;
            }
            foreach ($task->polygonScales as $polygonScale) {
                if ($continue && $polygonScale != $last[1]) {
                    continue;
                }
                $scalatedPoly = Templates::getScalatedPolygonCopy($poly, $polygonScale, $polygonScale);
                //$scalatedPoly2 = getScalatedPolygonCopy($poly, 32, 32); /////////////////
                foreach ($task->gridsDimensions as $gridDimensions) {
                    $gridX = $gridDimensions[0];
                    $gridY = $gridDimensions[1];
                    if ($continue && !($gridX == $last[2] && $gridY == $last[3])) {
                        continue;
                    }
                    /** @var float $angle */
                    foreach ($task->angles as $angleIndex => $angle) {
                        if ($continue && $angle != $last[4]) {
                            continue;
                        }
                        $rotatedPoly = Templates::getRotatedPolygonCopy($scalatedPoly, Templates::angleToRadians($angle));
                        //$rotatedPoly2 = getRotatedPolygonCopy($scalatedPoly2, angleToRadians($angle)); /////////////////
                        $fillCheckResult = self::checkNoLinesInPolygonFilling($rotatedPoly);
                        $fillCheckAnomaly = ($fillCheckResult !== true);
                        if ($fillCheckAnomaly) {
                            $fillCheckMode = getenv('MDIC_FILL_CHECK') ?: 'stop';
                            $fillCheckDebug = getenv('MDIC_FILL_CHECK_DEBUG') === '1';
                            $fillCheckMsg = "$indexPoly-s$polygonScale-x$gridX,y$gridY-a$angle";
                            $anomalyCount = count($fillCheckResult['anomalies']);
                            $anomalyLog = self::formatFillCheckAnomalyLog(
                                $fillCheckResult['anomalies'], $indexPoly, $polygonScale, $gridX, $gridY, $angle
                            );

                            if ($fillCheckMode === 'log') {
                                // Log mode: always generate debug images + write to log file + continue storing
                                $fillCheckDebug = true;
                            }

                            if (!$fillCheckDebug) {
                                // No debug images: apply policy immediately
                                if ($fillCheckMode === 'stop') {
                                    echo "\nERROR: fill check anomaly for $fillCheckMsg ($anomalyCount points)\n";
                                    echo $anomalyLog;
                                    echo "  Configure fillCheckPolicy in config.json: \"stop\", \"log\", \"skip\", or \"ignore\"\n";
                                    die();
                                } elseif ($fillCheckMode === 'skip') {
                                    echo "\nWARNING: fill check anomaly for $fillCheckMsg ($anomalyCount points, skipping angle)\n";
                                    echo $anomalyLog;
                                    $calculatedTemplates += $gridX * $gridY;
                                    continue;
                                }
                                echo "\nNOTICE: fill check anomaly for $fillCheckMsg ($anomalyCount points, storing anyway)\n";
                                echo $anomalyLog;
                            }
                            // With debug images (or log mode): continue to first position to generate template image
                        }
                        for ($x = 0; $x < $gridX; $x++) {
                            for ($y = 0; $y < $gridY; $y++) {
                                if ($continue) {
                                    $x = $last[5];
                                    $y = $last[6];
                                    $continue = false;
                                    continue;
                                }
                                $r = $redis->eval($task->keepTaskLockedScript, [$task->taskKey]);
                                $generationSetString = "$indexPoly-s$polygonScale-x$gridX,y$gridY-a$angle-dx$x,dy$y";
                                $movedPoly = $rotatedPoly->copy_poly();
                                //$movedPoly2 = $rotatedPoly2->copy_poly(); /////////////////
                                $movedPoly->move($x, $y);
                                //$movedPoly2->move($x, $y); /////////////////
                                $box = $movedPoly->bRect();
                                $boxVertex = [0 => ['x' => $box->first->x, 'y' => $box->first->y]
                                    , 1 => ['x' => $box->first->nextV->nextV->x, 'y' => $box->first->nextV->nextV->y]];
                                $gridXRange = [floor($boxVertex[0]['x'] / $gridX), ceil($boxVertex[1]['x'] / $gridX)];
                                $gridYRange = [floor($boxVertex[0]['y'] / $gridY), ceil($boxVertex[1]['y'] / $gridY)];

                                $templateGridXY = Templates::getTemplateGridFast(
                                    $gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1],
                                    $gridX, $gridY, $movedPoly
                                );
                                // Keep $grid for debug images (generated lazily only on anomaly)
                                $grid = null;

                                // Generate debug image on first position of an anomalous angle
                                if ($fillCheckAnomaly && $fillCheckDebug && $x === 0 && $y === 0) {
                                    $debugDir = (getenv('MDIC_OUTPUT_DIR') ?: '.') . '/fill_check_debug';
                                    @mkdir($debugDir, 0755, true);
                                    // Generate grid lazily for debug images
                                    $grid = Templates::getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);
                                    // Template images (IN/MAYBE/OUT grid)
                                    $debugTemplatePNG = "$debugDir/$fillCheckMsg.png";
                                    $debugTemplateSVG = "$debugDir/$fillCheckMsg.svg";
                                    Templates::templateToImage(
                                        $gridXRange, $gridX, $gridYRange, $gridY,
                                        $grid, $templateGridXY, $movedPoly, $debugTemplatePNG
                                    );
                                    Templates::templateToSVG(
                                        $gridXRange, $gridX, $gridYRange, $gridY,
                                        $grid, $templateGridXY, $movedPoly, $debugTemplateSVG
                                    );
                                    // Fill check images (isInside pixel-level)
                                    $debugFillPNG = "$debugDir/{$fillCheckMsg}_fill.png";
                                    $debugFillSVG = "$debugDir/{$fillCheckMsg}_fill.svg";
                                    self::fillCheckDebugImage($rotatedPoly, $fillCheckResult, $debugFillPNG);
                                    self::fillCheckDebugSVG($rotatedPoly, $fillCheckResult, $debugFillSVG);
                                    echo "\n  Debug images ($anomalyCount anomalies):";
                                    echo "\n    Template PNG: $debugTemplatePNG";
                                    echo "\n    Template SVG: $debugTemplateSVG";
                                    echo "\n    Fill PNG:     $debugFillPNG";
                                    echo "\n    Fill SVG:     $debugFillSVG\n";
                                    echo $anomalyLog;

                                    // Write to log file
                                    $logFile = "$debugDir/anomalies.log";
                                    $logEntry = "[" . date('Y-m-d H:i:s') . "] $fillCheckMsg ($anomalyCount points)\n";
                                    $logEntry .= "  Images: $debugTemplatePNG | $debugFillSVG\n";
                                    $logEntry .= $anomalyLog . "\n";
                                    file_put_contents($logFile, $logEntry, FILE_APPEND);

                                    if ($fillCheckMode === 'stop') {
                                        echo "\nERROR: fill check anomaly for $fillCheckMsg ($anomalyCount points)\n";
                                        echo "  Configure fillCheckPolicy in config.json: \"stop\", \"log\", \"skip\", or \"ignore\"\n";
                                        die();
                                    } elseif ($fillCheckMode === 'skip') {
                                        echo "\nWARNING: fill check anomaly for $fillCheckMsg ($anomalyCount points, skipping angle)\n";
                                        $calculatedTemplates += $gridX * $gridY;
                                        continue 3; // break out of x, y loops and continue angle loop
                                    }
                                    // 'log' or 'ignore': continue storing
                                    echo "\nNOTICE: fill check anomaly for $fillCheckMsg ($anomalyCount points, storing anyway)\n";
                                    $fillCheckAnomaly = false; // don't trigger again for other positions of this angle
                                }
                                ///////////////////////////
                                if (false) {
                                    $imageFilename = 'gen_poly_ex_fill_figure.gif';
                                    $imageFilename2 = 'gen_poly_ex_fill_figure2.gif';
                                    list($polyA, $r1, $p5, $r2) = self::checkIsInsidePolygon($movedPoly2, $im, $colors, $img, $col);
                                    if ($r1) {
                                        echo '<p><div align="center"><strong>EXAMPLE  - poligon used on example XX</strong><br><img src="'
                                            . $imageFilename . '" style="image-rendering: pixelated" width="' . ($polyA->x_max + $extraMargin) * 4 . '" height="' . ($polyA->y_max + $extraMargin) * 4 . '"><br></div></p>';
                                    }
                                    if ($r2) {
                                        echo '<p><div align="center"><strong>EXAMPLE Z - vertex is inside</strong><br><img src="'
                                            . $imageFilename2 . '" style="image-rendering: pixelated" width="'
                                            . ($polyA->x_max + $extraMargin) * 4 . '" height="'
                                            . ($polyA->y_max + $extraMargin) * 4 . '"><br></div></p>';
                                    }
                                    die();
                                }
                                //$hashXY = MatrixUtil::toString($templateGridXY);
                                //echo $hashXY;
                                //$test = MatrixUtil::binCode($templateGridXY);
                                //$decodedTemplateGrid = MatrixUtil::binDecode($test);
                                //echo MatrixUtil::toString($decodedTemplateGrid);

                                // Optional template validation: cross-check IN/OUT cells with isInside()
                                if (getenv('MDIC_TEMPLATE_VALIDATION') === '1') {
                                    if ($grid === null) {
                                        $grid = Templates::getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);
                                    }
                                    self::validateTemplateGrid($grid, $templateGridXY, $movedPoly, $generationSetString);
                                }

                                echo "\n{$task->taskKey} $generationSetString -->\n" . MatrixUtil::toString($templateGridXY);
                                $templateHash = MatrixUtil::binCode($templateGridXY);
                                /*storeOnlyNewTemplatesInMemory($sourceHash, $hashXY
                                    , $hashToTemplatesIdDictionary
                                    , $templateGridXY, $templateCount, $idToTemplatesDictionary);*/
                                if (!$printNextAndDie) {
                                    self::redisStoreOnlyNewTemplatesLUA($redis, $generationSetString/*, $templateHash*/
                                        , $templateGridXY, $task, $templateCount);
                                }
                                $calculatedTemplates++;
                                // Sync template count from Redis every 100 iterations
                                if ($calculatedTemplates % 100 === 0) {
                                    $globalCount = $redis->get($task->templateCountKey);
                                    if ($globalCount !== false) {
                                        $templateCount = (int)$globalCount;
                                    }
                                }
                                $pct = round($calculatedTemplates / $totalCombinations * 100, 1);
                                echo " {$task->taskKey} $templateCount plantillas | $calculatedTemplates / $totalCombinations ({$pct}%) ";
                                flush();
                                if ($printNextAndDie) {
                                    if ($grid === null) {
                                        $grid = Templates::getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);
                                    }
                                    $outputDir = getenv('MDIC_OUTPUT_DIR') ?: '.';
                                    $imageFilename = "$outputDir/examples/generated/$generationSetString.gif";

                                    list($imageWidth, $imageHeight, $isOk) = Templates::templateToImage(
                                        $gridXRange,
                                        $gridX,
                                        $gridYRange,
                                        $gridY,
                                        $grid,
                                        $templateGridXY,
                                        $movedPoly,
                                        $imageFilename
                                    );
                                    if ($isOk) {
                                        echo '<p><div align="center"><strong>EXAMPLE template</strong><br><img src="'
                                            . $imageFilename . '" style="image-rendering: pixelated" width="'
                                            . ($imageWidth) . '" height="' . ($imageHeight) . '"><br></div></p>';
                                    }
                                    $redis->del($task->taskKey);
                                    die();
                                }
                            }
                        }
                    }
                }
            }
        }
        $redis->eval($task->lockTaskAsCompletedScript, [$task->taskKey]);
        $globalCount = $redis->get($task->templateCountKey);
        if ($globalCount !== false) $templateCount = (int)$globalCount;
        echo "\n{$task->taskKey} completed: $calculatedTemplates combinations - $templateCount unique templates (global) - $inicio to " . date("Y-m-d H:i:s") . "\n";

        return; // array($im, $colors, $img);
    }

    /**
     * @param $grid
     * @param polygon $poly
     * @return array
     */
    /**
     * Combined grid generation + template classification.
     * Creates cell polygons only when they pass the bounding box check.
     */
    public static function getTemplateGridFast($sx, $sy, $ex, $ey, $gridX, $gridY, $poly)
    {
        $r = [];
        $bbox = $poly->bRect();
        $pxMin = $bbox->x_min; $pxMax = $bbox->x_max;
        $pyMin = $bbox->y_min; $pyMax = $bbox->y_max;
        $dx = $ex - $sx;
        $dy = $ey - $sy;

        for ($x = 0; $x < $dx; $x++) {
            $sxCell = ($sx + $x) * $gridX;
            $exCell = ($sx + $x + 1) * $gridX;
            for ($y = 0; $y < $dy; $y++) {
                $syCell = ($sy + $y) * $gridY;
                $eyCell = ($sy + $y + 1) * $gridY;

                // Bbox rejection without creating a Polygon
                if ($sxCell > $pxMax || $exCell < $pxMin
                    || $syCell > $pyMax || $eyCell < $pyMin) {
                    $r[$x][$y] = OUT;
                } else {
                    // Only create cell Polygon when needed
                    $cell = self::getSquarePolyFromXYXY($sxCell, $syCell, $exCell, $eyCell);
                    if ($poly->completelyContains($cell)) {
                        $r[$x][$y] = IN;
                    } elseif ($cell->completelyContains($poly)
                        || $poly->isPolyIntersect($cell)) {
                        $r[$x][$y] = MAYBE;
                    } else {
                        $r[$x][$y] = OUT;
                    }
                }
            }
        }
        return $r;
    }

    public static function getTemplateGrid($grid, $poly)
    {
        $r = [];
        // Cache polygon bounding box for fast rejection
        // Note: poly->move() does NOT update bounds, so recalculate from bRect
        $bbox = $poly->bRect();
        $pxMin = $bbox->x_min; $pxMax = $bbox->x_max;
        $pyMin = $bbox->y_min; $pyMax = $bbox->y_max;

        foreach ($grid as $ix => $column) {
            /** @var polygon $cell */
            foreach ($column as $iy => $cell) {
                // Fast bounding box rejection: if no overlap, cell is OUT
                if ($cell->x_min > $pxMax || $cell->x_max < $pxMin
                    || $cell->y_min > $pyMax || $cell->y_max < $pyMin) {
                    $r[$ix][$iy] = OUT;
                } elseif ($poly->completelyContains($cell)) {
                    $r[$ix][$iy] = IN;
                } elseif ($cell->completelyContains($poly)
                    || $poly->isPolyIntersect($cell)) {
                    $r[$ix][$iy] = MAYBE;
                } else {
                    $r[$ix][$iy] = OUT;
                }
            }
        }
        return $r;
    }
    public static function getTemplateGridExpecting($grid, $poly, $expected)
    {
        $r = []; //$sr = '';
        foreach ($grid as $ix => $column) {
            /** @var polygon $cell */
            foreach ($column as $iy => $cell) {
                if ($poly->completelyContains($cell)) {
                    $intersectResult = IN;
                } elseif ($cell->completelyContains($poly)
                    || $poly->isPolyIntersect($cell)) {
                    $intersectResult = MAYBE;
                } else {
                    $intersectResult = OUT;
                }
                $r[$ix][$iy] = $intersectResult;
                if ($expected[$ix][$iy] != $intersectResult) {
                    if ($poly->completelyContains($cell)) {
                        $intersectResult = IN;
                    } elseif ($cell->completelyContains($poly)
                        || $poly->isPolyIntersect($cell)) {
                        $intersectResult = MAYBE;
                    } else {
                        $intersectResult = OUT;
                    }
                }
                //$sr .= $intersectResult;
            }
            //$sr .= '|';
        }
        return $r; //[$r, $sr];
    }

    public static function getGrid($sx, $sy, $ex, $ey, $gridX, $gridY)
    {
        //$unDecimal = 0.0000000001; /////////// para 4 dígitos
        $unDecimal = 0.0;
        $dx = $ex - $sx;
        $dy = $ey - $sy;
        $grid = [];
        for ($x = 0; $x < $dx; $x++) {
            $sxCell = ($sx + $x) * $gridX;
            $exCell = ($sx + $x + 1) * $gridX - $unDecimal;
            for ($y = 0; $y < $dy; $y++) {
                $syCell = ($sy + $y) * $gridY;
                $eyCell = ($sy + $y + 1) * $gridY - $unDecimal;
                $grid[$x][$y] = self::getSquarePolyFromXYXY($sxCell, $syCell, $exCell, $eyCell);
            }
        }
        return $grid;
    }

    #endregion

    #region Persistence
    /**
     * @param Redis $redis
     * @param string $generationSetString
     * @param array $templateGridXY
     * @param Task $task
     * @param int $templateCount
     */
    private static function redisStoreOnlyNewTemplatesLUA(Redis $redis, string $generationSetString
        /*, string $templateHash*/, array $templateGridXY, $task, int &$templateCount)
    {
        global $matrixClass, $matrixMethods, $matrixMethodsIndex;
        $foundKeys = [];
        foreach ($matrixMethods as $method => $operation) {
            $matrix = call_user_func(array($matrixClass, $method), $templateGridXY);
            $foundKeys[] = MatrixUtil::binCode($matrix);
        }
        //$starttime = microtime(true);

        $prevTemplateCount = $templateCount;

        //global $script;
        //if (empty($script)) {
        //    $script = file_get_contents('../luaRedis/storeTemplate.luaRedis');
        //}
        //if ($templateCount >= 2) {
        //$script = file_get_contents('storeTemplateTests.luaRedis' );
        //}
        //$test = $redis->eval("return 1");
        //List($generationProcessData, $foundIndex, $templateCount, $r1, $r2, $r3, $r4)
        $result
            = $redis->eval($task->storeTemplateLUAScript, [
            $foundKeys[0], $foundKeys[1], $foundKeys[2], $foundKeys[3],
            $foundKeys[4], $foundKeys[5], $foundKeys[6], $foundKeys[7],
            $matrixMethodsIndex[0], $matrixMethodsIndex[1], $matrixMethodsIndex[2], $matrixMethodsIndex[3],
            $matrixMethodsIndex[4], $matrixMethodsIndex[5], $matrixMethodsIndex[6], $matrixMethodsIndex[7],
            $task->templateCountKey,
            $task->templateListKey,
            $task->lastTemplateKey,
            $generationSetString,
            $task->generationSetKey
        ]);
        $error = $redis->getLastError();
        list($generationProcessData, $foundIndex, $templateCount, $r1, $r2, $r3, $r4) = $result;
        //public function evalSha($scriptSha, $args = array(), $numKeys = 0)
        //* $sha = $redis->script('load', $script);
        //* $redis->evalSha($sha); // Returns 1
        //die();
        //keys: vector de binarios

        if (!empty($error)) {
            echo " --- ERROR!! - " . $$error . "\n";
            die();
        } else {
        }
        if (false === $templateCount) {
            $templateCount = $prevTemplateCount;
        }
    }

    /**
     * @param Polygon $polygon
     * @param string $emptyFilename
     * @param $filledImagefilename
     * @param $echoHTMLOutput
     * @return array
     */
    public static function polyFillTestToImage(Polygon $polygon, string $emptyFilename, $filledImagefilename, $recommendedOverScaledCalculation = 1): array
    {
        $extraMargin = 5;
        $box = $polygon->bRect();
        newImage($box->x_max - $box->x_min + $extraMargin * 2, $box->y_max - $box->y_min + $extraMargin * 2, $im, $colors);               // Create a new image to draw our polygons
        directDrawPolyAt(-$box->x_min + $extraMargin, -$box->y_min + $extraMargin, $im, $polygon, $colors, "red");
        //$r = imagesetpixel($im, 17 - $box->x_min + $extraMargin
        //    , -4 - $box->y_min + $extraMargin, $colors['red']);
        //$r = imagesetpixel($im, 42 - $box->x_min + $extraMargin
        //    , 0 - $box->y_min + $extraMargin, $colors['ora']);
        $r0 = imageflip($im, IMG_FLIP_VERTICAL);
        $r1 = imageGif($im, $emptyFilename);
        echo '<p><div align="center"><strong>EXAMPLE 2 - poligon used on example 3</strong><br><img src="poly_ex2polygon.gif" style="image-rendering: pixelated" width="'
            . ($polygon->x_max + $extraMargin) * 4 . '" height="' . ($polygon->y_max + $extraMargin) * 4 . '"><br></div></p>';

        newImage($box->x_max - $box->x_min + $extraMargin * 2, $box->y_max - $box->y_min + $extraMargin * 2, $img, $col);
        directDrawPolyAt(-$box->x_min + $extraMargin, -$box->y_min + $extraMargin, $img, $polygon, $colors, "red");
        if (1 === $recommendedOverScaledCalculation) {
            $scaledPolygon = $polygon;
        } else {
            $scaledPolygon = $polygon->copy_poly();
            $scaledPolygon->scale($recommendedOverScaledCalculation, $recommendedOverScaledCalculation);
        }
        for ($x = floor($box->x_min) - $extraMargin
                ; $x < $box->x_max + 1 + $extraMargin
                ; $x++) {
            for ($y = floor($box->y_min) - $extraMargin
                    ; $y < $box->y_max + 1 + $extraMargin
                    ; $y++) {
                //echo "\n$x - $y";
                if (1 === $recommendedOverScaledCalculation) {
                    $p5 = new Vertex($x + 0.5, $y + 0.5);
                } else {
                    $p5 = new Vertex(($x + 0.5) * $recommendedOverScaledCalculation, ($y + 0.5) * $recommendedOverScaledCalculation);
                }
                //$r1 = $polyA->isInside($p5);
                $a = 1;
                $r1 = $scaledPolygon->isInside($p5, true);
                if ($r1) {
                    $r = imagesetpixel($img, $x - $box->x_min + $extraMargin, $y - $box->y_min + $extraMargin, $col['grn']);
                } else {
                    $r = imagesetpixel($img, $x - $box->x_min + $extraMargin, $y - $box->y_min + $extraMargin, $col['blu']);
                }
            }
        }
        //$r = imagesetpixel($img, 42 - $box->x_min + $extraMargin
        //    , 0 - $box->y_min + $extraMargin, $col['red']);
        //$r = imagesetpixel($img, 17 - $box->x_min + $extraMargin
        //    , -4 - $box->y_min + $extraMargin, $col['ora']);
        $r3 = imageflip($img, IMG_FLIP_VERTICAL);
        $r2 = imageGif($img, $filledImagefilename);
        echo '<p><div align="center"><strong>EXAMPLE 3 - vertex is inside</strong><br><img src="poly_ex_vertex_inside.gif" style="image-rendering: pixelated" width="'
            . ($polygon->x_max + $extraMargin) * 4 . '" height="' . ($polygon->y_max + $extraMargin) * 4 . '"><br></div></p>';
        return [$r1, $r2];
    }

    /**
     * @param Polygon $polygon
     * @return array
     */
    public static function polyFillToArray(Polygon $polygon): array
    {
        $extraMargin = 5;
        $box = $polygon->bRect();
        $width = $box->x_max - $box->x_min + $extraMargin * 2;
        $height = $box->y_max - $box->y_min + $extraMargin * 2;
        $result = [];
        for ($x = floor($box->x_min) - $extraMargin
        ; $x < $box->x_max + 1 + $extraMargin
        ; $x++) {
            $result[] = [];
            for ($y = floor($box->y_min) - $extraMargin
            ; $y < $box->y_max + 1 + $extraMargin
            ; $y++) {
                //echo "\n$x - $y";
                $p5 = new Vertex($x, $y);
                //$r1 = $polyA->isInside($p5);
                $a = 1;
                $result[$x][$y] = $polygon->isInside($p5, true);
            }
        }
        return $result;
    }

    /**
     * @param Polygon $polygon
     * @return bool
     */
    /**
     * Returns true if fill is clean, or an array with anomaly details if not.
     * @return true|array{result: array, anomalies: array, xStart: int, yStart: int, xEnd: float, yEnd: float}
     */
    public static function checkNoLinesInPolygonFilling(Polygon $polygon)
    {
        $extraMargin = 3;
        $box = $polygon->bRect();
        $xStart = floor($box->x_min) - $extraMargin;
        $xEnd = $box->x_max + 1 + $extraMargin;
        $yStart = floor($box->y_min) - $extraMargin;
        $yEnd = $box->y_max + 1 + $extraMargin;
        $result = [];
        $lineDetection = [];
        $anomalies = [];
        for ($x = $xStart; $x < $xEnd; $x++) {
            $result[$x] = [];
            $previousLineDetection = $lineDetection;
            $lineDetection = [];
            for ($y = $yStart; $y < $yEnd; $y++) {
                $p5 = new Vertex($x, $y);
                $result[$x][$y] = $polygon->isInside($p5, true);
                if ($y > $yStart + 2) {
                    if ($result[$x][$y] == $result[$x][$y - 2] && $result[$x][$y] != $result[$x][$y - 1]) {
                        if (isset($previousLineDetection[$y]) && $previousLineDetection[$y] == $result[$x][$y]) {
                            $anomalies[] = [
                                'x' => $x, 'y' => $y,
                                'val' => $result[$x][$y],
                                // ABA pattern: y-2 and y match, y-1 differs
                                'y_minus_2' => $result[$x][$y - 2],
                                'y_minus_1' => $result[$x][$y - 1],
                                // Previous column had same pattern
                                'prev_x' => $x - 1,
                            ];
                        }
                        $lineDetection[$y] = $result[$x][$y];
                    }
                }
            }
        }
        if (!empty($anomalies)) {
            return ['result' => $result, 'anomalies' => $anomalies,
                'xStart' => $xStart, 'yStart' => $yStart, 'xEnd' => $xEnd, 'yEnd' => $yEnd];
        }
        return true;
    }

    /**
     * Generates a debug PNG for a fill check anomaly (GD library).
     */
    public static function fillCheckDebugImage(Polygon $polygon, array $checkData, string $filename): void
    {
        $cellSize = 6;
        $result = $checkData['result'];
        $anomalies = $checkData['anomalies'];
        $xStart = $checkData['xStart'];
        $yStart = $checkData['yStart'];
        $xEnd = $checkData['xEnd'];
        $yEnd = $checkData['yEnd'];

        $cols = (int)($xEnd - $xStart);
        $rows = (int)($yEnd - $yStart);
        $imgW = $cols * $cellSize + 1;
        $imgH = $rows * $cellSize + 1;

        $im = imagecreatetruecolor($imgW, $imgH);
        $cWhite = imagecolorallocate($im, 255, 255, 255);
        $cGreen = imagecolorallocate($im, 60, 180, 75);
        $cBlue  = imagecolorallocate($im, 70, 130, 200);
        $cGrid  = imagecolorallocate($im, 200, 200, 200);
        $cPoly  = imagecolorallocate($im, 0, 0, 0);
        $cAnomalyBorder = imagecolorallocate($im, 220, 50, 50);
        $cMidBorder     = imagecolorallocate($im, 255, 140, 0);

        imagefill($im, 0, 0, $cWhite);

        $anomalyMap = [];
        foreach ($anomalies as $a) {
            $anomalyMap[$a['x'] . ',' . $a['y']] = 'confirmed';
            $anomalyMap[$a['x'] . ',' . ($a['y'] - 1)] = 'mid';
        }

        for ($x = $xStart; $x < $xEnd; $x++) {
            for ($y = $yStart; $y < $yEnd; $y++) {
                $px = (int)(($x - $xStart) * $cellSize);
                $py = (int)(($rows - 1 - ($y - $yStart)) * $cellSize);
                $color = (isset($result[$x][$y]) && $result[$x][$y]) ? $cGreen : $cBlue;
                imagefilledrectangle($im, $px + 1, $py + 1, $px + $cellSize - 1, $py + $cellSize - 1, $color);
                $key = $x . ',' . $y;
                if (isset($anomalyMap[$key])) {
                    $bc = ($anomalyMap[$key] === 'confirmed') ? $cAnomalyBorder : $cMidBorder;
                    imagerectangle($im, $px, $py, $px + $cellSize, $py + $cellSize, $bc);
                    imagerectangle($im, $px + 1, $py + 1, $px + $cellSize - 1, $py + $cellSize - 1, $bc);
                }
            }
        }
        for ($i = 0; $i <= $cols; $i++) imageline($im, $i * $cellSize, 0, $i * $cellSize, $imgH - 1, $cGrid);
        for ($i = 0; $i <= $rows; $i++) imageline($im, 0, $i * $cellSize, $imgW - 1, $i * $cellSize, $cGrid);

        $half = $cellSize / 2;
        $v = $polygon->first;
        do {
            $next = $v->nextV;
            $px1 = (int)(($v->x - $xStart) * $cellSize + $half);
            $py1 = (int)(($rows - ($v->y - $yStart)) * $cellSize - $half);
            $px2 = (int)(($next->x - $xStart) * $cellSize + $half);
            $py2 = (int)(($rows - ($next->y - $yStart)) * $cellSize - $half);
            if ($v->d() == 0) {
                imageline($im, $px1, $py1, $px2, $py2, $cPoly);
            } else {
                $cx = (int)(($v->Xc() - $xStart) * $cellSize + $half);
                $cy = (int)(($rows - ($v->Yc() - $yStart)) * $cellSize - $half);
                $dia = (int)(2 * Polygon::dist($v->X(), $v->Y(), $v->Xc(), $v->Yc()) * $cellSize);
                $s = 360 - rad2deg(Polygon::angle($v->Xc(), $v->Yc(), $v->X(), $v->Y()));
                $e = 360 - rad2deg(Polygon::angle($v->Xc(), $v->Yc(), $next->X(), $next->Y()));
                if ($v->d() == -1) {
                    imagearc($im, $cx, $cy, $dia, $dia, (int)$s, (int)$e, $cPoly);
                } else {
                    imagearc($im, $cx, $cy, $dia, $dia, (int)$e, (int)$s, $cPoly);
                }
            }
            $v = $next;
        } while ($v !== $polygon->first);

        imagepng($im, $filename);
        imagedestroy($im);
    }

    /**
     * Generates a debug SVG for a fill check anomaly.
     * Precise arcs, zoomable, no GD dependency.
     */
    public static function fillCheckDebugSVG(Polygon $polygon, array $checkData, string $filename): void
    {
        $cs = 10; // cell size in SVG units
        $result = $checkData['result'];
        $anomalies = $checkData['anomalies'];
        $xStart = $checkData['xStart'];
        $yStart = $checkData['yStart'];
        $xEnd = $checkData['xEnd'];
        $yEnd = $checkData['yEnd'];

        $cols = (int)($xEnd - $xStart);
        $rows = (int)($yEnd - $yStart);
        $w = $cols * $cs;
        $h = $rows * $cs;

        // Build anomaly lookup
        $anomalyMap = [];
        foreach ($anomalies as $a) {
            $anomalyMap[$a['x'] . ',' . $a['y']] = 'confirmed';
            $anomalyMap[$a['x'] . ',' . ($a['y'] - 1)] = 'mid';
        }

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$w\" height=\"$h\" viewBox=\"0 0 $w $h\">\n";
        $svg .= "<rect width=\"$w\" height=\"$h\" fill=\"white\"/>\n";

        // Cells
        for ($x = $xStart; $x < $xEnd; $x++) {
            for ($y = $yStart; $y < $yEnd; $y++) {
                $px = ($x - $xStart) * $cs;
                $py = ($rows - 1 - ($y - $yStart)) * $cs; // flip Y
                $fill = (isset($result[$x][$y]) && $result[$x][$y]) ? '#3cb44b' : '#4682c8';

                $key = $x . ',' . $y;
                $stroke = '#c8c8c8';
                $strokeWidth = '0.3';
                if (isset($anomalyMap[$key])) {
                    $stroke = ($anomalyMap[$key] === 'confirmed') ? '#dc3232' : '#ff8c00';
                    $strokeWidth = '2';
                }
                $svg .= "<rect x=\"$px\" y=\"$py\" width=\"$cs\" height=\"$cs\" fill=\"$fill\" stroke=\"$stroke\" stroke-width=\"$strokeWidth\"/>\n";
            }
        }

        // Polygon outline — vertices offset by half cell to align with cell centers
        $half = $cs / 2;
        $pathD = '';
        $v = $polygon->first;
        $first = true;
        do {
            $next = $v->nextV;
            $sx = ($v->x - $xStart) * $cs + $half;
            $sy = ($rows - ($v->y - $yStart)) * $cs - $half;
            $ex = ($next->x - $xStart) * $cs + $half;
            $ey = ($rows - ($next->y - $yStart)) * $cs - $half;

            if ($first) {
                $pathD .= "M $sx $sy ";
                $first = false;
            }

            if ($v->d() == 0) {
                $pathD .= "L $ex $ey ";
            } else {
                // SVG arc: A rx ry x-rotation large-arc-flag sweep-flag x y
                $r = Polygon::dist($v->X(), $v->Y(), $v->Xc(), $v->Yc()) * $cs;
                // sweep-flag: SVG 1 = clockwise in screen coords (Y-down)
                // d=-1 is clockwise in math coords (Y-up) = clockwise in screen (Y flipped) = sweep 1
                $sweep = ($v->d() == -1) ? 1 : 0;
                // large-arc: use angle span to determine
                $arcAngle = abs(rad2deg(
                    Polygon::angle($v->Xc(), $v->Yc(), $next->X(), $next->Y())
                    - Polygon::angle($v->Xc(), $v->Yc(), $v->X(), $v->Y())
                ));
                if ($arcAngle > 180) $arcAngle = 360 - $arcAngle;
                $largeArc = ($arcAngle > 180) ? 1 : 0;
                $pathD .= "A $r $r 0 $largeArc $sweep $ex $ey ";
            }
            $v = $next;
        } while ($v !== $polygon->first);
        $pathD .= 'Z';

        $svg .= "<path d=\"$pathD\" fill=\"none\" stroke=\"black\" stroke-width=\"1.5\"/>\n";
        $svg .= "</svg>\n";

        // Change extension to .svg
        $filename = preg_replace('/\.png$/i', '.svg', $filename);
        file_put_contents($filename, $svg);
    }

    /**
     * Validates a template grid by sampling points inside each cell and cross-checking
     * with isInside(). Logs errors when IN/OUT classifications disagree with point sampling.
     */
    public static function validateTemplateGrid(array $grid, array $templateGridXY, Polygon $poly, string $label): void
    {
        $errors = [];
        foreach ($grid as $ix => $column) {
            /** @var Polygon $cell */
            foreach ($column as $iy => $cell) {
                $val = $templateGridXY[$ix][$iy];
                if ($val === MAYBE) continue; // partial is expected to have mixed results

                // Sample 5 points: center + 4 midpoints of edges
                $cx = ($cell->x_min + $cell->x_max) / 2;
                $cy = ($cell->y_min + $cell->y_max) / 2;
                $samples = [
                    [$cx, $cy],
                    [($cell->x_min + $cx) / 2, $cy],
                    [($cell->x_max + $cx) / 2, $cy],
                    [$cx, ($cell->y_min + $cy) / 2],
                    [$cx, ($cell->y_max + $cy) / 2],
                ];

                foreach ($samples as $s) {
                    $inside = $poly->isInside(new Vertex($s[0], $s[1]), true);
                    if ($val === IN && !$inside) {
                        $errors[] = "  IN cell [$ix][$iy] has point ({$s[0]},{$s[1]}) OUTSIDE polygon";
                        break;
                    }
                    if ($val === OUT && $inside) {
                        $errors[] = "  OUT cell [$ix][$iy] has point ({$s[0]},{$s[1]}) INSIDE polygon";
                        break;
                    }
                }
            }
        }
        if (!empty($errors)) {
            echo "\nTEMPLATE VALIDATION ERRORS for $label:\n";
            foreach ($errors as $e) echo "$e\n";
            echo "  Total: " . count($errors) . " cell(s) with mismatched classification\n\n";
        }
    }

    /**
     * Formats a reproducible log for fill check anomalies.
     * Outputs polygon params, angle, and exact coordinates + isInside results
     * for each anomaly so the ray-casting call can be replicated for debugging.
     */
    private static function formatFillCheckAnomalyLog(
        array $anomalies, $polyIndex, $scale, $gridX, $gridY, $angle
    ): string {
        $log = "  --- Fill check anomaly debug data ---\n";
        $log .= "  Polygon index: $polyIndex | Scale: $scale | Grid: {$gridX}x{$gridY} | Angle: {$angle}°\n";
        $log .= "  To reproduce: rotate polygon $polyIndex by " . $angle . " degrees, scale by $scale, then call isInside() on the coordinates below.\n\n";

        foreach ($anomalies as $i => $a) {
            $inside = $a['val'] ? 'true' : 'false';
            $mid = $a['y_minus_1'] ? 'true' : 'false';
            $log .= "  Anomaly #$i at ({$a['x']}, {$a['y']}):\n";
            $log .= "    isInside({$a['x']}, " . ($a['y'] - 2) . ") = {$inside}  (y-2)\n";
            $log .= "    isInside({$a['x']}, " . ($a['y'] - 1) . ") = {$mid}     (y-1, suspected wrong)\n";
            $log .= "    isInside({$a['x']}, {$a['y']})   = {$inside}  (y)\n";
            $log .= "    Same ABA pattern confirmed in column x=" . ($a['prev_x']) . "\n\n";
        }

        return $log;
    }


    /**
     * @param Redis $redis
     * @param string $generationSetString
     * @param array $templateGridXY
     * @param string $templateCountKey
     * @param string $LastTemplateKey
     * @param string $templateListKey
     * @param string $generationSetKey
     * @param int $templateCount
     */
    private static function redisStoreOnlyNewTemplates(Redis $redis, string $generationSetString
        /*, string $templateHash*/, array $templateGridXY, string $templateCountKey, string $LastTemplateKey, string $templateListKey, string $generationSetKey, int &$templateCount)
    {
        global $matrixClass, $matrixMethods, $matrixMethodsIndex;
        $keys = [];
        foreach ($matrixMethods as $method => $operation) {
            $matrix = call_user_func(array($matrixClass, $method), $templateGridXY);
            $keys[] = MatrixUtil::binCode($matrix);
        }
        $templateId = null;
        //$starttime = microtime(true);
        $searchResult = $redis->mget($keys);
        $found = false;
        for ($i = 0; $i < count($searchResult); $i++) {
            //$result = $searchResult[$i];
            if (false !== $searchResult[$i]) {
                $found = $i;
                $templateId = $searchResult[$found];
                //echo 'repeated! ' . (count($found) > 0? " doing a {$matrixMethods[$found]}": "id $templateId");
                break;
            }
        }
        if (false === $found) {
            $templateId = $redis->get($templateCountKey);
            $redis->multi();
            //echo "++++++ with id $templateCount";
            echo "++";
            $templateCount++;
            /*$templateId = */$redis->incr($templateCountKey);
            $redis->set($keys[0], $templateId);
            $redis->rPush($templateListKey, $keys[0]);
            $found = 0;
        } else {
            $redis->multi();
        }
        //$endtime = microtime(true);

        $generationProcessData = $generationSetString
            . '->' . $matrixMethodsIndex[$found] . '->' . $templateId;
        $r = $redis->rPush($generationSetKey, $generationProcessData);
        $redis->set($LastTemplateKey, $generationSetString);
        $r = $redis->exec();
        $r2 = $redis->save();
        echo $generationProcessData . "\n";
        if ($r === false) {
            echo " --- ERROR!! - " . $redis->getLastError() . "\n";
            die();
        } else {
            foreach ($r as $value) {
                if (false === $value) {
                    echo " --- ERROR!! - " . $redis->getLastError() . "\n";
                    die();
                }
            }
        }
    }

    /**
     * @param string $sourceHash
     * @param string $hashXY
     * @param array $hashToTemplatesIdDictionary
     * @param array $templateGridXY
     * @param int $templateCount
     * @param array $idToTemplatesDictionary
     * @deprecated
     */
    public function storeOnlyNewTemplatesInMemory(string $sourceHash, string $hashXY, array &$hashToTemplatesIdDictionary, array $templateGridXY, int &$templateCount, array $idToTemplatesDictionary)
    {
        global $matrixClass, $matrixMethods;

        //$found = false;
        foreach ($matrixMethods as $method => $operation) {
            $matrix = call_user_func(array($matrixClass, $method), $templateGridXY);
            //$nHash = MatrixUtil::toString($matrix);
            $nHash = MatrixUtil::binCode($matrix);
            if (!empty($hashToTemplatesIdDictionary[$nHash])) {
                echo 'repeated! ' . (count($operation) > 0 ? " doing a $operation" : '');
                $hashToTemplatesIdDictionary[$hashXY] = [$hashToTemplatesIdDictionary[$nHash][0], $operation];
                //return $templateCount;
                return;
                //$found = true;
                //break;
            }
        }
        //if (!$found) {
        echo "+++++++++++++++++++++ ";
        $hashToTemplatesIdDictionary[$hashXY] = [$templateCount, ''];
        //$idToTemplatesDictionary[$templateCount] = $templateGridXY;
        $templateCount++;
        //return $templateCount;
        //}
    }
    #endregion

    #region Utility
    public static function getGridsFromSupportedSizes($gridSupportedSizes, $horizontal = false, $vertical = false)
    {
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
    public static function getDropPolygonWithDimensions($radius, $length)
    {
        $dropPoly = new polygon();        // Create a new polygon and add some vertices to it
        $dropPoly->addv(-$radius, $length, 0, $length, -1);        // Arc with center 60,90 Clockwise
        $dropPoly->addv($radius, $length);
        $dropPoly->addv(0, 0);
        return $dropPoly;
    }

    public static function getCircleWithRadius($radius)
    {
        $circlePoly = new polygon();        // Create a new polygon and add some vertices to it
        $circlePoly->addv(0, -$radius, 0, 0, -$radius);        // Arc with center 60,90 Clockwise
        $circlePoly->addv(0, $radius, 0, 0, -$radius);
        return $circlePoly;
    }

    public static function getSquarePolygonWithDimensions($sideLength)
    {
        $sideLength /= 2;
        /*$boxPoly = new polygon();
        $boxPoly->addv(-$sideLength, -$sideLength);
        $boxPoly->addv($sideLength, -$sideLength);
        $boxPoly->addv($sideLength, $sideLength);
        $boxPoly->addv(-$sideLength, $sideLength);*/
        return self::getSquarePolyFromXYXY(-$sideLength, -$sideLength, $sideLength, $sideLength);
    }

    public static function getSquarePolyFromXYXY($sx, $sy, $ex, $ey)
    {
        $boxPoly = new polygon();
        $boxPoly->addv($sx, $sy);
        $boxPoly->addv($ex, $sy);
        $boxPoly->addv($ex, $ey);
        $boxPoly->addv($sx, $ey);
        return $boxPoly;
    }

    public static function angleToRadians($angle): float
    {
        return $angle * pi() / 180;
    }

    public static function getAnglesToTest($step): array
    {
        $r = [];
        for ($i = 0; $i < 360; $i += $step) {
            $r[] = $i; //angleToRadians($i);
        }
        return $r;
    }

    /**
     * @param polygon $polygon
     * @param $angle
     */
    public static function getRotatedPolygonCopy(polygon $polygon, $angle): polygon
    {
        $r = $polygon->copy_poly();
        $r->rotate(0, 0, $angle);
        return $r;
    }

    /**
     * @param polygon $polygon
     * @param $angle
     */
    public static function getScalatedPolygonCopy($polygon, $xScale, $yScale): polygon
    {
        $r = $polygon->copy_poly();
        $r->scale($xScale, $yScale);
        return $r;
    }
    #endregion

    #region Printing
    /**
     * @param array $gridXRange
     * @param $gridX
     * @param array $gridYRange
     * @param $gridY
     * @param $im
     * @param $colors
     * @param array $grid
     * @param array $templateGridXY
     * @param $movedPoly
     * @param string $imageFilename
     * @return array
     */
    public static function templateToImage(array $gridXRange, $gridX, array $gridYRange, $gridY, array $grid, array $templateGridXY, $movedPoly, string $imageFilename): array
    {
        $cellXRange = [$gridXRange[0] * $gridX, $gridXRange[1] * $gridX];
        $cellYRange = [$gridYRange[0] * $gridY, $gridYRange[1] * $gridY];

        $imageWidth = $cellXRange[1] - $cellXRange[0];
        $imageHeight = $cellYRange[1] - $cellYRange[0];
        /** @var resource $im */
        newImage($imageWidth, $imageHeight, $im, $colors);
        $cellCount = 0;
        foreach ($grid as $x => $column) {
            /** @var Polygon $cell */
            foreach ($column as $y => $cell) {
                switch ($templateGridXY[$x][$y]) {
                    case 0:
                        paintCell($cellXRange[0], $cellYRange[0], $cell, $im, 'blk', 'dgra', $colors);
                        break;
                    case 1:
                        paintCell($cellXRange[0], $cellYRange[0], $cell, $im, 'yel', 'ora', $colors);
                        //drawPolyAt(- $cellXRange[0], - $cellYRange[0], $im, $cell, $colors, "blu");
                        break;
                    case 2:
                        paintCell($cellXRange[0], $cellYRange[0], $cell, $im, 'blu', 'grn', $colors);
                        //drawPolyAt(- $gridXRange[0], - $gridYRange[0], $im, $cell, $colors, "grn");
                        break;
                }
                /*if ($cellCount == 40) {
                    directDrawPolyAt(-$cellXRange[0], -$cellYRange[0], $im, $movedPoly, $colors, "blk");
                    $r = imageGif($im, "$generationSetString.gif");
                    echo '<p><div align="center"><strong>EXAMPLE template</strong><br><img src="'
                        . $generationSetString . '.gif" style="image-rendering: pixelated" width="' . ($imageWidth) . '" height="' . ($imageHeight) . '"><br></div></p>';
                    die();
                }
                $cellCount++;*/
            }
        }
        directDrawPolyAt(-$cellXRange[0], -$cellYRange[0], $im, $movedPoly, $colors, "blk");
        $isOk = imageGif($im, $imageFilename);
        return array($imageWidth, $imageHeight, $isOk);
    }

    /**
     * Generates an SVG version of the template grid (IN/MAYBE/OUT) with polygon overlay.
     */
    public static function templateToSVG(array $gridXRange, $gridX, array $gridYRange, $gridY, array $grid, array $templateGridXY, $movedPoly, string $filename): void
    {
        $cellXRange = [$gridXRange[0] * $gridX, $gridXRange[1] * $gridX];
        $cellYRange = [$gridYRange[0] * $gridY, $gridYRange[1] * $gridY];
        $offsetX = $cellXRange[0];
        $offsetY = $cellYRange[0];
        $w = $cellXRange[1] - $cellXRange[0];
        $h = $cellYRange[1] - $cellYRange[0];

        $colors = [
            0 => ['fill' => '#787878', 'stroke' => '#000000'],  // OUT: dark gray + black
            1 => ['fill' => '#f0ad00', 'stroke' => '#e6c800'],  // MAYBE: orange + yellow
            2 => ['fill' => '#00c000', 'stroke' => '#4466dd'],  // IN: green + blue
        ];

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"$w\" height=\"$h\" viewBox=\"0 0 $w $h\">\n";
        $svg .= "<rect width=\"$w\" height=\"$h\" fill=\"white\"/>\n";

        // Draw cells
        foreach ($grid as $ix => $column) {
            foreach ($column as $iy => $cell) {
                $val = $templateGridXY[$ix][$iy];
                $c = $colors[$val];
                $cx = $cell->x_min - $offsetX;
                $cy = $h - ($cell->y_max - $offsetY); // flip Y
                $cw = $cell->x_max - $cell->x_min;
                $ch = $cell->y_max - $cell->y_min;
                $svg .= "<rect x=\"$cx\" y=\"$cy\" width=\"$cw\" height=\"$ch\" fill=\"{$c['fill']}\" stroke=\"{$c['stroke']}\" stroke-width=\"0.5\"/>\n";
            }
        }

        // Draw polygon outline
        $pathD = '';
        $v = $movedPoly->first;
        $first = true;
        do {
            $next = $v->nextV;
            $sx = $v->x - $offsetX;
            $sy = $h - ($v->y - $offsetY);
            $ex = $next->x - $offsetX;
            $ey = $h - ($next->y - $offsetY);

            if ($first) {
                $pathD .= "M $sx $sy ";
                $first = false;
            }

            if ($v->d() == 0) {
                $pathD .= "L $ex $ey ";
            } else {
                $r = Polygon::dist($v->X(), $v->Y(), $v->Xc(), $v->Yc());
                $sweep = ($v->d() == -1) ? 1 : 0;
                $arcAngle = abs(rad2deg(
                    Polygon::angle($v->Xc(), $v->Yc(), $next->X(), $next->Y())
                    - Polygon::angle($v->Xc(), $v->Yc(), $v->X(), $v->Y())
                ));
                if ($arcAngle > 180) $arcAngle = 360 - $arcAngle;
                $largeArc = ($arcAngle > 180) ? 1 : 0;
                $pathD .= "A $r $r 0 $largeArc $sweep $ex $ey ";
            }
            $v = $next;
        } while ($v !== $movedPoly->first);
        $pathD .= 'Z';

        $svg .= "<path d=\"$pathD\" fill=\"none\" stroke=\"black\" stroke-width=\"1\"/>\n";
        $svg .= "</svg>\n";

        file_put_contents($filename, $svg);
    }
    #endregion

    #region Others
    public static function recursiveMatrixOperationsCheck(&$resultListOfOperationsCache, $previousOperations, $currentMatrix)
    {
        $hash = MatrixUtil::toString($currentMatrix);
        //if (array_key_exists($$hash, $resultListOfOperationsCache)) {
        //if (!empty($resultListOfOperationsCache[$hash])) {
        //if (count($resultListOfOperationsCache[$hash])) {
        if (is_array($resultListOfOperationsCache[$hash])) {
            if (count($previousOperations) < count($resultListOfOperationsCache[$hash])) {
                $resultListOfOperationsCache[$hash] = $previousOperations;
                echo "better - $hash\n";
            } else {
                echo "no - $hash\n";
            }
            return;
        }
        echo "- $hash\n";
        $resultListOfOperationsCache[$hash] = $previousOperations;
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'tc90'), MatrixUtil::rotateClockwise90($currentMatrix));
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'tcc90'), MatrixUtil::rotateCounterClockwise90($currentMatrix));
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'tr180'), MatrixUtil::rotate180($currentMatrix));
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'flr'), MatrixUtil::flipLR($currentMatrix));
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'ftb'), MatrixUtil::flipTB($currentMatrix));
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'ftlbr'), MatrixUtil::flipTLBR($currentMatrix));
        Templates::recursiveMatrixOperationsCheck($resultListOfOperationsCache, Templates::arrayPush($previousOperations, 'ftrbl'), MatrixUtil::flipTRBL($currentMatrix));
    }

    public static function arrayPush($array, $item)
    {
        $array[] = $item;
        return $array;
    }
    #endregion

    /**
     * @param $movedPoly2
     * @param $im
     * @param $colors
     * @param $img
     * @param $col
     * @return array
     */
    public static function checkIsInsidePolygon($movedPoly2, $im, $colors, $img, $col): array
    {
        $bb = $movedPoly2->bRect();
        /** @var Polygon $polyA */
        $polyA = $movedPoly2->copy_poly();
        $polyA->move(-$bb->x_min, -$bb->y_min);
        $cc = $polyA->bRect();
        newImage(ceil($cc->x_max), ceil($cc->y_max), $im, $colors);               // Create a new image to draw our polygons
        directDrawPolyAt(0, 0, $im, $polyA, $colors, "red");
        $r1 = imageGif($im, "poly_ex_fill_figure1.gif");

        newImage(ceil($cc->x_max), ceil($cc->y_max), $img, $colors);
        directDrawPolyAt(0, 0, $img, $polyA, $colors, "red");
        for ($x = 0; $x < $cc->x_max; $x++) {
            for ($y = 0; $y < $cc->y_max; $y++) {
                $p5 = new Vertex($x, $y);
                //$r1 = $polyA->isInside($p5);
                $a = 1;
                $r1 = $polyA->isInside($p5, true);
                if ($r1) {
                    $r = imagesetpixel($img, $x, $y, $col['grn']);
                } else {
                    if ($y < 32 || $x == 0 || $y > 32) {
                        $r = imagesetpixel($img, $x, $y, $col['blu']);
                    } else {
                        $r = imagesetpixel($img, $x, $y, $col);
                    }
                }
            }
        }
        $r2 = imageGif($img, "poly_ex_fill_figure2.gif");
        return array($polyA, $r1, $p5, $r2);
    }
}
