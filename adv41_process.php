<?php
class def
{
    function run()
    {
        $f = file("data/4111.example");
        $f = file("data/adv41.inp");
        $numTotal = $num = 0;
        foreach ($f as $line) {
            $num += $this->countXmas($line);
        }
        $numTotal +=$num;
        rslog($num, '$num FIRST');
        $this->printM($f);

        $other = $this->changeMatrix($f);
        $num = 0;
        foreach ($other as $line) {
            $num += $this->countXmas($line);
        }
        $numTotal +=$num;
        rslog($num, '$num SECOND');
        $this->printM($other);

        $other = $this->changeMatrixDiagonal($f);
        $num = 0;
        foreach ($other as $line) {
            $line = join($line);
            $num += $this->countXmas($line);
        }
        $numTotal +=$num;
        rslog($num, '$num DIAG FIRST');
        $this->printM($other);


        $num = 0;
        $other = $this->changeMatrixDiagonal2($f);
        foreach ($other as $line) {
            $line = join($line);
            $num += $this->countXmas($line);
        }
        $numTotal += $num;
        rslog($num, '$num DIAG SECOND');
        $this->printM($other);



        rslog($numTotal, '$numTotal TOTAL');

    }

    function changeMatrix($f) {
        $other = [];
        foreach ($f as $key => $line) {
            $line = str_split($line);
            foreach ($line as $key2 => $symb) {
                if (empty($other[$key2])) {
                    $other[$key2] = '';
                }
                $other[$key2] .= $symb;
            }
        }
        return  $other;
    }

    function changeMatrixDiagonal($f) {
        $other = [];
        $matrix = $matrix2 = [];
        $size = count($f);
        rslog($size, '$size');
        foreach ($f as $key => $line) {
            $line = str_split($line);
            $line = array_slice($line, 0, $size, true);
            $matrix[] = $line;
        }
//        rslog($matrix, '$matrix');


        $lineCount = 0;
        for ($x = 0; $x < $size; $x++)
            for($y=$size-1;$y>=0;$y--) {
                $x1 = $x;
                $y1 = $y;
                if ($x>0 && $y>0) {
                    continue;
                }
                while ($x1 >= 0 && $x1 < $size && $y1 >= 0 && $y1 < $size) {
//                    rslog($matrix[$x1][$y1], '$matrix[$x1][$y1];' . " x1Y1 [$x1,$y1]");
                    $matrix2[$lineCount] ?? $matrix2[$lineCount] = [];
                    $matrix2[$lineCount][] = $matrix[$x1][$y1];
                    $x1++;
                    $y1++;
                }
                $lineCount++;
            }

        
//        rslog("=======================================================================================================", '');
//        rslog($matrix2, '$matrix2');

        return  $matrix2;
    }

    function changeMatrixDiagonal2($f) {
        $other = [];
        $matrix = $matrix2 = [];
        $size = count($f);
        rslog($size, '$size');
        foreach ($f as $key => $line) {
            $line = str_split($line);
            $line = array_slice($line, 0, $size, true);
            $matrix[] = $line;
        }
//        rslog($matrix, '$matrix');


        $lineCount = 0;
        for ($x = $size-1; $x >= 0; $x--)
            for($y=$size-1;$y>=0;$y--) {
                $x1 = $x;
                $y1 = $y;
                if ($x<$size-1 && $y>0) {
                    continue;
                }
                while ($x1 >= 0 && $x1 < $size && $y1 >= 0 && $y1 < $size) {
//                    rslog($matrix[$x1][$y1], '$matrix[$x1][$y1];' . " x1Y1 [$x1,$y1]");
                    $matrix2[$lineCount] ?? $matrix2[$lineCount] = [];
                    $matrix2[$lineCount][] = $matrix[$x1][$y1];
                    $x1--;
                    $y1++;
                }
                $lineCount++;
            }


//        rslog("=======================================================================================================", '');
//        rslog($matrix2, '$matrix2');

        return  $matrix2;
    }

    function printM($mat)
    {
        rslog("PRINT THE MATRIX");
        foreach ($mat as $one) {
            if (is_array($one)) {
                $one = join($one);
            }
            echo "$one\r\n";
        }
    }

    function countXmas($line) {
//        rslog($line, '$line');
        $count = 0;
        $matches = [];
        preg_match_all("/SAMX/mis", $line, $matches);
        $count+=count($matches[0]);
        $matches = [];
        preg_match_all("/XMAS/mis", $line, $matches);
        $count+=count($matches[0]);

//        rslog(count($matches[0]));
        return $count;
    }
}