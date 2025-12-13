<?php
class Def
{
    protected $matrix = [];
    protected $points = [];
    protected $drawing = [];

    protected $lowestX = 0;
    protected $highestX = 0;
    protected $lowestY = 0;
    protected $highestY = 0;

    protected $sizes = [];
    protected $biggestSize = 0;
    protected $biggestSquare = [];

    protected $firstRed = [];

    protected $filledSpaces = [];


    protected $poly = [];
    protected $redPoints = [];

    protected $cache = [];

    protected $isDebug = true;
    protected $isMatrixPrint = true;



    function id($x, $y)
    {
        return $x . '_' . $y;
    }

    function convert($filepath)
    {
        $results = [];

        $lines = file($filepath);
        foreach ($lines as $y => $line) {
            $line = str_split(trim($line));
            foreach ($line as $x => $symb) {
                if ($symb == '#') {
                    $results[] = "$x,$y";
                }
            }
        }
        return $results;
    }

    function readInput($filePath)
    {
        $this->lowestX = 1000000000000000;
        $this->highestX = 0;
        $this->lowestY = 1000000000000000;
        $this->highestY = 0;

        if ($filePath === "data/25-09-3.inp") {
            $lines = $this->convert($filePath);
//            var_dump($lines);
        } else {
            $lines = file($filePath);
        }
//        die();

        foreach ($lines as $line) {
            [$x, $y] = explode(',', trim($line));
            $this->points[$this->id($x, $y)] = [$x, $y, 'R'];
            if ($this->lowestX > $x) $this->lowestX = $x;
            if ($this->highestX < $x) $this->highestX = $x;
            if ($this->lowestY > $y) $this->lowestY = $y;
            if ($this->highestY < $y) $this->highestY = $y;
        }
        $this->redPoints = $this->points;
    }

    function resetDrawing()
    {
        $this->drawing = [];
    }

