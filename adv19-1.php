<?php
class Def
{
    protected $i = 0;
    protected $cache = [];

    function readInput($filePath)
    {
        $lines = file($filePath);
        $patterns = explode(',', array_shift($lines));
        array_walk($patterns, function (&$val) { $val=trim($val); });
        array_shift($lines);
        $designs = $lines;
        array_walk($designs, function (&$val) { $val=trim($val); return true;});
        rslog($designs, '$designs');
        return [$patterns, array_filter($designs)];
    }

    function isDesignPossible($design, $patterns)
    {
        $finalCandidates = [];
        $curCandidates = [];
        $designArr = str_split($design);

        for ($i = 0; $i<count($designArr); $i++) {
            $curSymb = $designArr[$i];
            $curSymbHasAnyCandidates = false;
//            rslog($i, '$i processing........' . $curSymb);

            // Check old current candidates
            foreach ($curCandidates as $key => $cand) {
                list($candArr, $candStart) = $cand;
                $candI = $i - $candStart;
                if ($candArr[$candI] == $curSymb) {
                    $curSymbHasAnyCandidates = true;
                    if (count($candArr) == $candI+1) {
                        // End of candidate and it is valid
                        $finalCandidates[$candStart][] = [$candStart, count($candArr), $candArr];
//                        rslog(join($candArr), '"moving to final candidate"');
                        unset($curCandidates[$key]);
                    }
                } else {
                    // New symbol does not fit
                    unset($curCandidates[$key]);
                }
            }

            // Check patterns for a good start
            foreach ($patterns as $pattern) {
                $patternArr = str_split($pattern);
//                rslog($pattern[0] == $curSymb, "$pattern[0] == $curSymb");
                if ($patternArr[0] == $curSymb) {
                    // First letter of candidate matches
                    if (count($patternArr) == 1) {
                        $curSymbHasAnyCandidates = true;
                        // Its a one synbol pattern - it is a successfull candidate already
                        $finalCandidates[$i][] = [$i, 1, $patternArr];
//                        rslog(join($patternArr), '"new final candidate"');
                    } else {
//                        rslog($i, '$i');
//                        rslog(count($patternArr), 'count($patternArr)');
                        // its a few letters candidate which is just started - we will add it to candidates
                        if ($i < count($designArr)-1) {
//                            rslog(join($patternArr), '"adding candidate"');
                            $curSymbHasAnyCandidates = true;
                            // there will be more symbols to check in the design
                            $curCandidates[] = [$patternArr, $i];
                        }
                    }
                }
            }
            if (!$curSymbHasAnyCandidates) {
                // This symbol has no candidates coverage - no need to check further
                rslog($design, '$design <===============   FAILED');
                rslog($patterns, '$patterns');
                rslog($finalCandidates, '$finalCandidates');
                rslog($curCandidates, '$curCandidates');
                rslog($i, '$i < failed');
                return 0;
            }
        }


        rslog($design, '$design');
        rslog($finalCandidates, '$finalCandidates');


        $this->cache = [];
        return $this->checkPaths(0, $designArr, $finalCandidates);

//        rslog($design, '$design <============');
//        rslog($patterns, '$patterns');
//        rslog($finalCandidates, '$finalCandidates');
//        die();
    }

    function checkPaths($idx, &$designArr, &$finalCandidates, $i = 0)
    {
        if (isset($this->cache[$idx])) {
            return $this->cache[$idx];
        }
        $sum = 0;
        $i++;
        if (isset($finalCandidates[$idx]))
        foreach ($finalCandidates[$idx] as $cand) {
            //[$candStart, count($candArr), $candArr];
            $candLen = $cand[1];
            if ($candLen + $idx == count($designArr)) {
                // with this candidate we will reach the end of the design
                $sum++;
            } else {
                $sum += $this->checkPaths($idx + $candLen, $designArr, $finalCandidates, $i);
            }
        }
        $this->cache[$idx] = $sum;
        return $sum;
    }


    function run()
    {
        // Main Execution
//        $filePath = "data/19-1.example";
//        $filePath = "data/19-1.example2";
        $filePath = "data/19-1.inp";
        [$patterns, $designs] = $this->readInput($filePath);
        $foundCnt = 0;
        foreach ($designs as $i => $design) {
            $this->i = 0;
            rslog($design, '$design <===================== ' . $i);
            if ($cnt = $this->isDesignPossible($design, $patterns)) {
                $foundCnt += $cnt;
            }
        }
        rslog($foundCnt, '$foundCnt');
    }
}

