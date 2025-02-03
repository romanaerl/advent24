<?php
class def
{
    const UP = '^';
    const RIGHT = '>';
    const LEFT = '<';
    const DOWN = 'v';
    protected $mat = [];
    protected $minimalSumm;
    protected $startX;
    protected $startY;
    protected $visited = [];
    protected $operationsCnt = 0;
    protected $lastTimePrinted = 0;
    protected $path = [];
    protected $diffMat = [];

    protected $deadEnds = [];


    function code()
    {
        for ($i = 0; $i <5; $i++) rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
//        $this->readArray("data/16-1.inp");
        $this->readArray("data/16-1-1.inp");
//        $this->readArray("data/16-1.example");
//        $this->readArray("data/16-1.example2");
//        $this->readArray("data/16-1.example3");
//        $this->printArray("INITIAL");
        echo "INITIAL \n";
        $this->printMat([]);

//        $this->processMovs($this->startX, $this->startY, self::LEFT, 0, null, null, [], false);
//        $this->operationsCnt = 0;
//        $this->processMovs($this->startX, $this->startY, self::LEFT, 0, null, null, [], false);
//        rslog($this->minimalSumm, "NOT OPTIMAL ($this->operationsCnt) ");
//        $this->operationsCnt = 0;
        $this->operationsCnt = 1;
        while ($this->operationsCnt) {
            $this->operationsCnt = 0;
            $this->closeDeadEnds();
            echo "\n";
            echo "\n";
            echo "\n";
            echo "\n";
            $this->printMat([], false);
            rslog($this->operationsCnt, '$this->operationsCnt');
        }
        echo "AFTER DEADENDS MARK \n";
        $this->printMat([]);
        $this->operationsCnt = 1;
        while ($this->operationsCnt) {
            $this->operationsCnt = 0;
            $this->processMovs($this->startX, $this->startY, self::LEFT, 0, null, null, [], true);
        }
        rslog($this->minimalSumm, "OPTIMAL ($this->operationsCnt) ");
        $this->printMat($this->path, true);

//
//        echo '$this->calcSum()' . $this->calcSum() . "\n";
////
//        $this->printArray("RESULT");
    }

    function closeDeadEnds() {
        foreach ($this->mat as $y => $row) {
            foreach ($row as $x => $symb) {
                if ($symb == ".") {
                    $coords = $this->getSquareAround($x, $y, self::LEFT);
                    $exits = 0;
                    foreach ($coords as $coord) {
                        list($cX, $cY, $cD) = $coord;
                        if (in_array($this->mat[$cY][$cX], [".", "S", "E"])) {
                            $exits++;
                        }
                    }
                    if ($exits == 1) {
                        // square has only one entrance, so no exit. marking it as a dead end
                        $this->mat[$y][$x] = '#';
                        $this->operationsCnt++;
                    }
                }
            }
        }
    }

    function tooExpensiveToContinue($summ)
    {
        return false;
        if (is_null($this->minimalSumm)) return false;
//        if ($summ > 151612) return false; // precalculated not to be the best answer
        return $summ > $this->minimalSumm;
    }

    function addResult($summ, $visited = [], $x = 0, $y = 0)
    {
        if (is_null($this->minimalSumm) || ($this->minimalSumm > $summ)) {
            $this->minimalSumm = $summ;
            $this->operationsCnt++;
            return true;
        }
        return false;
    }

    function getSquareAround($x, $y, $curDir)
    {
        $dirs = [
            [0, -1, self::UP],
            [1, 0, self::RIGHT],
            [-1, 0, self::LEFT],
            [0, 1, self::DOWN],
        ];
        $coords = [];
        $prioritizedDir = [];
        foreach ($dirs as $dir) {
            $xm = $x + $dir[0];
            $ym = $y + $dir[1];
//            if ($dir[2] === $curDir) {
//                $prioritizedDir = [$xm, $ym, $dir[2]];
//            } else {
                $coords[] = [$xm, $ym, $dir[2]];
//            }
        }
//        if (!empty($prioritizedDir)) {
//            $coords = array_merge([$prioritizedDir], $coords);
//        }
        return $coords;
    }

    function filterCoords($coords, $excludeX, $excludeY, $visited, $forceOptimal) {
        $canGoCoords = [];
        foreach ($coords as $coord) {
            if (isset($this->deadEnds[$coord[1]][$coord[0]][$coord[2]])) {
                // this is marked as a dead end
                continue;
            }
            if ($coord[0] == $excludeX && $coord[1] == $excludeY) {
                continue;
            }
            if (isset($visited[$coord[1]][$coord[0]])) {
                continue;
            }

            $symbol = $this->mat[$coord[1]][$coord[0]];
            if (in_array($symbol, ['.','E'])) {
                $canGoCoords[] = $coord;
            }
        }
        return $canGoCoords;
    }

    function getTurnPrice($dir1, $dir2)
    {
        if (is_null($dir1)) return 0; // Start - we can go anywhere without new turn
        if ($dir1 == $dir2) return 0;
        if (in_array($dir1, [self::UP, self::DOWN]) && in_array($dir2, [self::UP, self::DOWN])) return 2000;
        if (in_array($dir1, [self::LEFT, self::RIGHT]) && in_array($dir2, [self::LEFT, self::RIGHT])) return 2000;
        return 1000;
    }

