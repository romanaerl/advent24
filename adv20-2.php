<?php
class Def
{
    function readInput($filePath)
    {
        $grid = [];
        $lines = file($filePath);
        $mintosave = (int)array_shift($lines);
        foreach ($lines as $y => $line) {
            $lineArr = str_split(trim($line));
            foreach ($lineArr as $x => $symb) {
                $grid[$y][$x] = $symb;
            }
        }
        $size = count($grid);

        return [$grid, $size, $mintosave];
    }

    function calcDistance($start, $end)
    {
        return abs($start[0] - $end[0]) + abs($start[1] - $end[1])+1;
    }

    function findShorts(&$grid, $endPoint, $endToStartCost, $size, $path, $mintofind, $baseCost, $shortMaxSize)
    {
        $shorts = [];

        $regionStartX = max(1, $endPoint[1] - $shortMaxSize);
        $regionStartY = max(1, $endPoint[0] - $shortMaxSize);
        $regionEndX = min($size, $endPoint[1] + $shortMaxSize);
        $regionEndY = min($size, $endPoint[0] + $shortMaxSize);

        for ($yy = $regionStartY; $yy <= $regionEndY; $yy++) {
            for ($xx = $regionStartX; $xx <= $regionEndX; $xx++) {
                if ($yy == $endPoint[0] && $xx == $endPoint[1]) {
                    // same waypoint - no need to calculate
                    continue;
                }

                $pathHash = $this->getPosStr([$yy, $xx]);

                // There is a waypoint on these coordinates.
                if (isset($path[$pathHash])) {

                    $newPoint = $path[$pathHash];

                    // How much the short will add itself to the found cost
                    $shortCost = $this->calcDistance($newPoint[0], $endPoint);

                    if ($shortCost > $shortMaxSize) {
                        continue;
                    }

                    // Cost of the way from start to the found waypoint
                    $newOwnCost = $newPoint[1];

                    $newCost = $shortCost + $newOwnCost;

                    $savedCost = $endToStartCost - $newCost;

                    if ([$yy, $xx] ==  [63, 51] && $endPoint == [83, 71]) {
                        rslog($pathHash, '$pathHash');
                        rslog($newPoint, '$newPoint');
                        rslog($shortCost, '$shortCost');
                        rslog($newOwnCost, '$newOwnCost');
                        rslog($newCost, '$newCost');
                        rslog($savedCost, '$savedCost');
                        rslog($mintofind, '$mintofind');

                        die("HERE\n");
                    }


                    if ($savedCost >= $mintofind)  {
                        $shorts[] = [$newPoint[0], $endPoint, $newCost];
                    }
                }
            }
        }
        return $shorts;
    }

    function findPositions($grid)
    {
        $start = null;
        $end = null;
        foreach ($grid as $i => $row) {
            foreach ($row as $j => $cell) {
                if ($cell === 'S') {
                    $start = [$i, $j];
                } elseif ($cell === 'E') {
                    $end = [$i, $j];
                }
            }
        }
        return [$start, $end];
    }

    function getNeighbors($pos, $direction, $grid)
    {
        $directions = [[0, 1], [1, 0], [0, -1], [-1, 0]]; // Right, Down, Left, Up
        $neighbors = [];
        $rows = count($grid);
        $cols = count($grid[0]);

        foreach ($directions as $i => [$dx, $dy]) {
            $newDirection = $i;
            $newPos = [$pos[0] + $dx, $pos[1] + $dy];

            if (
                $newPos[0] >= 0 && $newPos[0] < $rows &&
                $newPos[1] >= 0 && $newPos[1] < $cols &&
                $grid[$newPos[0]][$newPos[1]] !== '#'
            ) {
                $neighbors[] = [$newPos, $newDirection];
            }
        }
        return $neighbors;
    }

    function getPosStr($a) {
        return $a[0] . "_" . $a[1];
    }

    function dijkstra($grid, $start, $end)
    {
        $queue = new SplPriorityQueue();
        $queue->insert([$start, null, 0], 0); // Position, direction, cost
        $costs = [implode(',', $start) . ",-1" => 0];
        $predecessors = [];

        while (!$queue->isEmpty()) {
            [$pos, $direction, $cost] = $queue->extract();

            if ($pos === $end) {
                $path = [];
                $steps = 0;
                while ($pos !== $start) {
                    $path[$this->getPosStr($pos)] = [$pos, $cost - $steps];
                    $steps++;
                    $key = implode(',', $pos) . ",$direction";
                    if (!isset($predecessors[$key])) {
                        throw new Exception("Missing predecessor for position " . implode(',', $pos));
                    }
                    [$pos, $direction] = $predecessors[$key];
                }
                $path[$this->getPosStr($start)] = [$start, 0];
                return [$cost, $path];
            }

            foreach ($this->getNeighbors($pos, $direction, $grid) as [$neighbor, $newDirection]) {
                $newCost = $cost + 1;
                $key = implode(',', $neighbor) . ",$newDirection";

                if (!isset($costs[$key]) || $newCost < $costs[$key]) {
                    $costs[$key] = $newCost;
                    $predecessors[$key] = [$pos, $direction];
                    $queue->insert([$neighbor, $newDirection, $newCost], -$newCost);
                }
            }
        }

        return [PHP_INT_MAX, []];
    }

