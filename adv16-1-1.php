<?php
class def
{
    const UP = '^';
    const RIGHT = '>';
    const LEFT = '<';
    const DOWN = 'v';
    const DIRS = [
        self::UP => [0, -1, self::UP],
        self::RIGHT => [1, 0, self::RIGHT],
        self::LEFT => [-1, 0, self::LEFT],
        self::DOWN => [0, 1, self::DOWN],
    ];
    const OPPOSITE_DIRS = [
        self::UP => self::DOWN,
        self::DOWN => self::UP,
        self::LEFT => self::RIGHT,
        self::RIGHT => self::LEFT,
    ];
    protected $mat = [];
    protected $minimalSumm;
    protected $startX;
    protected $startY;
    protected $endX;
    protected $endY;
    protected $visited = [];
    protected $operationsCnt = 0;
    protected $lastTimePrinted = 0;
    protected $path = [];
    protected $diffMat = [];
    protected $keypoints = [];
    protected $deadEnds = [];
    protected $queue = [];
    protected $lastSumm = 0;
    protected $lastX = 0;
    protected $lastY = 0;


    protected $queue0 = [];
    protected $queue1 = [];
    protected $queue2 = [];
    protected $queue3 = [];
    protected $queue4 = [];
    protected $queue5 = [];
    protected $queue6 = [];
    protected $queue7 = [];
    protected $queue8 = [];
    protected $queue9 = [];

    protected $queueFactor = 9;


    function getSizeQueue()
    {
        return count($this->queue);
    }

    function addQueue($row)
    {
        $this->queue[] = $row;
    }

    function getQueue()
    {
//        return array_shift($this->queue);
        return array_pop($this->queue);
    }

    function code()
    {
        for ($i = 0; $i <5; $i++) rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
        $this->readArray("data/16-1.inp");
//        $this->readArray("data/16-1-1.inp");
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
        $this->printMat([], true);

        $this->markKeypoints();
        echo "AFTER markdown\n";
        $this->printMat([], true);

        $this->findAndWeightLinks();
        rslog(count($this->keypoints), 'count($this->keypoints)');
        echo "AFTER WEIGHT\n";
        $this->printMat([], true);


//        $this->queue[] = [$this->startX, $this->startY, self::LEFT, 0, []];
        $this->addQueue([$this->startX, $this->startY, self::LEFT, 0, []]);
        $this->operationsCnt = 0;
        while ($this->getSizeQueue()) { // && $this->operationsCnt<5000000) {
            $this->processRecordFromQueue();
        }


//        $this->operationsCnt = 1;
//        while ($this->operationsCnt) {
//            $this->operationsCnt = 0;
//            $this->processMovs($this->startX, $this->startY, self::LEFT, 0, null, null, [], true);
//        }
        rslog($this->minimalSumm, "OPTIMAL ($this->operationsCnt) ");
        $this->printMat($this->path, true);

//
//        echo '$this->calcSum()' . $this->calcSum() . "\n";
////
//        $this->printArray("RESULT");
    }

    function processRecordFromQueueDejksrta()
    {

    }