    function drawSquareForVis($sq)
    {
        if (!$this->isMatrixPrint) return;
        $this->resetDrawing();
        $minX = min($sq[0][0], $sq[1][0]);
        $maxX = max($sq[0][0], $sq[1][0]);
        $minY = min($sq[0][1], $sq[1][1]);
        $maxY = max($sq[0][1], $sq[1][1]);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                $this->drawing[$this->id($x, $y)] = [$x, $y, '='];
            }
        }
    }

    function debug($val, $desc = '', $isSimple = false)
    {
        if (!$this->isDebug) return;

        if ($isSimple) {
            echo "$desc: $val\r\n";
        } else {
            var_dump($val);
            echo "\r\n";
        }
    }

    function printMatrix()
    {
        if (!$this->isMatrixPrint) return;

        echo "\r\n Matrix: \r\n\r\n";

        for ($y = $this->lowestY; $y <= $this->highestY; $y++) {
            for ($x = $this->lowestX; $x <= $this->highestX; $x++) {
                if ($this->isDrawing($x, $y)) {
                    echo "=";
                } else if ($this->isGreen($x, $y)) {
                    echo "G";
                } else if ($this->isRed($x, $y)) {
                    echo "R";
                } else if ($this->isTemp($x, $y)) {
                    echo "-";
                } else {
                    echo ".";
                }
            }
            echo "\r\n";
        }
        echo "\r\n\r\n";
    }

    function calcSize($p1, $p2)
    {
        $x = max($p1[0], $p2[0]) - min($p1[0], $p2[0]) + 1;
        $y = max($p1[1], $p2[1]) - min($p1[1], $p2[1]) + 1;
        return $y * $x;
    }

    function calcSquares()
    {
        foreach ($this->points as $point1) {
            foreach ($this->points as $point2) {
                $size = $this->calcSize($point1, $point2);
                if (!isset($this->sizes[$size])) $this->sizes[$size] = [];
                $this->sizes[$size][] = [$point1, $point2];
                if ($this->biggestSize < $size) {
                    $this->biggestSize = $size;
                    $this->biggestSquare = [$point1, $point2];
                }
            }
        }
    }

    function findFirstRed22()
    {
        for ($y = $this->lowestY; $y<= $this->highestY; $y++) {
            for($x = $this->lowestX; $x <= $this->highestX; $x++) {
                if ($this->isRed($x, $y)) {
                    return [$x, $y];
                }
            }
        }
        return [];
    }

    function findFirstRed()
    {
        $first = [10000000, 10000000];
        foreach ($this->redPoints as $point) {
            $exchange = false;
            if ($point[1] < $first[1] ) {
                $exchange = true;
            } else if ($point[0] < $first[0] && $point[1] == $first[1]) {
                $exchange = true;
            }
            if ($exchange) {
                $first = $point;
            }
        }
        return $first;
    }

    function isDot($x, $y)
    {
        return !isset($this->points[$this->id($x, $y)]) || ($this->points[$this->id($x, $y)][2] === '.');
    }

    function isDrawing($x, $y)
    {
        return isset($this->drawing[$this->id($x, $y)]);
    }

    function isTemp($x, $y)
    {
        return isset($this->points[$this->id($x, $y)]) && ($this->points[$this->id($x, $y)][2] === '-');
    }

    function isInPoly($x, $y)
    {
        if (isset($this->cache[$this->id($x, $y)])) {
            return $this->cache[$this->id($x, $y)];
        }
        if ($this->pointInPolygon([$x, $y], $this->poly)) {
            $this->cache[$this->id($x, $y)] = true;
            return true;
        }

        for($i = 1; $i<= count($this->poly)-1; $i++) {
            $a = [$this->poly[$i-1][0], $this->poly[$i-1][1]];
            $b = [$this->poly[$i][0], $this->poly[$i][1]];
            if ($this->pointOnSegment($a, $b, [$x, $y])) {
                $this->cache[$this->id($x, $y)] = true;
                return true;
            }
        }

        $this->cache[$this->id($x, $y)] = false;
        return false;

    }

    function isGreen($x, $y)
    {
        if (isset($this->filledSpaces[$x][$y])) {
            return true;
        }
        return isset($this->points[$this->id($x, $y)]) && ($this->points[$this->id($x, $y)][2] === 'G');
    }

    function isRed($x, $y)
    {
        return isset($this->points[$this->id($x, $y)]) && ($this->points[$this->id($x, $y)][2] === 'R');
    }

    function searchNext22($x, $y, $dir)
    {
        $dirs = [
            'u' => [0, -1],
            'd' => [0, 1],
            'l' => [-1, 0],
            'r' => [1, 0],
        ];
        $inRegion = true;
        $found = false;
        $newX = $x;
        $newY = $y;
        while ($inRegion) {
            $newX = $newX + $dirs[$dir][0];
            $newY = $newY + $dirs[$dir][1];
            if ($newX > $this->highestX + 1) $inRegion = false;
            if ($newX < $this->lowestX - 1) $inRegion = false;
            if ($newY > $this->highestY + 1) $inRegion = false;
            if ($newY < $this->lowestY - 1) $inRegion = false;
            if ($inRegion) {
                if ($this->isRed($newX, $newY)) {
                    return [true, [$newX, $newY]];
                }
            }
        }
        return [false, []];
    }

    function searchNext($x, $y, $dir)
    {
        $distance = 10000000;
        $fpoint = [];
        foreach ($this->redPoints as $point) {
//            if (($point[0] == $x)&&($point[1] == $y)) {
//                // this is the same point
//                continue;
//            }
            foreach ($this->poly as $pol) {
                if (($pol[0] == $point[0]) && ($pol[1] == $point[1])) {
                    // We have this point in poly already
                    continue;
                }
            }
            if (in_array($dir, ['u', 'd']) && $point[0] == $x) {
                if ($dir == 'u' && $point[1] < $y) {
                    $dist = $y - $point[1];
                    if ($distance > $dist) {
                        $distance = $dist;
                        $fpoint = $point;
                    }
                } elseif ($dir == 'd' && $point[1] > $y) {
                    $dist = $point[1] - $y;
                    if ($distance > $dist) {
                        $distance = $dist;
                        $fpoint = $point;
                    }
                }
            }
            if (in_array($dir, ['l', 'r']) && $point[1] == $y) {
                if ($dir == 'l' && $point[0] < $x) {
                    $dist = $x - $point[0];
                    if ($distance > $dist) {
                        $distance = $dist;
                        $fpoint = $point;
                    }
                } elseif ($dir == 'r' && $point[0] > $x) {
                    $dist = $point[0] - $x;
                    if ($distance > $dist) {
                        $distance = $dist;
                        $fpoint = $point;
                    }
                }
            }
        }
        if (!empty($fpoint)) {
            return [true, $fpoint];
        } else {
            return [false, []];
        }
    }

    function drawOneFence($x, $y, $x1, $y1, $dir)
    {
        $dirs = [
            'u' => [0, -1],
            'd' => [0, 1],
            'l' => [-1, 0],
            'r' => [1, 0],
        ];

        $newX = $x;
        $newY = $y;
        $finished = false;
        while (!$finished) {
            $newX += $dirs[$dir][0];
            $newY += $dirs[$dir][1];
            if ($newY == $y1 && $newX == $x1) {
                $finished = true;
            } else {
                $this->points[$this->id($newX, $newY)] = [$newX, $newY, 'G'];
            }
        }
    }

    function getOpposeDir($dir)
    {
        $dirs = [
            'u' => 'd',
            'd' => 'u',
            'l' => 'r',
            'r' => 'l',
        ];


        return $dirs[$dir];
    }

    function drawFences22($x, $y, $from, $first = false) {

//        var_dump($x, $y, $from, "NEEEEEEWW");

        $dirs = ['u', 'd', 'l', 'r'];

        if ($first) $dirs = ['r', 'd'];

        if ($first) {
            $this->poly[] = [$x, $y];
        }


        foreach ($dirs as $dir) {
//            var_dump($x, $y, $from, $dir, "ALL");
            if ($dir == $from) continue;

            [$found, $foundPoint] = $this->searchNext22($x, $y, $dir);
            if ($found) {
//                var_dump($foundPoint[0], $foundPoint[1], 'FOUND');
                $this->drawOneFence($x, $y, $foundPoint[0], $foundPoint[1], $dir);
                $this->poly[] = [$foundPoint[0], $foundPoint[1]];
                if ($foundPoint[0] == $this->firstRed[0] && $foundPoint[1] == $this->firstRed[1]) {
                    return false;
                }

                if (!$this->drawFences($foundPoint[0], $foundPoint[1], $this->getOpposeDir($dir), false)) {
                    return false;
                }
            }
        }
        return false;
    }

    function drawFences($x, $y, $from, $first = false) {

//        var_dump($x, $y, $from, "NEEEEEEWW");

        $dirs = ['u', 'd', 'l', 'r'];

        if ($first) $dirs = ['r', 'd'];

        if ($first) {
            $this->poly[] = [$x, $y];
        }


        foreach ($dirs as $dir) {
            // Not going back (!)
            if ($dir == $this->getOpposeDir($from)) continue;

            [$found, $foundPoint] = $this->searchNext($x, $y, $dir);
            if ($found) {
//                var_dump($foundPoint[0], $foundPoint[1], 'FOUND');
                $this->drawOneFence($x, $y, $foundPoint[0], $foundPoint[1], $dir);
                if ($foundPoint[0] == $this->firstRed[0] && $foundPoint[1] == $this->firstRed[1]) {
                    return false;
                }
                $this->poly[] = [$foundPoint[0], $foundPoint[1]];

                if (!$this->drawFences($foundPoint[0], $foundPoint[1], $dir, false)) {
                    return false;
                }
            }
        }
        return false;
    }




    function pointInPolygon(array $edges, int $px, int $py): bool
    {
        $this->debug("", '--------------', true);
        $this->debug("", "Checking point:[$px, $py]", true);
        // check edges
        foreach ($edges as $e) {
            $x1 = $e[0][0];
            $y1 = $e[0][1];
            $x2 = $e[1][0];
            $y2 = $e[1][1];

            // horizontal edge
            if ($x1 === $x2) {
                if ($px === $x1 && $py >= min($y1, $y2) && $py <= max($y1, $y2)) {
                    $this->debug("", "Result of check: the point is on the Vertical Edge [$x1, $y1] - [$x2, $y2]", true);
                    return true;
                }
            }
            // vertical
            elseif ($y1 === $y2) {
                if ($py === $y1 && $px >= min($x1, $x2) && $px <= max($x1, $x2)) {
                    $this->debug("", "Result of check: the point is on the Horizontal Edge [$x1, $y1] - [$x2, $y2]", true);
                    return true;
                }
            }
        }

        // going right through each edge. Each wall pair will mean we are still inside
        $inside = false;
        foreach ($edges as $e) {
            $x1 = $e[0][0];
            $y1 = $e[0][1];
            $x2 = $e[1][0];
            $y2 = $e[1][1];

//             horizontal only, skipping vertical
            if ($y1 !== $y2) {
                // Делаем из горизонтальной еще одну пиксельную вертикальную
                $y1 = max($y1, $y2);
                $y2 = max($y1, $y2);

//                continue;
            }

            // order of X to check min max correctly
            if ($x1 > $x2) {
                $tmp = $x1;
                $x1  = $x2;
                $x2  = $tmp;
            }

            // Beam goes down forever
            if (
                $px >= $x1 // beam does not pass on the left of the wal
                && $px < $x2 //  beam does not pass on the right of the wall
                // we also do not include either the left or the right corner
                && $py < $y1 // beam will cross the wall eventually, we count it
            )  {
                $inside = !$inside;
                $this->debug("", "Crossed the wall (edge [$x1, $y1] - [$x2, $y2], inside: ". ($inside ? "TRUE" : "FALSE"), true);
            }
        }

        $this->debug("", "Result of check, the point is " . ($inside ? "INSIDE" : "OUTSIDE"), true);
        return $inside;
    }

    function checkSquare($sq)
    {
        $x1 = $sq[0][0];
        $y1 = $sq[0][1];
        $x2 = $sq[1][0];
        $y2 = $sq[1][1];

        $squarePoints = [
            [$x1, $y1],
            [$x2, $y1],
            [$x2, $y2],
            [$x1, $y2],
        ];

        $squareEdges = [
            [$squarePoints[0], $squarePoints[1]],
            [$squarePoints[1], $squarePoints[2]],
            [$squarePoints[2], $squarePoints[3]],
            [$squarePoints[3], $squarePoints[0]],
        ];

        $polyEdges = [];
        for ($i = 1; $i <= count($this->poly) - 1; $i++) {
            $a = [(int)$this->poly[$i - 1][0], (int)$this->poly[$i - 1][1]];
            $b = [(int)$this->poly[$i][0], (int)$this->poly[$i][1]];
            $polyEdges[] = [$a, $b];
        }

        $this->debug("", "PolyEdges", true);
        foreach ($polyEdges as $k => $one) {
            $this->debug("", "$k => [{$one[0][0]}, {$one[0][1]}] - [{$one[1][0]}, {$one[1][1]}]", true);
        }
        $this->debug("", "SquareEdges", true);
        foreach ($squareEdges as $k => $one) {
            $this->debug("", "$k => [{$one[0][0]}, {$one[0][1]}] - [{$one[1][0]}, {$one[1][1]}]", true);
        }

        // Checking all square corners are in polygon
        foreach ($squarePoints as $point) {
            if (!$this->pointInPolygon($polyEdges, $point[0], $point[1])) {
                return false;
            }
        }

        // Checking all square edges do not cross any poly edge
        foreach ($polyEdges as $pkey => $polyEdge) {
            foreach ($squareEdges as $skey => $sqEdge) {
                $this->debug("Checking PolyEdge $pkey and SquareEdge $skey....", "", true);
                $sqVert = $this->isVertical($sqEdge);
                $polyVert = $this->isVertical($polyEdge);
                if ($polyVert == $sqVert) {
                    // We do skip parallel edges within this check
                    $this->debug("Parallel, skipping..", ", true");
                    continue;
                }

                // Making two lines one always vertical, another always horizontal
                $vert = $polyVert ? $polyEdge : $sqEdge;
                $hor = $polyVert ? $sqEdge : $polyEdge;

                // Vertical point A coordinate X
                $vax = $vert[0][0]; // Vertical X should match
                $vay = min($vert[0][1], $vert[1][1]); // yA is always less than yB
                $vbx = $vert[1][0]; // Vertical X should match
                $vby = max($vert[0][1], $vert[1][1]); // yA is always less than yB

                // Horizontal point A coordinate X
                $hax = min($hor[0][0], $hor[1][0]); // xA is always less than xB
                $hay = $hor[0][1]; // Horizontal Y should match
                $hbx = max($hor[0][0], $hor[1][0]);; // xA is always less than xB
                $hby = $hor[1][1]; // Horizontal Y should match

                $this->debug("$hax, $vax, $hbx", "hax<vax<hbx: ", true);
                $this->debug("$vay, $hay, $vby", "vay<hay<vby: ", true);

                if (
                    $hax < $vax && $vax < $hbx
                    && $vay < $hay && $hay < $vby
                ) {
                    // These edges Crossing
//                    $this->debug([$polyEdge, $sqEdge], "These edges DO CROSS");
                    $this->debug("These edges DO CROSS", '', true);
                    return false;
                }

            }
        }

        return true;
    }

    function isVertical($ab)
    {
        return [$ab[0][0] == $ab[1][0]];
    }


    function code() {
        $this->printMatrix();

        $this->calcSquares();

        $prevPoint = false;
        foreach ($this->redPoints as $point) {
            $this->poly[] = $point;
            if ($prevPoint) {
                $this->polyEdges = [$prevPoint, $point];
            }
        }

//        $this->drawFences($this->firstRed[0], $this->firstRed[1], 'l', true);


        echo "--" . count($this->poly) . '--' . count($this->redPoints)  . '--' ;
        var_dump(array_shift($this->poly));
        var_dump(array_pop($this->poly));
//        die();

        $this->printMatrix();

        $keys = array_keys($this->sizes);
        rsort($keys);

        $cnt = 0;
        foreach ($keys as $size) {
            var_dump($size);
            foreach ($this->sizes[$size] as $square) {
                $this->debug(">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>", "", true);
                $this->debug("checking square [{$square[0][0]}, {$square[0][1]}]-[{$square[1][0]}, {$square[1][1]}]", "", true);
//                $this->debug($square, "SQUARE");
                $this->printMatrix();
                $this->drawSquareForVis($square);
                $this->printMatrix();
                $this->resetDrawing();
                if ($this->checkSquare($square)) {
                    $cnt++;
                    if (!$this->isDebug) {
                        $this->isDebug = true;
                        $this->checkSquare($square);
                        $this->isDebug = false;
                    }
                    if ($cnt > 0) {
                        $this->printMatrix();
                        $this->drawSquareForVis($square);
                        $this->printMatrix();
                        $this->resetDrawing();
                        var_dump($this->poly);
                        return $size;
                    }
                }
            }
        }


        return "NOT FOUND!!!\r\n";
    }

