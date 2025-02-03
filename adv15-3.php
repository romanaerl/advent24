<?php
class def
{
    protected $visualMat = [];
    protected $fences = [];
    protected $books = [];
    protected $sizeX;
    protected $sizeY;
    protected $movements = [];
    protected $booksMovs = [];
    protected $cycle = 0;

    protected $curX = 0;
    protected $curY = 0;

    function code()
    {
        for ($i = 0; $i <5; $i++) rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
        $this->readArray("data/15-1.inp");
//        $this->readArray("data/15-1.example");
//        $this->readArray("data/15-1.example2");
//        $this->readArray("data/15-3.example3");
        $this->printArray("INITIAL");

        $this->processMovs();
//
        echo '$this->calcSum()' . $this->calcSum() . "\n";
//
        $this->printArray("RESULT");
    }

    function calcSum()
    {
        $sum = 0;
        foreach ($this->books as $y => $line) {
            foreach ($line as $x => $val) {
                if ($val==1) {
//                    echo "[$x,$y]";
                    $sum += (100 * ($y)) + ($x);
                }
            }
        }
        return $sum;
    }

    function getDirection($mov)
    {
        // xx, yy
        $dirs = [
            "^" => [0, -1],
            ">" => [1, 0],
            "v" => [0, 1],
            "<" => [-1, 0],
        ];
        return $dirs[$mov];
    }

    function getBlock($x, $y)
    {
        if (!empty($this->fences[$y][$x])) {
            return '#';
        } elseif (!empty($this->books[$y][$x])) {
            return $this->books[$y][$x] == 1 ? '[' : ']';
        } else {
            return ".";
        }
    }

    function getCoordsCorrected($mov, $x, $y)
    {
        $dir = $this->getDirection($mov);
        return [$x + $dir[0], $y + $dir[1]];
    }

    function move($mov, $x, $y)
    {
        rslog("$x-$y", '"$x-$y" MOVE INIT');
        $dir = $this->getDirection($mov);
        list($xx, $yy) = $this->getCoordsCorrected($mov, $x, $y);
        rslog("$xx-$yy", '"$x-$y" MOVE CORRECTED');
        $space = $this->getBlock($xx,$yy);
        $this->cycle++;
        rslog($space, '$space');
        if ($space == ".") {
            return [$xx, $yy, true];
        }
        if ($space == ']') {
            // correcting for the book start
            $moved = $this->move($mov, $x-1, $y);
            rslog($moved, '$moved');
            // returning back the original coordinate
            $moved[0] = $xx;
            return $moved;
        }
        if ($space=='[') {
            if ($dir[1] == 0) {
                // not a Y movement
                $expectEndblock = $dir[0] > 0;
                list($xxx, $yyy, $movResult) = $this->move($mov, $xx + ($expectEndblock ? 1:0), $yy);
                if ($dir[0] > 0) {
                    // compensate for block lenght
                    $xxx--;
                }
            } else {
                // Y movement
                list($xxx, $yyy, $movResult) = $this->move($mov, $xx, $yy);
                list($xxx2, $yyy2, $movResult2) = $this->move($mov, $xx+1, $yy);
                // both parts should move vertically
                $movResult = $movResult && $movResult2;
            }
            if ($movResult) {
                $this->booksMovs[] = [$xx, $yy, $xxx, $yyy];
                return [$xx, $yy, true];
            } else {
                return [$x, $y, false];
            }
        }
        return [$x, $y, false];
    }

    function processMovs()
    {
        foreach ($this->movements as $key=>$mov) {
            $this->booksMovs = [];
            echo ('$mov  DIRECTION ==        ' . $mov . "\n");
            rslog();
            rslog("$this->curX -- $this->curY", '"$this->curX -- $this->curY"');
            list($newX, $newY, $result) = $this->move($mov, $this->curX, $this->curY);
            rslog("$result, $newX, $newY", '"$result, $newX, $newY"');
            if ($result) {
                $this->curX = $newX;
                $this->curY = $newY;
                foreach ($this->booksMovs as $move) {
                    rslog($move, '<-----MOOOOOOVEEEE---->');
                    list($oldX, $oldY, $newX, $newY) = $move;
                    unset($this->books[$oldY][$oldX]);
                    unset($this->books[$oldY][$oldX+1]);
                }
                foreach ($this->booksMovs as $move) {
                    list($oldX, $oldY, $newX, $newY) = $move;
                    $this->books[$newY][$newX] = 1;
                    $this->books[$newY][$newX+1] = 2;
                }
                $this->booksMovs = [];
            }
            if ($key == 65) {
//                break;
            } else {
//                $this->printArray();
            }
        }
    }





    function printArray($comment = "Matrix:")
    {
//        rslog($this->curY, '$this->curY');
//        rslog($this->curX, '$this->curX');
        rslog();
        rslog($comment, 'visMat');
        for($y = 0; $y<$this->sizeY+1; $y++) {
            $line = "";
            for($x = 0; $x<$this->sizeX; $x++) {
                if (!empty($this->fences[$y][$x])) {
                    $char = '#';
                } elseif (!empty($this->books[$y][$x])) {
                    $char = $this->books[$y][$x] ==1 ? '[' : ']';
                } elseif ($x == $this->curX && $y == $this->curY) {
                    $char = '@';
                } else {
                    $char = ".";
                }
                $line .= $char;
            }
            echo $line . "\n";
        }
    }

    function readArray($filename)
    {
        $lines = file_get_contents($filename);

        $matches = [];
        preg_match_all('/([\#\.O\@]+)|([\^v\<\>]+)/mis', $lines, $matches);



        $x = 0;
        foreach ($matches[1] as $y => $line) {
            $line = str_split(trim($line));
            if (empty($line)) break;
            if ($x*2+2 > $this->sizeX) $this->sizeX = $x*2+2;
            if ($y > $this->sizeY) $this->sizeY = $y+1;
            $xAdd = 0;
            foreach ($line as $x => $v) {
                switch ($v) {
                    case "#":
                        $this->fences[$y][$x+$xAdd] = 1;
                        $this->fences[$y][$x+$xAdd+1] = 1;
                        break;

                    case "O":
                        $this->books[$y][$x+$xAdd] = 1;
                        $this->books[$y][$x+$xAdd+1] = 2;
                        break;

                    case "@":
                        $this->curX = $x+$xAdd;
                        $this->curY = $y;
                        break;

                    case ".":
                        $this->visualMat[$y][$x+$xAdd] = ".";
                        break;
                }
                $xAdd++;
            }
        }

        foreach ($matches[2] as $movements) {
            if (!empty($movements)) {
                $movs = str_split(trim($movements));
                foreach ($movs as $mov) {
                    $this->movements[] = $mov;
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