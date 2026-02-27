<?php

class MatrixUtil
{
    public static function print($matrix)
    {
        //echo "------------\n";
        echo self::toString($matrix);
        //echo "------------\n";
    }

    #region basic operations
    /**
     * @param $matrix array
     */
    public static function getDimensionsXY($matrix)
    {
        return [count($matrix), count($matrix[0])];
    }

    public static function equal($matrix)
    {
        return $matrix;
    }

    public static function rotateClockwise90($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$ty - $y - 1][$x] = $matrix[$x][$y];
            }
        }
        return $r;
    }
    public static function rotate180($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$tx - $x - 1][$ty - $y - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }
    public static function rotateCounterClockwise90($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$y][$tx - $x - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipTB($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$x][$ty - $y - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipLR($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$tx - $x - 1][$y] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipTRBL($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$y][$x] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipTLBR($matrix)
    {
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$ty - $y - 1][$tx - $x - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }
    #endregion

    #region serialization, toString
    /**
     * @param $matrix
     */
    public static function toString($matrix): string
    {
        $r = '';
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        for ($y = 0; $y < $ty; $y++) {
            for ($x = 0; $x < $tx; $x++) {
                $r .= "\t" . $matrix[$x][$y];
            }
            $r .= "\n";
        }
        return $r;
    }
    public static function toStringYX($matrix): string
    {
        $r = '';
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        for ($y = 0; $y < $ty; $y++) {
            for ($x = 0; $x < $tx; $x++) {
                $r .= "\t" . $matrix[$y][$x];
            }
            $r .= "\n";
        }
        return $r;
    }

    public static function binCode($matrix): string
    {
        $r = '';
        list($tx, $ty) = MatrixUtil::getDimensionsXY($matrix);
        $r[0] = chr($tx);
        $r[1] = chr($ty);
        $currentPair = 0;
        $byte = 0;
        $stringPosition = 2;
        for ($y = 0; $y < $ty; $y++) {
            for ($x = 0; $x < $tx; $x++) {
                $byte = $byte << 2;
                $byte += $matrix[$x][$y];
                $currentPair++;
                if ($currentPair == 4) {
                    //$r[$stringPosition] = $byte;
                    //$r = $r << 8 | $byte;
                    $r .= chr($byte);
                    $byte = 0;
                    $currentPair = 0;
                    $stringPosition++;
                }
            }
        }
        return $r;
    }

    private static function getPairFromByte($n, $byte)
    {
        $selector = (3 << ((3 - $n) * 2));
        return ($selector & $byte) >> ((3 - $n) * 2);
    }
    public static function binDecode($string): array
    {
        $tx = ord($string[0]);
        $ty = ord($string[1]);
        $r = [];
        $currentPair = 0;
        $stringPosition = 2;
        for ($y = 0; $y < $ty; $y++) {
            for ($x = 0; $x < $tx; $x++) {
                $r[$x][$y] = self::getPairFromByte($currentPair, ord($string[$stringPosition]));
                $currentPair += 1;
                if ($currentPair == 4) {
                    $currentPair = 0;
                    $stringPosition++;
                }
            }
        }
        return $r;
    }
    #endregion

    #region other
    /**
     * @param array $m
     */
    public static function printOperationsExamples(array $m = null): void
    {
        if (empty($m)) {
            $m = [[1, 2, 3, 4], [5, 6, 7, 8], [9, 10, 11, 12]];
        }
        echo "######### Original ###########################\n\n";
        MatrixUtil::print($m);
        echo "\n90 clockwise rotation:\n\n";
        MatrixUtil::print(MatrixUtil::rotateClockwise90($m));
        echo "\n90 counter-clockwise rotation:\n\n";
        MatrixUtil::print(MatrixUtil::rotateCounterClockwise90($m));
        echo "\n180 rotation:\n\n";
        MatrixUtil::print(MatrixUtil::rotate180($m));
        echo "\n\n######### Original ###########################\n\n";
        MatrixUtil::print($m);
        echo "\nflip left right:\n\n";
        MatrixUtil::print(MatrixUtil::flipLR($m));
        echo "\nflip top bottom:\n\n";
        MatrixUtil::print(MatrixUtil::flipTB($m));
        echo "\n\n######### Original ###########################\n\n";
        MatrixUtil::print($m);
        echo "\nflip top left bottom right:\n\n";
        MatrixUtil::print(MatrixUtil::flipTLBR($m));
        echo "\nflip top right bottom left:\n\n";
        MatrixUtil::print(MatrixUtil::flipTRBL($m));
    }
    #endregion
}