//    function pointOnSegment($A, $B, $T) {
//        $cross = ($B[0] - $A[0]) * ($T[1] - $A[1])
//            - ($B[1] - $A[1]) * ($T[0] - $A[0]);
//
//        if (abs($cross) > 1e-8) return false; // не на линии
//
//        if (min($A[0], $B[0]) <= $T[0] && $T[0] <= max($A[0], $B[0]) &&
//            min($A[1], $B[1]) <= $T[1] && $T[1] <= max($A[1], $B[1])) {
//            return true;
//        }
//        return false;
//    }
//
//    function pointInPolygon($point, $polygon) {
//        $x = $point[0];
//        $y = $point[1];
//        $inside = false;
//
//        $n = count($polygon);
//        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
//            $xi = $polygon[$i][0];
//            $yi = $polygon[$i][1];
//            $xj = $polygon[$j][0];
//            $yj = $polygon[$j][1];
//
//            $intersect = (($yi > $y) != ($yj > $y)) &&
//                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);
//
//            if ($intersect) {
//                $inside = !$inside;
//            }
//        }
//        return $inside;
//    }




    function run()
    {
        // Main Execution
        $filePath = "data/25-09-1.example"; $this->isMatrixPrint = true; $this->isDebug = true;
//        $filePath = "data/25-09-1.inp"; $this->isMatrixPrint = false; $this->isDebug = false;
//        $filePath = "data/25-09-3.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }


}


ini_set("memory_limit", '32000M');
ini_set("max_execution_time", '60000');
ini_set("max_input_nesting_level", '10000');
//system('stty cbreak -echo');
$Def = new Def();
$Def->run();

