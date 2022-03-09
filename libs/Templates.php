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
    public static function generateAndPersist(Task $task, bool $printNextAndDie = false, string  $forceLast = null): void
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

        echo "\n\nIniciando con $templateCount plantillas para $calculatedTemplates combinaciones\n";
        echo "Última en base de datos: $last\n\n";
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

        //$redis->set($templateCountKey, -1); comentado al hacer transacciones <-- deprecated


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
                        if (!self::checkNoLinesInPolygonFilling($rotatedPoly)) {
                            echo "Error: calculation misshap for polygon " . "$indexPoly-s$polygonScale-x$gridX,y$gridY-a$angle";
                            die();
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
                                $template = [];
                                $gridXRange = [floor($boxVertex[0]['x'] / $gridX), ceil($boxVertex[1]['x'] / $gridX)];
                                $gridYRange = [floor($boxVertex[0]['y'] / $gridY), ceil($boxVertex[1]['y'] / $gridY)];
                                $grid = Templates::getGrid($gridXRange[0], $gridYRange[0], $gridXRange[1], $gridYRange[1], $gridX, $gridY);
                                /*list($templateGridXY, $templateHashYX)*/

                                $templateGridXY = Templates::getTemplateGrid($grid, $movedPoly);
                                ///////$templateGridXY = getTemplateGridExpecting($grid, $movedPoly, $expected);
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
                                echo " $templateCount plantillas para $calculatedTemplates combinaciones ";
                                flush();
                                if ($printNextAndDie) {
                                    $imageFilename = "examples/generated/$generationSetString.gif";

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
        echo "Calculated templates: $calculatedTemplates - unique ones: $templateCount - desde $inicio a " . date("Y-m-d H:i:s");

        return; // array($im, $colors, $img);
    }

    /**
     * @param $grid
     * @param polygon $poly
     * @return array
     */
    public static function getTemplateGrid($grid, $poly)
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
                //$sr .= $intersectResult;
            }
            //$sr .= '|';
        }
        return $r; //[$r, $sr];
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

    private static function getGrid($sx, $sy, $ex, $ey, $gridX, $gridY)
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
    public static function checkNoLinesInPolygonFilling(Polygon $polygon): bool
    {
        $extraMargin = 3;
        $box = $polygon->bRect();
        $width = $box->x_max - $box->x_min + $extraMargin * 2;
        $height = $box->y_max - $box->y_min + $extraMargin * 2;
        $xStart = floor($box->x_min) - $extraMargin;
        $xEnd = $box->x_max + 1 + $extraMargin;
        $yStart = floor($box->y_min) - $extraMargin;
        $yEnd = $box->y_max + 1 + $extraMargin;
        $result = [];
        $lineDetection = [];
        for ($x = $xStart
                ; $x < $xEnd
                ; $x++) {
            $result[] = [];
            $previousLineDetection = $lineDetection;
            $lineDetection = [];
            for ($y = $yStart
                    ; $y < $yEnd
                    ; $y++) {
                //echo "\n$x - $y";
                $p5 = new Vertex($x, $y);
                //$r1 = $polyA->isInside($p5);
                $a = 1;
                $result[$x][$y] = $polygon->isInside($p5, true);
                if ($y > $yStart + 2) {
                    if ($result[$x][$y] == $result[$x][$y - 2] && $result[$x][$y] != $result[$x][$y - 1]) {
                        if ($previousLineDetection[$y] == $result[$x][$y]) {
                            return false;
                        }
                        $lineDetection[$y] = $result[$x][$y];
                    }
                }
            }
        }
        return true;

        return $result;
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
    public static function getGridsFromSupportedSizes($gridSupportedSizes, $horizontal = true, $vertical = true)
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
