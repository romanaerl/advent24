<?php
class Def
{
    private $matrix = [];
    private $res_matrix = [];

    private $linesCnt = 0;
    private $colsCnt = 0;

    function readInput($filePath)
    {

        $lines = file($filePath);

        $this->linesCnt = count($lines);
        $this->colsCnt = strlen(trim($lines[0]));

        foreach ($lines as $line => $oneLine) {
            $oneLine = trim($oneLine);
            for ($col = 0; $col < $this->colsCnt; $col++) {
                $symb = $oneLine[$col];
                $this->matrix[$line][$col] = ($symb == '@') ? 1 : 0;
            }
        }

        $sum = 0;

        while ($ss = $this->removeIteration()) {
            $sum += $ss;
        }



//        $this->printMatrix();
//        $this->printMatrix2();
        echo "\r\nSUM IS $sum\r\n";

    }

    function removeIteration()
    {
        $sum = 0;
        for($line=0 ; $line < $this->linesCnt ; $line++) {
            for($col=0 ; $col < $this->colsCnt ; $col++) {
                if ($this->isNotBlocked($line, $col)) {
                    $this->matrix[$line][$col] = 0;
                    $sum++;
                }
            }
        }

        return $sum;
    }

    function isNotBlocked($line, $col) {
        $symb = $this->matrix[$line][$col];
        if (!$symb) {
            // This is not a roll at all
            return false;
        }
        $lsum = 0;
        for ($i = $line - 1; $i <= $line + 1; $i++) {
            for ($j = $col - 1; $j <= $col + 1; $j++) {
                if ($i < 0 || $i >= $this->linesCnt || $j < 0 || $j >= $this->colsCnt) {
                    // skipping out of region spaces
                    continue;
                }
                if ($i == $line && $j == $col) {
                    continue;
                }
                if ($this->matrix[$i][$j]) {
                    $lsum++;
                }
            }
        }
        return $lsum < 4;
    }

    function printMatrix2 () {
        echo "\r\n\ LINES: \r\n";
        for($i = 0; $i < $this->linesCnt; $i++) {
            $line = '';
            for($j = 0; $j < $this->colsCnt; $j++) {
                $d = $this->matrix[$i][$j];
                $c = $this->res_matrix[$i][$j] ?? 0;
                if ($c>0 && $c < 4) {
                    $line .= "x";
                } else {
                    $line .= $d ? "@" : '.';
                }
//                $line .= str_pad($c, 3);
            }
            echo "$line\r\n";
        }
    }

    function printMatrix () {
        echo "\r\n\ LINES: \r\n";
        for($i = 0; $i < $this->linesCnt; $i++) {
            $line = '';
            for($j = 0; $j < $this->colsCnt; $j++) {
                $c = $this->res_matrix[$i][$j];
                $line .= str_pad($c, 3);
            }
            echo "$line\r\n";
        }
    }

    function addRes($symb, $line, $col) {
//        echo "$line:$col=>$symb\r\n";
        if ($symb !== '@') {
            $this->res_matrix[$line][$col] = -200;
        } else {
            for ($i = $line - 1; $i <= $line + 1; $i++) {
                for ($j = $col - 1; $j <= $col + 1; $j++) {
                    if ($i < 0 || $i >= $this->linesCnt  || $j < 0 || $j >= $this->colsCnt) {
                        // skipping out of region spaces
                        continue;
                    }
                    if ($i == $line && $j == $col) {
                        continue;
                    }
                    // nulls are ignored (there is no paper anyway)
                    if (!isset($this->res_matrix[$i][$j])) {
                        // Creating the weight, yet we do not know if there is any paper there
                        $this->res_matrix[$i][$j] = 1;
                    } else if (!is_null($this->res_matrix[$i][$j])) {
                        // Increasing the weight
                        $this->res_matrix[$i][$j] += 1;
                    }
                }
            }
        }

    }

    function code() {
        return '';
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-04-1.example";
//        $filePath = "data/25-04-1.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


$Def = new Def();
$Def->run();

