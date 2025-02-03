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

    function findShorts($grid, $size)
    {
        $shorts = [];
        $goods = ['.', 'E', 'S'];
        for ($yy = 1; $yy < $size-1; $yy++) {
            for ($xx = 1; $xx < $size-1; $xx++) {
                if ($grid[$yy][$xx] == "#") {
                    if (
                        (in_array($grid[$yy+1][$xx], $goods) && in_array($grid[$yy-1][$xx], $goods))
                        || (in_array($grid[$yy][$xx+1], $goods) && in_array($grid[$yy][$xx-1], $goods))
                    ){
                        $shorts[] = [$yy, $xx];
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

    function dijkstra($grid, $start, $end)
    {
        $directions = [[0, 1], [1, 0], [0, -1], [-1, 0]];
        $queue = new SplPriorityQueue();
        $queue->insert([$start, null, 0], 0); // Position, direction, cost
        $costs = [implode(',', $start) . ",-1" => 0];
        $predecessors = [];

        while (!$queue->isEmpty()) {
            [$pos, $direction, $cost] = $queue->extract();

            if ($pos === $end) {
                $path = [];
                while ($pos !== $start) {
                    $path[] = $pos;
                    $key = implode(',', $pos) . ",$direction";
                    if (!isset($predecessors[$key])) {
                        throw new Exception("Missing predecessor for position " . implode(',', $pos));
                    }
                    [$pos, $direction] = $predecessors[$key];
                }
                $path[] = $start;
                return [$cost, array_reverse($path)];
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
        // Main Execution
//        $filePath = "data/20-1.example";
        $filePath = "data/20-1.inp";
        [$grid, $size, $mintofind] = $this->readInput($filePath);

        $this->printGrid($grid, $size);

        [$start, $end] = $this->findPositions($grid);
        rslog([$start,$end], '[$start,$end]');

        [$baseCost, $primaryPath] = $this->dijkstra($grid, $start, $end);

        rslog($baseCost, '$baseCost');

        $shorts = $this->findShorts($grid, $size);

        $cnt = 0;
        rslog(count($shorts), 'count($shorts)');
        foreach ($shorts as $key => $short) {
            if ($key % 100 ==0) rslog($key, '$key');
            $grid2 = $grid;
            $grid2[$short[0]][$short[1]] = ".";
            [$newCost, $newPath] = $this->dijkstra($grid2, $start, $end);
            $save = $baseCost - $newCost;

            if ($save >= $mintofind) {
                $cnt++;
            }
        }


        rslog($cnt , 'Found places saveing at least ' . $mintofind);
    }

    function printGrid($gridOrig, $size, $path = [])
    {
        $grid = $gridOrig;
        if (!empty($path)) {
            foreach ($path as $one) {
                $grid[$one[0]][$one[1]] = "0";
            }
        }
        for ($y = 0 ; $y <= $size+1; $y++) {
            $line = '';
            for ($x = 0 ; $x <= $size+1; $x++) {
                $line .= $grid[$y][$x];
            }
            echo "$line \n";
        }
    }
}