    function processMovs($x,$y, $curDir, $curSumm, $excludeX, $excludeY, $visited, $forceOptimal)
    {
//        rslog(" $x, $y => $curSumm ");
        if ($this->tooExpensiveToContinue($curSumm)) {
//            rslog("TOO EXPENSIVE. Stop Route");
//            return false;
            return true;
        }
        $curSymbol = $this->mat[$y][$x];
        $visited['path'][] = [$x, $y, $curDir, $curSumm];
        $visited[$y][$x][$curDir] = $curSumm;
        $this->visited[$y][$x][$curDir] = $curSumm;
        if ($this->printMat($visited)) {
            echo "$this->minimalSumm";
        }

        if ($curSymbol == 'E') {
            if ($this->addResult($curSumm)) {
                $this->operationsCnt++;
                $this->path = $visited;
                if ($this->printMat($this->path)) {
                    rslog($this->minimalSumm, 'FOUND NEW SUMM <----');
                }
                return false;
            } else {
                return true;
            }
        }

        $square = $this->getSquareAround($x, $y, $curDir);
//        rslog($square, '$square');
        $filtered = $this->filterCoords($square, $excludeX, $excludeY, $visited, $forceOptimal);

        $rememberKeypoint = false;
        if (count($filtered) > 2) {
            //remember the last split point in our local branch
            $rememberKeypoint = true;
        } elseif ($filtered == 1) {
            // no exit out, just the entrance
            list($endX, $endY, $endDir) = array_pop($visited['keypoints']);
            $startMarking = false;
            foreach ($visited['path'] as $step) {
                if ($step[0] == $endX && $step[1] == $endY && $step[2] == $endDir) {
                    $startMarking = true;
                }
                if ($startMarking && !isset($this->deadEnds[$step[0]][$step[1]][$step[2]])) {
                    $this->operationsCnt++;
                    $this->deadEnds[$step[0]][$step[1]][$step[2]] = 1;
                }
            }
            if ($this->printMat($this->path)) {
                rslog($this->minimalSumm, 'FOUND NEW BLOCKS <----');
            }
            // dead end detected - we will mark it in the dead end entrances library so other processes will avoid it
        }

//        rslog($filtered, '$filtered');
        $processCount = 0;
        foreach ($filtered as $fil) {
            if (isset($fil['skip'])) continue;
            list($newX, $newY, $newDir) = $fil;
            $tSum = $this->getTurnPrice($curDir, $newDir);
            if (!$processCount && $rememberKeypoint) {
                $visited['keypoints'][] = [$newX, $newY, $newDir];
            }
            if (!$this->processMovs($newX, $newY, $newDir, $curSumm+1+$tSum, $x, $y, $visited, $forceOptimal)) {
                return false;
            }
            $processCount++;
        }

        return true;
    }




    function printMat($visited, $ignoreTime = false)
    {
        if (!$ignoreTime && time() - $this->lastTimePrinted < 2) return false;
        echo "\033c";
        $this->lastTimePrinted = time();
        $lines = [];
        if (isset($visited['path'])) {
            $vis = $visited['path'][count($visited['path']) - 1];
            list ($lastX, $lastY, $lastDir, $lastSum) = $vis;
        } else {
            $lastY = $lastX = null;
        }
        foreach ($this->mat as $y => $row) {
            $line = "";
            foreach ($row as $x => $symb) {
                if (!empty($this->diffMat)) {
                    if ($symb !== $this->diffMat[$y][$x]) {
                        $line .= "\033[48;5;0m";
                    }
                }
                if ($x == $lastX and $y == $lastY) {
                    $symb = "@";
                    $symb = "\033[38;5;112m{$symb}\033[0m";
                } elseif (isset($this->deadEnds[$x][$y])) {
                    $symb = "X";
                    $symb = "\033[38;5;120m{$symb}\033[0m";
                } elseif (isset($visited[$y][$x])) {
                    $arr = array_keys($visited[$y][$x]);
                    $symb = reset($arr);
                    $symb = "8";
                    $symb = "\033[38;5;230m{$symb}\033[0m";
                } elseif (isset($this->visited[$y][$x])) {
                    $symb = "-";
                    $symb = "\033[38;5;60m{$symb}\033[0m";
                } elseif ($symb == ".") {
                    $symb = "0";
                    $symb = "\033[38;5;255m{$symb}\033[0m";
                } else {
                    $symb = "\033[38;5;210m{$symb}\033[0m";
                }
                $line .= $symb;
                if (!empty($this->diffMat)) {
                    if ($symb != $this->diffMat) {
                        $line .= "\033[0m";
                    }
                }
            }
            $lines[] = $line;
//            echo "$line \n";
            $this->diffMat = $this->mat;
        }

        $lineCnt = count($lines);

        for ($i = 0; $i < ceil($lineCnt/2); $i++) {
            $secondLine = $lines[$i + ceil($lineCnt/2)] ?? str_repeat('.', count($this->mat[0]));
            echo $secondLine ."   ".$lines[$i]  . "\n";
        }
//        foreach ($lines as $line) echo "$line \n";

        return true;
    }


    function readArray($filename)
    {
        $lines = file($filename);
        foreach ($lines as $y => $line) {
            $line = str_split(trim($line));
            foreach ($line as $x => $symb) {
                $this->mat[$y][$x] = $symb;
                if ($symb=='S') {
                    $this->startX = $x;
                    $this->startY = $y;
                }
            }
        }
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}