    function run()
    {
        ini_set('memory_limit', '2048M');

        // Main Execution
//        $filePath = "data/20-1.example"; // 50+ = 32+31+29+39+25+23+20+19+12+14+12+22+4+3 = 285
        $filePath = "data/20-1.inp";
        [$grid, $size, $mintofind] = $this->readInput($filePath);

        $shortMaxSize = 21;
//        $shortMaxSize = 3;

        $this->printGrid($grid, $size);

        [$start, $end] = $this->findPositions($grid);
        rslog([$start,$end], '[$start,$end]');
        rslog($mintofind, '$mintofind');

        [$baseCost, $primaryPath] = $this->dijkstra($grid, $start, $end);

        rslog($baseCost, '$baseCost');

//        rslog($primaryPath, '$primaryPath');

        $allShorts = [];
        $allShortsCnt = 0;
        $stepsFromEnd = 0;
        foreach ($primaryPath as $pathPoint) {
//            rslog($pathPoint, '$pathPoint');
//            die();
            $distance = $this->calcDistance($pathPoint[0], $end);
            if ($distance > $shortMaxSize) {
                // The distance between the point and the end will not be covered by a short max size
//                continue;
            }
            $shorts = $this->findShorts($grid, $pathPoint[0], $pathPoint[1], $size, $primaryPath, $mintofind, $baseCost, $shortMaxSize);
            foreach ($shorts as $short) {
                $shortsHash = join("_", [$this->getPosStr($short[0]), $this->getPosStr($short[1])]);
                $backwardsShortHash = join("_", [$this->getPosStr($short[1]), $this->getPosStr($short[0])]);
                if (!isset($allShorts[$shortsHash]) && !isset($allShorts[$backwardsShortHash])) {
                    $allShorts[$shortsHash] = $short;
                    $allShortsCnt++;
                }
            }

            // We're moving from the end to start by the main route. Each step the distance between the searchin point
            // and the End will increase by one
            $stepsFromEnd++;
        }

//        rslog($allShorts, '$allShorts');



        $points = $allShorts;
//        rslog($primaryPath, '$primaryPath');
//        rslog($allShorts, '$allShorts');

        $this->printGrid($grid, $size, $primaryPath, $allShorts);
        rslog($allShortsCnt, '$allShortsCnt');
        rslog(reset($allShorts), '$allShorts');


    }

    function printGrid($gridOrig, $size, $path = [], $shorts = [])
    {
        $colour = [];
        $maxColour = $minColour = 0;
        $grid = $gridOrig;
        if (!empty($path)) {
            $ii = 0;
            foreach ($path as $one) {
                $colour[$one[0][0]][$one[0][1]] = $one[1];
                if ($one[1]>$maxColour)$maxColour=$one[1];
                if ($one[1]<$minColour)$minColour=$one[1];
                $one = $one[0];
                if ($grid[$one[0]][$one[1]] == ".")
                $grid[$one[0]][$one[1]] = (string)$ii;
                $ii++;
                if ($ii==10)$ii=0;
            }
        }
//        rslog($colour, '$colour');
        rslog($maxColour, '$maxColour');
        rslog($minColour, '$minColour');
        $stringarr = str_split("aAbBdDcCfFgG");
        $i = 0;
        foreach ($shorts as $short) {
//            rslog($stringarr[$ii], '$stringarr[$ii]');
//            rslog($short, '$short');
            $start = $short[0];
            $end = $short[1];
            $grid[$start[0]][$start[1]] = $stringarr[$i];
            $grid[$end[0]][$end[1]] = $stringarr[$i+1];
            $i+=2;
            if ($i >=10) {
                break;
            }
        }
        for ($y = 0 ; $y < $size; $y++) {
            $line = '';
            for ($x = 0 ; $x < $size; $x++) {
                $s = $grid[$y][$x];
                $s = str_replace(['#', ' '], [' ',' '], $s);
                $line .= $s;
            }
            echo "$line \n";
        }
    }
}

