<?php
class Def
{
    protected $mat = [];
    protected $startX;
    protected $startY;
    protected $endX;
    protected $endY;
    protected $distances = [];
    protected $visited = [];
    protected $priorityQueue;

    const DIRS = [
        [0, -1],  // Up
        [1, 0],   // Right
        [0, 1],   // Down
        [-1, 0]   // Left
    ];

    function __construct()
    {
        $filename = "data/16-1.inp";
        $this->readArray($filename);
    }

    function readArray($filename)
    {
        $lines = file($filename);
        foreach ($lines as $y => $line) {
            $line = str_split(trim($line));
            foreach ($line as $x => $symb) {
                $this->mat[$y][$x] = $symb;
                if ($symb == 'S') {
                    $this->startX = $x;
                    $this->startY = $y;
                }
                if ($symb == 'E') {
                    $this->endX = $x;
                    $this->endY = $y;
                }
            }
        }
    }

    function initialize()
    {
        $rows = count($this->mat);
        $cols = count($this->mat[0]);
        for ($y = 0; $y < $rows; $y++) {
            for ($x = 0; $x < $cols; $x++) {
                $this->distances[$y][$x] = PHP_INT_MAX;
                $this->visited[$y][$x] = false;
            }
        }
        $this->distances[$this->startY][$this->startX] = 0;
        $this->priorityQueue = new SplPriorityQueue();
        $this->priorityQueue->insert([$this->startX, $this->startY], 0);
    }

    function dijkstra()
    {
        while (!$this->priorityQueue->isEmpty()) {
            list($x, $y) = $this->priorityQueue->extract();

            if ($this->visited[$y][$x]) {
                continue;
            }
            $this->visited[$y][$x] = true;

            foreach (self::DIRS as $dir) {
                $newX = $x + $dir[0];
                $newY = $y + $dir[1];

                if ($this->isValid($newX, $newY) && !$this->visited[$newY][$newX]) {
                    $newDistance = $this->distances[$y][$x] + 1; // Assume uniform cost for now

                    if ($newDistance < $this->distances[$newY][$newX]) {
                        $this->distances[$newY][$newX] = $newDistance;
                        $this->priorityQueue->insert([$newX, $newY], -$newDistance);
                    }
                }
            }
        }

        return $this->distances[$this->endY][$this->endX];
    }

    function isValid($x, $y)
    {
        return isset($this->mat[$y][$x]) && $this->mat[$y][$x] != '#';
    }

    function run()
    {
        $this->initialize();
        $shortestPath = $this->dijkstra();

        echo "Shortest path cost: " . $shortestPath . PHP_EOL;
    }
}