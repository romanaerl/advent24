<?php
class Def
{
    protected $curPositions = [];
    protected $directions = [[0, 1], [1, 0], [0, -1], [-1, 0]]; // Right, Down, Left, Up
    protected $directionSymbols = [">", "v", "<", "^"]; // Right, Down, Left, Up
    protected $automatConfig1 = [
        ["7","8","9"],
        ["4","5","6"],
        ["1","2","3"],
        ["#","0","A"],
    ];
    protected $automatConfig2 = [
        ["#","^","A"],
        ["<","v",">"],
    ];

    function readInput($filePath)
    {
        $codes = file($filePath);
        array_walk($codes, fn(&$val) => $val = str_split(trim($val)));

        return $codes;
    }

    function calcDistanceBetweenPositions($pos1, $pos2)
    {
        return (abs($pos1[1] - $pos2[1]) + abs($pos1[0] - $pos2[0])) + 0;
    }


    function getPosStr($a) {
        return $a[0] . "_" . $a[1];
    }

    function dijkstra($grid, $start, $end, $deepCoefficient)
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
                    $path[$this->getPosStr($pos)] = [$pos, $cost - $steps, $direction];
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
                $costDirection = 0;
                if (!is_null($direction)) {
                    $prevPos = $this->findButtons($this->directionSymbols[$direction], $this->automatConfig2)[0];
                    $newPos = $this->findButtons($this->directionSymbols[$newDirection], $this->automatConfig2)[0];
                    $costDirection = $this->calcDistanceBetweenPositions($prevPos, $newPos);
                    $costDirection = $machineId;
                }
                $newCost = $cost + 1 + $costDirection;
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

    function findButtons($search, $grid)
    {
        $results = [];
        foreach ($grid as $y => $row)
            foreach ($row as $x => $symb)
                if ($symb == $search) {
                    $results[] = [$y, $x];
                }
        return $results;
    }

    function initPositions($machinesChainConfig)
    {
        foreach ($machinesChainConfig as $id => $data) {
            // init positions
            $this->curPositions[$id] = $this->findButtons('A', $data['config'])[0];
        }
    }

    function findShortestPath($start, $end, $grid, $deepCoefficient)
    {
//        rslog($start, '$start');
//        rslog($end, '$end');
//        rslog($grid, '$grid');
        [$_, $path] = $this->dijkstra($grid, $start, $end, $deepCoefficient);
//        rslog($path, '$path');

        $pathCode = "";
        foreach ($path as $step) {
            if (isset($step[2])) {
                $pathCode = $this->directionSymbols[$step[2]] . $pathCode;
            }
        }
        return $pathCode;
    }

    function getNeighbors($pos, $direction, $grid)
    {
        $directions = $this->directions;
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

    function getShortestInstruction($code, $machineId, $grid, $deepCoefficient)
    {
        $codeCombination = "";
        $start = $this->curPositions[$machineId];
        foreach ($code as $symb) {
//            if ($symb == "|") continue;
            $end = $this->findButtons($symb, $grid)[0];
            // Way to the button we search
            $shortestPath = $this->findShortestPath($start, $end, $grid, $deepCoefficient);
            $codeCombination .= $shortestPath;

//            $shrt = str_split($shortestPath);
//            $shrt = $this->optimizePath($shrt);


            // Press of a button
//            $codeCombination .= "A|";
            $codeCombination .= "A";
            $start = $end;
        }
        $this->curPositions[$machineId] = $end;

        return $codeCombination;
    }

    function run()
    {
        ini_set('memory_limit', '2048M');
        $codes = $this->readInput("data/21-1.example");
//        $codes = $this->readInput("data/21-1.inp");

        $machinesChainConfig = [
            'numeric1' => ['config' => $this->automatConfig1],
            'keypad1' => ['config' => $this->automatConfig2],
//            'keypad2' => ['config' => $this->automatConfig2],
            'mykeypad' => ['config' => $this->automatConfig2],
        ];

        $this->initPositions($machinesChainConfig);

        $sum = 0;
        foreach ($codes as $code) {
            $curCode = join($code);
            rslog($code, '$code');
            $deepCoefficient = count($machinesChainConfig)+2;
            foreach ($machinesChainConfig as $machineId => $machine) {
                $curCodeArr = str_split($curCode);
                $curCode = $this->getShortestInstruction($curCodeArr, $machineId, $machine['config'], $deepCoefficient);
                $deepCoefficient--;
                rslog($curCode, '$curCode');
            }
            rslog(join($code), 'join($code)');
            $lenCode = strlen($curCode);
            $numCode = (int)str_replace("A", "", join($code));
            rslog($lenCode, '$lenCode');
            rslog($numCode, '$numCode');
            $sum += $lenCode * $numCode;
        }

        rslog($sum, '$sum');


//        rslog($codes, '$codes');
    }

    function printGrid($gridOrig, $size, $path = [], $shorts = [])
    {

    }
}

