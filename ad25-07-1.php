<?php
class Def
{
    protected $matrix = [];
    protected $maxCols = 0;
    protected $maxRows = 0;
    protected $startRow = 0;
    protected $startCol = 0;

    const DIR_SAME = 0;
    const DIR_UP = 1;
    const DIR_RIGHT = 2;
    const DIR_DOWN = 3;
    const DIR_LEFT = 4;

    const VAL_START = 'S';
    const VAL_SPLIT = '^';
    const VAL_PIPE = '|';
    const VAL_DOT = '.';

    const DIRS = [
        self::DIR_SAME  => [0,0],
        self::DIR_UP    => [-1,0],
        self::DIR_DOWN  => [1,0],
        self::DIR_RIGHT => [0,1],
        self::DIR_LEFT  => [0,-1],
    ];

    function readInput($filePath)
    {

        $lines = file($filePath);
        $this->maxRows = count($lines);

        foreach ($lines as $rowI => $line) {
            $line = trim($line);
            $this->maxCols = strlen($line);
            for ($colI = 0; $colI < $this->maxCols; $colI++) {
                $this->matrix[$rowI][$colI] = $line[$colI];
                if ($line[$colI] == self::VAL_START) {
                    $this->startCol = $colI;
                    $this->startRow = $rowI;
                }
            }
        }

    }


    function printMatrix($mat)
    {
        echo "\r\n Matrix: \r\n\r\n";
        foreach ($mat as $rowI => $row) {
            foreach ($row as $colI => $symb) {
                echo $symb;
            }
            echo "\r\n";
        }

        echo "\r\n";
    }


    function setPos(&$mat, $row, $col, $value, $dir)
    {
        $row += self::DIRS[$dir][0];
        $col += self::DIRS[$dir][1];
        $mat[$row][$col] = $value;
    }

    function getPos($mat, $row, $col, $dir = self::DIR_SAME)
    {
        $row += self::DIRS[$dir][0];
        $col += self::DIRS[$dir][1];
        return $mat[$row][$col] ?? ' ';
    }

    function setPipeIfOk(&$mat, $row, $col, $dir)
    {
        $c = $this->getPos($mat, $row, $col, $dir);
        if (!$this->isSplit($c)) {
            $this->setPos($mat, $row, $col, '|', $dir);
        }
    }

    function isSplit($c)
    {
        return $c === self::VAL_SPLIT;
    }

    function isStart($c)
    {
        return $c === self::VAL_START;
    }

    function isPipe($c)
    {
        return $c === self::VAL_PIPE;
    }

    function isDot($c)
    {
        return $c === self::VAL_DOT;
    }

    function walkLine($rowI)
    {
        $splits = 0;
        for ($colI = 0; $colI < $this->maxCols; $colI++) {
            $c = $this->getPos($this->matrix, $rowI, $colI, self::DIR_SAME);

            // Start
            if ($this->isStart($c)) {
                $this->startRow = $rowI;
                $this->startCol = $colI;
                $c = self::VAL_PIPE;
                $this->setPos($this->matrix, $rowI, $colI, $c, self::DIR_SAME);
            }

            if ($this->isDot($c)) {
                $upper = $this->getPos($this->matrix, $rowI, $colI, self::DIR_UP);
                if ($this->isPipe($upper)) {
                    $this->setPipeIfOk($this->matrix, $rowI, $colI, self::DIR_SAME);
                }
            }

            if ($this->isSplit($c)) {
                $upper = $this->getPos($this->matrix, $rowI, $colI, self::DIR_UP);
                if ($this->isPipe($upper)) {
                    // Found the splitter under the beam
                    $splits++;
                    $this->setPipeIfOk($this->matrix, $rowI, $colI, self::DIR_LEFT);
                    $this->setPipeIfOk($this->matrix, $rowI, $colI, self::DIR_RIGHT);
                }
            }
        }

        return $splits;
    }

    function countSplits($mat)
    {
        $splits = 0;

        for ($rowI = 0; $rowI < $this->maxRows; $rowI++) {
            $splits += $this->walkLine($rowI);
        }

        return $splits;
    }

    function code() {
        $this->printMatrix($this->matrix);

        return $this->countSplits($this->matrix);
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-07-1.example";
        $filePath = "data/25-07-1.inp";
//        $filePath = "data/25-07-2.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


ini_set("memory_limit", '5120M');
$Def = new Def();
$Def->run();

