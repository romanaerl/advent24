<?php
class Def
{
    function readInput($filePath)
    {
        $grid = [];
        $lines = file($filePath);
        $bytestoread = (int)array_shift($lines);
        $size = (int)array_shift($lines);
        $read = 0;
        foreach ($lines as $line) {
            $read++;

            list($x,$y) = explode(',', trim($line));
            $grid[(int)$y+1][(int)$x+1] = '#';
            if ($read >= $bytestoread) {
                break;
            }
        }

        for($xx = 0; $xx<= $size+1; $xx++) {
            $grid[0][$xx] = '#';
            $grid[$size+1][$xx] = '#';
        }
        for($yy = 0; $yy<= $size+1; $yy++) {
            $grid[$yy][0] = '#';
            $grid[$yy][$size+1] = '#';
        }

        for($xx = 0; $xx<= $size+1; $xx++) {
            for($yy = 0; $yy<= $size+1; $yy++) {
                if (!isset($grid[$yy][$xx])) {
                    $grid[$yy][$xx] = '.';
                }
            }
        }

        $grid[1][1] = 'S';
        $grid[$size][$size] = 'E';

        return [$grid, $size];
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
                if (is_null($direction)) {
                    $turnCost = 0;
                } else {
                    $turnCost = ($direction !== -1 && $direction !== $newDirection) ? 1000 : 0;
                }
                $newCost = $cost + 1 + $turnCost;
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

    function findAllPaths($grid, $start, $end, $baseCost)
    {
        $allPaths = [];
        $originalGrid = $grid;

        [$cost, $primaryPath] = $this->dijkstra($grid, $start, $end);
        if (!$primaryPath) {
            return [];
        }

        foreach ($primaryPath as $blockPos) {
            $grid = $originalGrid;
            $grid[$blockPos[0]][$blockPos[1]] = '#';

            [$cost, $path] = $this->dijkstra($grid, $start, $end);
            if ($cost === $baseCost && $path) {
                $allPaths[] = $path;
            }
        }

        return $allPaths;
    }

    function countUniquePoints($paths)
    {
        $points = [];
        foreach ($paths as $path) {
            foreach ($path as $point) {
                $key = implode(',', $point);
                $points[$key] = true;
            }
        }
        return count($points);
    }

    function run()
    {
        // Main Execution
//        $filePath = "data/18-1.example";
        $filePath = "data/18-1.inp";
        [$grid, $size] = $this->readInput($filePath);
        $this->printGrid($grid, $size);
        [$start, $end] = $this->findPositions($grid);
        rslog([$start,$end], '[$start,$end]');




        [$baseCost, $primaryPath] = $this->dijkstra($grid, $start, $end);
        rslog(count($primaryPath)-1, 'count($primaryPath)-1');
        $this->printGrid($grid, $size, $primaryPath);

//        if ($primaryPath) {
//            rslog($baseCost, '$baseCost');
//            $allPaths = $this->findAllPaths($grid, $start, $end, $baseCost);
//            rslog(count($allPaths), 'count($allPaths)');
//            $count =$this->countUniquePoints($allPaths);
//            rslog($count, '$count');
//        } else {
//            rslog("NO PATH", '"NO PATH"');
//        }
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