    function processRecordFromQueue()
    {
        $this->operationsCnt++;

        $rec = $this->getQueue();
        list($x, $y, $dir, $sum, $visited) = $rec;

        if (!isset($this->keypoints[$y][$x])) {
            die("We should not be here $x, $y");
        }

        $this->lastX = $x;
        $this->lastY = $y;
        $visited[$y][$x][$dir] = 1;
        $this->visited[$y][$x][$dir] = 1;
        $this->printMat($visited);

        if ($this->endX == $x and $this->endY == $y) {
            // We found the end (!)
            $this->addResult($sum, $visited);
            return;
        }

        $links = $this->keypoints[$y][$x]['links'];
//        rslog($links,"links $x, $y");
//        if ($this->operationsCnt > 5) die("DIE");
        foreach ($links as $linkDir => $link) {
            list($linkX, $linkY, $linkComeDir, $linkSum) = $link;
            if ($linkDir == $this->getOpposeDir($dir)) {
//                rslog("skip $linkDir", '"skip $linkDir"');
                // This is where we came from
                continue;
            }
            if (isset($visited[$linkY][$linkX][$linkComeDir])) {
//                rslog("visited $linkDir", '"skip $linkDir"');
                // been here
                continue;
            }
            $sumRec = $sum + $this->getTurnPrice($dir, $linkDir) + $linkSum;
            if ($this->tooExpensiveToContinue($sumRec)) {
                continue;
            }

            $record = [$linkX, $linkY, $linkComeDir, $sumRec, $visited];
            $this->lastSumm = $sumRec;
//            rslog("[newrecord $x,$y] $linkDir ==> $linkX, $linkY, $linkDir, $sumRec");
//            array_unshift($this->queue, $record);
//            array_pop()
//            $this->queue = array_merge([$record], $this->queue);
//            $this->queue[] = $record;
            $this->addQueue($record);
        }
    }

    function getOpposeDir($direction)
    {
        return self::OPPOSITE_DIRS[$direction];
    }

    function walkToNextKeypoint($x, $y, $dir, $sum)
    {
        $newX = $x + self::DIRS[$dir][0];
        $newY = $y + self::DIRS[$dir][1];

        // One new step is made
        $sum++;

        if (isset($this->keypoints[$newY][$newX])) {
            // reached next Keypoint - return the weight
            return [$newX, $newY, $dir, $sum];
        } else {
            $coords = $this->getSquareAround($newX, $newY);
            $coords = $this->filterCoords($coords, $x, $y, []);
            // This should leave us just the only one to go to (this point is not a keypoint, so should be one way)
            $coord = reset($coords);
            if (!in_array($this->mat[$y][$x], ['.', 'E', 'S'])) {
                die("WE SHOULD NOT BE HERE - not the dot, while walking");
            }
            // Adding the turn cost (if any)
            rslog($coord, '$coord - PASSING BY');
            $sum += $this->getTurnPrice($dir, $coord[2]);
            return $this->walkToNextKeypoint($newX, $newY, $coord[2], $sum);
        }

        die("ERROR - We should not be here!");
    }

    function findAndWeightLinks()
    {
        foreach ($this->keypoints as $y => &$row) {
            foreach ($row as $x => &$keypoint) {
                foreach ($keypoint['links'] as $dir => $link) {
                    if (!empty($link)) continue;
                    $keypoint2 = $this->walkToNextKeypoint($x, $y, $dir, 0);
                    $keypoint['links'][$dir] = $keypoint2;
                }
            }
        }
    }

    function markKeypoints()
    {
        foreach ($this->mat as $y => $row) {
            foreach ($row as $x => $symb) {
                if (in_array($symb, ['.', 'E', 'S'])) {
                    $coords = $this->getSquareAround($x, $y);
                    $exits = 0;
                    $keypoint = [];
                    foreach ($coords as $coord) {
                        list($cX, $cY, $cD) = $coord;
                        if (in_array($this->mat[$cY][$cX], [".", "S", "E"])) {
                            $keypoint['links'] ?? $keypoint['links'] = [];
                            $keypoint['links'][$cD] = [];
                            $exits++;
                        }
                    }
                    if ($exits > 2 || in_array($symb, ['E', 'S'])) {
                        $this->keypoints[$y][$x] = $keypoint;
                    }
                }
            }
        }
    }

