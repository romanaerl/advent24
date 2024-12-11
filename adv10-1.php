<?php
class def
{
    protected $mat = [];
    protected $heads = [];

    function code()
    {
        ini_set('memory_limit', '1024M');
        $this->readArray("data/10-1.inp");
//        $this->readArray("data/10-1.example");
        $this->printArray($this->mat);
//        rslog($this->heads, '$this->heads');


        $sum = 0;
        foreach ($this->heads as $head) {
            rslog($head, '$head HEADSTARTED <==================');

            // Getting all Ends
            $ends = $this->sumSquare($head[0], $head[1], 0);

//            rslog($ends, '$ends ALL ENDS FOUND!');

            // Getting unique ends
//            $finalEnds = [];
//            foreach ($ends as $one_end) {
//                $hash = $one_end[0] . '_' . $one_end[1];
//                rslog($hash, '$hash');
//                if (!isset($finalEnds[$hash])) {
//                    rslog($hash, '$hash FINAL');
//                    $finalEnds[$hash] = 1;
//                }
//            }

//            rslog(count($finalEnds), 'count($finalEnds)');
            $sum += count($ends);
//            die();
        }

        rslog($sum, '$sum TOTAL SUM');
    }

    function getPossibleStepsCoords($y, $x)
    {
        $steps = [];

        $directions = [
            [-1, 0],
            [+1, 0],
            [0, -1],
            [0, +1],
        ];
        $sizeY = count($this->mat);
        $sizeX = count($this->mat[0]);

        foreach ($directions as $dir) {
            $yy = $y + $dir[0];
            $xx = $x + $dir[1];
            if ($xx >= 0 && $xx < $sizeX && $yy >= 0 && $yy < $sizeY) {
                $steps[] = [$yy, $xx];
            }
        }
        return $steps;
    }

    function sumSquare($y, $x, $height)
    {
        $ends = [];
//        rslog($this->getPossibleStepsCoords($y, $x), '$this->getPossibleStepsCoords($y, $x)');
//        die();
        foreach ($this->getPossibleStepsCoords($y, $x) as $nextStep) {
            $val = $this->mat[$nextStep[0]][$nextStep[1]];
            rslog($val, '$val HEIGHT' . $height . "  [{$nextStep[0]}, {$nextStep[1]}]");
            if ($val == $height + 1) {
                if ($val == 9) {
                    rslog("END FOUND ===>  [{$nextStep[0]}, {$nextStep[1]}]");
                    $ends[] = [$nextStep[0], $nextStep[1]];
                } else {
                    rslog("Step Found", '"Step Found"');
                    $ends = array_merge($ends, $this->sumSquare($nextStep[0],$nextStep[1], $height + 1));
                }
            }
        }

        return $ends;
    }


    function printArray($mat)
    {
        rslog("Matrix:");
        foreach ($mat as $y => $one) {
            $line = "";
            foreach ($one as $x => $val) {
                $line .= $val;
            }
            echo $line . "\n";
        }
    }

    function readArray($filename)
    {
        $lines = file($filename);
        foreach ($lines as $y => $line) {
            $line = str_split(trim($line));
            foreach ($line as $x => $val) {
                $this->mat[$y][$x] = (int)$val;
                if ((int)$val == 0) {
                    $this->heads[] = [$y, $x];
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