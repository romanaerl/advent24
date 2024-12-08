<?php
class def
{
    protected $mat = [];
    protected $visualMat = [];
    protected $uniqueSymbols = [];
    protected $uniqueZones = [];

    function code()
    {
        ini_set('memory_limit', '1024M');
//        $this->readArray("data/81.example");
        $this->readArray("data/81.inp");
        $this->visualMat = $this->mat;
        $this->printArray($this->visualMat);
        rslog(count($this->uniqueSymbols), 'count($this->uniqueSymbols)');
        rslog($this->uniqueSymbols, '$this->uniqueSymbols');
        $this->countAntiZones();
        $this->printArray($this->visualMat);


        $count2 = 0;
        foreach ($this->visualMat as $y => $line)
            foreach ($line as $x => $one){
                if ($one !== '.') {
                    $count2++;
                }
            }

        rslog($count2, '$count2');
    }

    function getAllSteps($x, $diffX, $y, $diffY)
    {
        $size = count($this->mat);
        $coords = [];
        while (true) {
            $x = $x + $diffX;
            $y = $y + $diffY;
            if (!($x >= 0 && $y >= 0 && $x < $size && $y < $size))
            {
                break;
            }
            $coords[] = [$x, $y];
        }

        return $coords;
    }

    function getAntiZones ($x,$y,$xx,$yy) {

//        rslog($x, '$x');
//        rslog($y, '$y');
//        rslog($xx, '$xx');
//        rslog($yy, '$yy');
//        rslog("=============", '"============="');
//
        $size = count($this->mat);
//        rslog($size, '$size');
        $new_cooords = [];
        $diffX = abs($x - $xx);
        $diffY = abs($y - $yy);

        if ($x <= $xx && $y <= $yy) {

            $new_cooords = array_merge($new_cooords, $this->getAllSteps(min($x,$xx), -$diffX, min($y,$yy), -$diffY));
            $new_cooords = array_merge($new_cooords, $this->getAllSteps(max($x,$xx), +$diffX, max($y,$yy), +$diffY));

        } else {
            $new_cooords = array_merge($new_cooords, $this->getAllSteps(min($x,$xx), -$diffX, max($y,$yy), +$diffY));
            $new_cooords = array_merge($new_cooords, $this->getAllSteps(max($x,$xx), +$diffX, min($y,$yy), -$diffY));
        }




//        rslog($new_cooords, '$new_cooords');

        $count = 2; // we started with 2 antennas
        foreach ($new_cooords as $cooord) {
            if ($cooord[0]>=0 && $cooord[0]<$size && $cooord[1]>=0 && $cooord[1]<$size) {
                if (!isset($this->uniqueZones[$cooord[1]][$cooord[0]])) {
                    $this->uniqueZones[$cooord[1]][$cooord[0]] = 1;
                    $this->visualMat[$cooord[1]][$cooord[0]] = '#';
                    $count++;
                }
            }
        }

        return $count;
    }

    function countAntiZones()
    {
        $count = 0;
        foreach ($this->mat as $y => $line) {
            foreach ($line as $x => $one) {
                if ($one == ".") {
                    continue;
                }
                $nextY = $y;
                $nextX = $x+1;
                if ($nextX == count($this->mat)) {
                    $nextY++;
                    $nextX = 0;
                }
//                if ($one == "W") {
//                    rslog("------------------------------------- FOUNDW");
//                    rslog($x, '$x');
//                    rslog($y, '$y');
//                    rslog($nextX, '$nextX');
//                    rslog($nextY, '$nextY');
//                }
                for ($yy = $nextY; $yy < count($this->mat); $yy++) {
                    for ($xx = $nextX ; $xx < count($this->mat); $xx++) {
                        $one2 = $this->mat[$yy][$xx];
//                        if ($one == 'W') {
//                            rslog($one2, '$one2');
//                            rslog($xx, '$xx');
//                            rslog($yy, '$yy');
//                        }
                        if ($x == $xx and $y == $yy) {
                            // its the same point
                            continue;
                        }
                        if ($one !== $one2) {
                            continue;
                        }
//                        rslog($one, '$one FOUND ONE!');
                        // found a pair (!)
                        $count += $this->getAntiZones($x,$y,$xx,$yy);
                    }
                    // Next line should be read from the beginning
                    $nextX = 0;
                }
            }
        }

        rslog($count, '$count TOTAL');
    }

    function printArray($mat)
    {
        rslog("Matrix:");
        foreach ($mat as $y) {
            $line = '';
            foreach ($y as $x) {
                $line .= $x;
            }
            rslog($line);
        }
    }

    function readArray($filename)
    {
        $file = file($filename);
        $xx = $yy = 0;
        foreach ($file as $y => $str) {
            $arr = str_split(trim($str));
            foreach ($arr as $x => $one) {
                $this->mat[$yy][$xx] = $one;
                if ($one != '.')
                    in_array($one, $this->uniqueSymbols) || $this->uniqueSymbols[] = $one;
                $xx++;
            }
            $xx = 0;
            $yy++;
        }
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}