    function closeDeadEnds() {
        foreach ($this->mat as $y => $row) {
            foreach ($row as $x => $symb) {
                if ($symb == ".") {
                    $coords = $this->getSquareAround($x, $y);
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
        if ($summ > 250000) return true;
        if (is_null($this->minimalSumm)) return false;
        return $summ > $this->minimalSumm;
    }

    function addResult($summ, $visited)
    {
//        rslog($summ, '$summ');
        if (is_null($this->minimalSumm) || ($this->minimalSumm > $summ)) {
            $this->minimalSumm = $summ;
            $this->operationsCnt++;
            rslog($summ, 'NEW MINIMAL!');
            $this->path = $visited;
            return true;
        }
        return false;
    }

    function getSquareAround($x, $y)
    {
        $dirs = self::DIRS;
        $coords = [];
        foreach ($dirs as $dir) {
            $xm = $x + $dir[0];
            $ym = $y + $dir[1];
            $coords[$dir[2]] = [$xm, $ym, $dir[2]];
        }
        return $coords;
    }

    function filterCoords($coords, $excludeX, $excludeY, $visited) {
        $canGoCoords = [];
        foreach ($coords as $coord) {
            if ($coord[0] == $excludeX && $coord[1] == $excludeY) {
                continue;
            }
            if (isset($visited[$coord[1]][$coord[0]])) {
                continue;
            }
            $symbol = $this->mat[$coord[1]][$coord[0]];
            if (in_array($symbol, ['.','E', 'S'])) {
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


    function printMat($visited, $ignoreTime = false)
    {
        if (!$ignoreTime && time() - $this->lastTimePrinted < 2) return false;
//        echo "\033c";
        $this->lastTimePrinted = time();
        $lines = [];
        if (isset($visited['path'])) {
            $vis = $visited['path'][count($visited['path']) - 1];
            list ($lastX, $lastY, $lastDir, $lastSum) = $vis;
        } else {
            $lastY = $lastX = null;
        }
        $queue = [];
        foreach ($this->queue as $oneq) {
            list ($qx, $qy) = $oneq;
            $queue[$qy][$qx] = 1;
        }
        foreach ($this->mat as $y => $row) {
            $line = "";
            foreach ($row as $x => $symb) {
                if (!empty($this->diffMat)) {
                    if ($symb !== $this->diffMat[$y][$x]) {
                        $line .= "\033[48;5;0m";
                    }
                }
                if ($x == $this->lastX and $y == $this->lastY) {
                    $symb = "@";
                    $symb = "\033[38;5;112m{$symb}\033[0m";
                } elseif (isset($queue[$y][$x])) {
                    $symb = "0";
                    $symb = "\033[38;5;112m{$symb}\033[0m";
                } elseif (isset($this->visited[$y][$x])) {
                    $symb = "I";
                    $symb = "\033[38;5;255m{$symb}\033[0m";
                } elseif (isset($this->keypoints[$y][$x]['links'])) {
                    $symb = "+";
                    $symb = "\033[38;5;255m{$symb}\033[0m";
                } elseif (isset($this->keypoints[$y][$x])) {
                    $symb = "+";
                    $symb = "\033[38;5;210m{$symb}\033[0m";
                } elseif (isset($this->deadEnds[$x][$y])) {
                    $symb = "X";
                    $symb = "\033[38;5;120m{$symb}\033[0m";
                } elseif (isset($visited[$y][$x])) {
                    $arr = array_keys($visited[$y][$x]);
                    $symb = reset($arr);
                    $symb = "8";
                    $symb = "\033[38;5;230m{$symb}\033[0m";
                } elseif ($symb == ".") {
                    $symb = " ";
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
            $this->diffMat = $this->mat;
        }

        $lineCnt = count($lines);

        for ($i = 0; $i < ceil($lineCnt/2); $i++) {
            $secondLine = $lines[$i + ceil($lineCnt/2)] ?? str_repeat('.', count($this->mat[0]));
            echo $secondLine ."   ".$lines[$i]  . "\n";
        }
//        foreach ($lines as $line) echo "$line \n";


        rslog($this->minimalSumm, '$this->minimalSumm');
        rslog($this->getSizeQueue(), '$this->getSizeQueue()');
        rslog($this->lastSumm, '$this->lastSumm');
        rslog($this->operationsCnt, 'OPERATIONS');


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
                if ($symb=='E') {
                    $this->endX = $x;
                    $this->endY = $y;
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