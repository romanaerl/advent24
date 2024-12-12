<?php
class def
{
    protected $mat = [];
    protected $regions = [];
    protected $sizeX;
    protected $sizeY;

    /**
     * array(
     *  'startY' => $y
     *  'startX' => $x
     *  'spaces' = [
     *      [Y, X]
     *      [Y, X]
     *  ]
     *  'spacesCnt' = Z
     *  'fencesCnt' = Z
     * )
     */

    function code()
    {
        for ($i = 0; $i <5; $i++)
            rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
        $this->readArray("data/12-1.inp");
//        $this->readArray("data/12-1.example");
//        $this->readArray("data/12-1-1.example");
        $this->printArray($this->mat, "INITIAL");


        $this->walkRegions();

        $sum = 0;
        foreach ($this->regions as $key => &$regs) {
            foreach ($regs as &$reg) {
                $reg['horFencesCnt'] = $this->countHorizontalFences($reg['fences'], ["u", 'd']);
            }
        }
//        rslog($this->regions["Z"], '$this->regions["Z"] <==============');

        foreach ($this->regions as $key => &$regs) {
            foreach ($regs as &$reg) {
                $reg['fences'] = $this->turnFencesMap($reg['fences']);
                $reg['verFencesCnt'] = $this->countHorizontalFences($reg['fences'], ['r', 'l']);
                    $sum += ($reg['verFencesCnt'] + $reg['horFencesCnt']) * $reg['spacesCnt'];
//                break 2;
            }
        }


        rslog();
        rslog();
//        rslog($this->regions["Z"], '$this->regions["Z"]');
        
        
        rslog($sum, '$sum');
        
        
    }

    function turnFencesMap($fences)
    {
        $nfences = [];
        foreach ($fences as $y => $line) {
            foreach ($line as $x => $val) {
                $yy = $this->sizeX-1 - $x;
                $xx = $y;
                $nfences[$yy][$xx] = $val;
            }
        }

        return $nfences;
    }

    function countHorizontalFences(&$fences, $fenceTypes)
    {
        $minY = min(array_keys($fences));
        $maxY = max(array_keys($fences));

        $fencesCnt = 0;
        $lastFences = [];
        for ($y = $minY;  $y<=$maxY; $y++) {
            $minX = min(array_keys($fences[$y]));
            $maxX = max(array_keys($fences[$y]));
            $lastFences = [];
            for ($x=$minX; $x<=$maxX; $x++) {
                if (!isset($fences[$y][$x])) {
                    $curFences = [];
                } else {
                    $curFences = $fences[$y][$x];
                }
                foreach ($fenceTypes as $type) {
                    if (in_array($type, $curFences)) {
                        if (!in_array($type, $lastFences)) {
                            $fencesCnt++;
                        }
                    }
                }
                $lastFences = $curFences;
            }
        }
        return $fencesCnt;
    }


    function alreadyCounted($val, $y, $x)
    {
        if (!isset($this->regions[$val])) {
            return false;
        }
        $space = "$y-$x";
        foreach ($this->regions[$val] as $one) {
            if (in_array($space, $one['spaces'])) {
                return true;
            }
        }
        return false;
    }

    function getSquareAroundCoords($y, $x)
    {
        $coords = [
            ['u', $y-1, $x],
            ['r', $y, $x+1],
            ['d', $y+1, $x],
            ['l', $y, $x-1],
        ];

        return $coords;
    }

    function expandAndCount($val, $y, $x, &$region)
    {
        $region['spacesCnt']++;
        $region['spaces'][] = "$y-$x";

        rslog($val, '$val STARTED <=================');
        rslog("$y -- $x", '$val STARTED <=================');

        $fences = [];
        foreach ($this->getSquareAroundCoords($y, $x) as $coords) {
            list($dir, $yy, $xx) = $coords;
            rslog("$yy-$xx", '"$yy-$xx"');
            if (in_array("$yy-$xx", $region['spaces'])) {
                rslog("INARRAY", '"INARRAY"');
                continue;
            } elseif ($yy<0 || $xx<0 || $yy>=$this->sizeY || $xx>=$this->sizeX) {
                rslog("OUT", '"OUT"');
                $region['fencesCnt']++;
                $fences[] = $dir;
                rslog("FENCES++", '"FENCES++"');
            } else {
                $val2 = $this->mat[$yy][$xx];
                rslog($val2, '$val2');
                if ($val2 == $val) {
                    rslog("CONTINUE", '"CONTINUE"');
                    $this->expandAndCount($val, $yy, $xx, $region);
                } else {
                    rslog("ANOTHER");
                    rslog("FENCES++", '"FENCES+++"');
                    $fences[] = $dir;
                    $region['fencesCnt']++;
                }
            }
        }
        $region['fences'][$y][$x] = $fences;
        rslog("------ ENDLOOP", '"------ ENDLOOP"');
    }

    function walkRegions() {
//        $curVal = '+';
        $regionId = 0;
        foreach ($this->mat as $y => $line) {
                foreach ($line as $x => $val) {
//                if ($val != $curVal) {
                    // another Region
                    // check we had it before
                    if ($this->alreadyCounted($val, $y, $x)) {
                        continue;
                    }

                    // expand and count fences and space
                    $this->regions[$val][$regionId] = [
                        'spaces' => [],
                        'startY' => $y,
                        'startX' => $x,
                        'fencesCnt' => 0,
                        'spacesCnt' => 0,
                    ];
                    $this->expandAndCount($val, $y, $x, $this->regions[$val][$regionId]);
                    rslog($this->regions[$val][$regionId], '$this->regions[$val][$regionId]');
//                    die();
                    $regionId++;
                    $curVal = $val;
                }
//            }
        }

        $sum = 0;
        foreach ($this->regions as $val => $regs)
        {
            foreach ($regs as $reg) {
                $sum += $reg['fencesCnt'] * $reg['spacesCnt'];
            }
        }

        rslog($sum, '$sum');
        rslog();

//        rslog($this->regions["Z"]);

    }


    function printArray($mat, $comment = "Matrix:")
    {
        rslog($comment, 'MAT');
        foreach ($this->mat as $y => $line) {
            $linestr = "";
            foreach ($line as $x => $val) {
                $linestr .= $val;
            }
            echo "$linestr" . "\n";
        }
        rslog("");
    }

    function readArray($filename)
    {
        $lines = file($filename);
        $y = $x = 0;
        foreach ($lines as $y => $line) {
            $line = str_split(trim($line));
            foreach ($line as $x => $v) {
                $this->mat[$y][$x] = $v;
            }
        }
        $this->sizeY = count($this->mat);
        $this->sizeX = count($this->mat[0]);
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}