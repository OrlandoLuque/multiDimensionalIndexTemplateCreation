<?php

class MatrixUtil {
    public static function print($matrix) {
        echo "------------\n";
        self::toString($matrix);
        echo "------------\n";
    }


    /**
     * @param $matrix array
     */
    public static function getDimensions($matrix) {
        return [count($matrix[0]), count($matrix)];
    }

    public static function rotateClockwise90($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$y][$tx - $x - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }
    public static function rotate180($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$tx - $x - 1][$ty - $y - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }
    public static function rotateCounterClockwise90($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$ty - $y - 1][$x] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipLR($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$x][$ty - $y - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipTB($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$tx - $x - 1][$y] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipTRBL($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$y][$x] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    public static function flipTLBR($matrix) {
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        $r = [];
        for ($x = 0; $x < $tx; $x++) {
            for ($y = 0; $y < $ty; $y++) {
                $r[$ty - $y - 1][$tx - $x - 1] = $matrix[$x][$y];
            }
        }
        return $r;
    }

    /**
     * @param $matrix
     */
    public static function toString($matrix): string {
        $r = '';
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        for ($y = 0; $y < $ty; $y++) {
            for ($x = 0; $x < $tx; $x++) {
                $r .= "\t" . $matrix[$x][$y];
            }
            $r .= "\n";
        }
        return $r;
    }
    public static function toStringYX($matrix): string {
        $r = '';
        List($tx, $ty) = MatrixUtil::getDimensions($matrix);
        for ($y = 0; $y < $ty; $y++) {
            for ($x = 0; $x < $tx; $x++) {
                $r .= "\t" . $matrix[$y][$x];
            }
            $r .= "\n";
        }
        return $r;
    }
}

