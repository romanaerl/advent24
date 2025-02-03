<?php
class def
{
    protected $sizeX = 0;
    protected $sizeY = 0;
    protected $robots = [];
    protected $robotsInitial = [];
    protected $visMat = [];

    function code()
    {
        for ($i = 0; $i <5; $i++)
            rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
        $this->readArray("data/14-1.inp");
//        $this->readArray("data/14-1.example");
//        $this->readArray("data/14-1-1.example");
        $this->printArray("INITIAL");

        for ($i = 1;$i<100000;$i++) {
            $moveSeconds = 1;
            foreach ($this->robots as &$robot) {
//                    rslog($robot, '$robot');
                $robX = $robot[0];
                $robY = $robot[1];
                $coordsX = $robot[2] * $moveSeconds + $robX;
//                    rslog($coordsX, '$coordsX');
                $coordsY = $robot[3] * $moveSeconds + $robY;
//                    rslog($coordsY, '$coordsY');

                $overlapX = (int)floor(abs($coordsX) / $this->sizeX) * $this->sizeX;
                $overlapY = (int)floor(abs($coordsY) / $this->sizeY) * $this->sizeY;
//            rslog($overlapX, '$overlapX');
//            rslog($overlapY, '$overlapY');

                $coordsX = $coordsX > 0 ? $coordsX - $overlapX : $coordsX + $overlapX;
                $coordsY = $coordsY > 0 ? $coordsY - $overlapY : $coordsY + $overlapY;
//                    rslog($coordsX, '$coordsX');
//                    rslog($coordsY, '$coordsY');
                $robot[0] = $coordsX>=0 ? $coordsX : $this->sizeX + $coordsX;
                $robot[1] = $coordsY>=0 ? $coordsY : $this->sizeY + $coordsY;
//                    rslog($robot, '$robot');
            }
            if ($this->christmasDetected("TTTTT", 10)) {
                rslog($i, '$i <<<<=====================');
                $this->printArray("RESULT");
//                die();
            }
            if ($i > 100000) die();
        }



//        $this->calcSumQuadrants();

    }

    function christmasDetected($search, $xxx)
    {


        $this->visMat = [];
        for($y = 0; $y < $this->sizeY; $y++) {
            for ($x = 0; $x < $this->sizeX; $x++) {
                $this->visMat[$y][$x] = " ";
            }
        }

        foreach ($this->robots as $robot) {
            $this->visMat[$robot[1]][$robot[0]] = "T";
        }

        $lineLong = "";
        foreach ($this->visMat as $y => $line) {
            $lineLong .= join("", $line);
        }
        $matches = [];
        preg_match_all("|($search)|mis", $lineLong, $matches);

        $count = count($matches[0]);

        if ($count >= $xxx) {
            return true;
        }

        return false;
    }

    function calcSumQuadrants() {
        $quadrants = [0,0,0,0];
        $treshX = (int)floor($this->sizeX / 2);
        $treshY = (int)floor($this->sizeY / 2);

//        rslog($treshX, '$treshX');
//        rslog($treshY, '$treshY');

        foreach ($this->robots as $key => $robot) {
            $coordX = $robot[0];
            $coordY = $robot[1];
            if ($coordX == $treshX || $coordY == $treshY) {
                continue;
            }
            if ($coordX < $treshX && $coordY < $treshY) {
                $qIdx = 0;
            } elseif (($coordX > $treshX && $coordY < $treshY)) {
                $qIdx = 1;
            } elseif (($coordX < $treshX && $coordY > $treshY)) {
                $qIdx = 2;
            } elseif (($coordX > $treshX && $coordY > $treshY)) {
                $qIdx = 3;
            }
            $quadrants[$qIdx] += 1;
        }

//        rslog($quadrants, '$quadrants');
//
        rslog($quadrants[0]*$quadrants[1]*$quadrants[2]*$quadrants[3],"FINAL SUMM");
    }



    function printArray($comment = "Matrix:")
    {
        $this->visMat = [];
        rslog($comment, '$comment');
        for($y = 0; $y < $this->sizeY; $y++) {
            for ($x = 0; $x < $this->sizeX; $x++) {
                $this->visMat[$y][$x] = " ";
            }
        }

        foreach ($this->robots as $robot) {
            $this->visMat[$robot[1]][$robot[0]] = "+";
        }

        foreach ($this->visMat as $row) {
            $line = '';
            foreach ($row as $symb) {
                $line .= "$symb";
            }
            echo $line . "\n";
        }
    }

    function readArray($filename)
    {
        $lines = file($filename);
        $this->sizeX = (int)trim($lines[0]);
        $this->sizeY = (int)trim($lines[1]);



        $lines = file_get_contents($filename);
        $matches = [];
        preg_match_all("|p=([-\d]+),([-\d]+)\sv=([-\d]+),([-\d]+)|mis", $lines, $matches);
        foreach ($matches[1] as $key => $val) {
            $robot = [
                $matches[1][$key],
                $matches[2][$key],
                $matches[3][$key],
                $matches[4][$key],
            ];
            $this->robotsInitial[] = $robot;
        }

        $this->robots = $this->robotsInitial;
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}