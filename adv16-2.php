<?php
class Def
{
    function readInput($filePath)
    {
        $grid = [];
        $file = fopen($filePath, "r");
        while (($line = fgets($file)) !== false) {
            $grid[] = str_split(trim($line));
        }
        fclose($file);
        return $grid;
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
        $queue->insert([$start, -1, 0], 0); // Position, direction, cost
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
                $turnCost = ($direction !== -1 && $direction !== $newDirection) ? 1000 : 0;
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
        $filePath = "data/16-1.inp";
        $grid = $this->readInput($filePath);
        [$start, $end] = $this->findPositions($grid);
        [$baseCost, $primaryPath] = $this->dijkstra($grid, $start, $end);

        if ($primaryPath) {
            rslog($baseCost, '$baseCost');
            $allPaths = $this->findAllPaths($grid, $start, $end, $baseCost);
            rslog(count($allPaths), 'count($allPaths)');
            $count =$this->countUniquePoints($allPaths);
            rslog($count, '$count');
        } else {
            rslog("NO PATH", '"NO PATH"');
        }
    }
}

