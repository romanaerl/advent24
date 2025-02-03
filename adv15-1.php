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

    protected $curX = 0;
    protected $curY = 0;

    function code()
    {
        for ($i = 0; $i <5; $i++) rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
        $this->readArray("data/15-1.inp");
//        $this->readArray("data/15-1.example");
//        $this->readArray("data/15-1.example2");
        $this->printArray("INITIAL");

        $this->processMovs();

        rslog($this->calcSum(), '$this->calcSum()');

        $this->printArray("RESULT");
    }

    function calcSum()
    {
        $sum = 0;
        foreach ($this->books as $y => $line) {
            foreach ($line as $x => $val) {
                rslog("[$x,$y]");
                $sum += (100 * ($y)) + ($x);
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
            return 'O';
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
        list($xx, $yy) = $this->getCoordsCorrected($mov, $x, $y);
        $space = $this->getBlock($xx,$yy);
        if ($space == ".") {
            return [$xx, $yy, true];
        }
        if ($space == 'O') {
            list($xxx, $yyy, $movResult) = $this->move($mov, $xx, $yy);
            if ($movResult) {
                unset($this->books[$yy][$xx]);
                $this->books[$yyy][$xxx] = 1;
                return [$xx, $yy, true];
            } else {
                return [$x, $y, false];
            }
        }
        return [$x, $y, false];
    }

    function processMovs()
    {
        foreach ($this->movements as $mov) {
            list($newX, $newY, $result) = $this->move($mov, $this->curX, $this->curY);
            if ($result) {
                $this->curX = $newX;
                $this->curY = $newY;
            }
        }
    }





    function printArray($comment = "Matrix:")
    {
        rslog($this->curY, '$this->curY');
        rslog($this->curX, '$this->curX');
        rslog();
        rslog($comment, 'visMat');
        for($y = 0; $y<$this->sizeY; $y++) {
            $line = "";
            for($x = 0; $x<$this->sizeX; $x++) {
                $char = "";
                if (!empty($this->fences[$y][$x])) {
                    $char = '#';
                } elseif (!empty($this->books[$y][$x])) {
                    $char = 'O';
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


        foreach ($matches[1] as $y => $line) {
            $line = str_split(trim($line));
            if (empty($line)) break;
            if ($x > $this->sizeX) $this->sizeX = $x;
            if ($y > $this->sizeY) $this->sizeY = $y;
            foreach ($line as $x => $v) {
                switch ($v) {
                    case "#":
                        $this->fences[$y][$x] = 1;
                        break;

                    case "O":
                        $this->books[$y][$x] = 1;
                        break;

                    case "@":
                        $this->curX = $x;
                        $this->curY = $y;
                        break;

                    case ".":
                        $this->visualMat[$y][$x] = ".";
                        break;
                }
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