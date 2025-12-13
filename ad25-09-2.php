<?php
class Def
{
    protected $points = [];
    protected $drawing = [];

    protected $lowestX = 0;
    protected $highestX = 0;
    protected $lowestY = 0;
    protected $highestY = 0;

    protected $sizes = [];

    protected $firstRed = [];

    protected $poly = [];
    protected $polyEdges = [];
    protected $redPoints = [];

    protected $isDebug = true;
    protected $isMatrixPrint = true;


    function id($x, $y)
    {
        return $x . '_' . $y;
    }

    function readInput($filePath)
    {
        $this->lowestX = 1000000000000000;
        $this->highestX = 0;
        $this->lowestY = 1000000000000000;
        $this->highestY = 0;

        if ($filePath === "data/25-09-3.inp") {
            die("this will not work");
        } else {
            $lines = file($filePath);
        }

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

    function printMatrix2($sq)
    {


// size of each cell in pixels (makes the image bigger & clearer)
        $cellSize = .1;
        $drawSize = 20;

        $height = $this->highestY - $this->lowestY + 4000;
        $width = $this->highestX - $this->lowestX + 4000;

        $imgWidth  = $width * $cellSize + $drawSize +1;
        $imgHeight = $height * $cellSize + $drawSize +1;

        $image = imagecreatetruecolor($imgWidth, $imgHeight);

// colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $green = imagecolorallocate($image, 0, 255, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);
        $red = imagecolorallocate($image, 255, 0, 0);

// fill background white
        imagefill($image, 0, 0, $black);

        foreach ($this->polyEdges as $edge) {
            $addXThick = $this->isVertical($edge) ? $drawSize : 0;
            $addYThick = $this->isVertical($edge) ? $drawSize : 0;
            imagefilledrectangle(
                $image,
                floor($edge[0][0] * $cellSize),
                floor($edge[0][1] * $cellSize),
                floor($edge[1][0] * $cellSize) + $addXThick ,
                floor($edge[1][1] * $cellSize) + $addYThick ,
                $white
            );
        }

        $minX = min($sq[0][0], $sq[1][0]);
        $maxX = max($sq[0][0], $sq[1][0]);
        $minY = min($sq[0][1], $sq[1][1]);
        $maxY = max($sq[0][1], $sq[1][1]);

        imagefilledrectangle(
            $image,
            floor($minX * $cellSize),
            floor($minY * $cellSize),
            floor($maxX * $cellSize),
            floor($maxY * $cellSize),
            $red
        );



// draw cells
//        for ($y = 0; $y < $height; $y++) {
//            for ($x = 0; $x < $width; $x++) {
//                $value = $matrix[$y][$x]; // 0 or 1
//
//                $color = $value ? $black : $white;
//
//                // top-left corner of cell
//                $px = $x * $cellSize;
//                $py = $y * $cellSize;
//
//                imagefilledrectangle(
//                    $image,
//                    $px, $py,
//                    $px + $cellSize - 1,
//                    $py + $cellSize - 1,
//                    $color
//                );
//            }
//        }

// Output directly to browser as PNG
        imagepng($image, "imagead25.png");
        imagedestroy($image);
    }

    function printMatrix()
    {
        if (!$this->isMatrixPrint) return;

        echo "\r\n Matrix: \r\n\r\n";
        for ($y = $this->lowestY; $y <= $this->highestY; $y++) {
            for ($x = $this->lowestX; $x <= $this->highestX; $x++) {
                if ($this->isDrawing($x, $y)) {
                    echo "=";
                } else if ($this->isRed($x, $y)) {
                    echo "R";
                } else if ($this->pointInPolygon($this->polyEdges, $x, $y)) {
                    echo "G";
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
                if ($point2 == $point1) continue;
                $size = $this->calcSize($point1, $point2);
                if (!isset($this->sizes[$size])) $this->sizes[$size] = [];
                if (in_array([$point2, $point1], $this->sizes[$size])) {
                    // Skip duplicates
                    continue;
                }
                $this->sizes[$size][] = [$point1, $point2];
            }
        }
    }


    function isDrawing($x, $y)
    {
        return isset($this->drawing[$this->id($x, $y)]);
    }


    function isRed($x, $y)
    {
        return isset($this->points[$this->id($x, $y)]) && ($this->points[$this->id($x, $y)][2] === 'R');
    }

    function searchNext($x, $y, $dir)
    {
        $distance = 10000000;
        $fpoint = [];
        foreach ($this->redPoints as $point) {
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
            if ($x1 == $x2) {
                if ($px == $x1 && $py >= min($y1, $y2) && $py <= max($y1, $y2)) {
                    $this->debug("", "Result of check: the point is on the Vertical Edge [$x1, $y1] - [$x2, $y2]", true);
                    return true;
                }
            }
            // vertical
            elseif ($y1 == $y2) {
                if ($py == $y1 && $px >= min($x1, $x2) && $px <= max($x1, $x2)) {
                    $this->debug("", "Result of check: the point is on the Horizontal Edge [$x1, $y1] - [$x2, $y2]", true);
                    return true;
                }
            }
            $this->debug("", "Result of check: the point is NOT ON THE Edge [$x1, $y1] - [$x2, $y2]", true);
        }

        // going right through each edge. Each wall pair will mean we are still inside
        $inside = false;
        foreach ($edges as $k=>$e) {
            $x1 = $e[0][0];
            $y1 = $e[0][1];
            $x2 = $e[1][0];
            $y2 = $e[1][1];

            // horizontal only, skipping vertical
            if ($y1 !== $y2) {
                continue;
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
                && $py < $y1 // beam will cross the wall eventually, we count it
            )  {
                $inside = !$inside;
                $this->debug("", "Crossed the wall (edge $k:[$x1, $y1] - [$x2, $y2], inside: ". ($inside ? "TRUE" : "FALSE"), true);
            }
        }

        $this->debug("", "Result of check, the point is " . ($inside ? "INSIDE" : "OUTSIDE"), true);
        return $inside;
    }

    function checkSquare($sq, $SHOW = false)
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

        $this->debug("", "PolyEdges", true);
        foreach ($this->polyEdges as $k => $one) {
            $this->debug("", "$k => [{$one[0][0]}, {$one[0][1]}] - [{$one[1][0]}, {$one[1][1]}]", true);
        }
        $this->debug("", "SquareEdges", true);
        foreach ($squareEdges as $k => $one) {
            $this->debug("", "$k => [{$one[0][0]}, {$one[0][1]}] - [{$one[1][0]}, {$one[1][1]}]", true);
        }

        // Checking all square corners are in polygon
        foreach ($squarePoints as $point) {
            if (!$this->pointInPolygon($this->polyEdges, $point[0], $point[1])) {
                return false;
            }
        }

        // Checking all square edges do not cross any poly edge
        foreach ($this->polyEdges as $pkey => $polyEdge) {
//            if ($SHOW && $pkey == 248) die();
            foreach ($squareEdges as $skey => $sqEdge) {
                $this->debug("Checking PolyEdge $pkey and SquareEdge $skey....", "", true);
                $sqVert = $this->isVertical($sqEdge);
                $polyVert = $this->isVertical($polyEdge);
                if ($polyVert == $sqVert) {
                    // We do skip parallel edges within this check
                    $this->debug("Parallel, skipping..", ", true");
                    continue;
                }

//                var_dump($polyEdge);
//                var_dump($sqEdge);

                // Making two lines one always vertical, another always horizontal
                $hor = $polyVert ? $polyEdge : $sqEdge;
                $vert = $polyVert ? $sqEdge : $polyEdge;

//                var_dump($vert, 'vert');
//                var_dump($hor, 'hor');

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

        // Fill poly edges
        $prevPoint = false;
        foreach ($this->redPoints as $point) {
            $this->poly[] = $point;
            if ($prevPoint) {
                $this->polyEdges[] = [$prevPoint, $point];
            }
            $prevPoint = $point;
        }
        $this->polyEdges[] = [$prevPoint, reset($this->redPoints)];


        //Checking that the amount of poly edges equals to the amount of poly points
        echo "--" . count($this->poly) . '--' . count($this->redPoints)  . "\r\n" ;

        $this->printMatrix();

        // Sorting all squares by size DESC
        $keys = array_keys($this->sizes);
        rsort($keys);

        $cnt = 0;
        foreach ($keys as $size) {
            $this->debug($size, "\r\n\r\n\r\n=====>>>SIZE:", true);
            echo "=====>>>SIZE: $size\r\n";
            foreach ($this->sizes[$size] as $square) {
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
                        $this->checkSquare($square, TRUE);
                        $this->isDebug = false;
                    }
                    if ($cnt > 0) {
                        $this->printMatrix();
                        $this->drawSquareForVis($square);
                        $this->printMatrix();
                        $this->resetDrawing();
                        $this->printMatrix2($square);
//                        var_dump($this->poly);
                        return $size;
                    }
                }
            }
        }


        return "NOT FOUND!!!\r\n";
    }


    function run()
    {
        // Main Execution
        $filePath = "data/25-09-1.example"; $this->isMatrixPrint = true; $this->isDebug = true;
        $filePath = "data/25-09-1.inp"; $this->isMatrixPrint = false; $this->isDebug = false;
//        $filePath = "data/25-09-2.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
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

