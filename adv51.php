<?php
class def
{
    protected $initPriority = [];
    protected $initReports = [];
    protected $iReports = [];
    protected $goodReportsIndexes = [];
    protected $badReportsIndexes = [];


    function run()
    {
//        $this->readArrays("data/51.example");
        $this->readArrays("data/51.inp");
        $this->iReports = $this->indexReports();

        foreach ($this->iReports as $key => $report) {
            if ($this->isGoodIReport($report)) {
                $this->goodReportsIndexes[] = $key;
            } else {
                $this->badReportsIndexes[] = $key;
            }
        }


        $summ = $this->calcMiddleSum($this->goodReportsIndexes);

        rslog($summ, 'RESULT1');


        foreach ($this->initReports as $repIdx => $report) {
            if (in_array($repIdx, $this->badReportsIndexes)) {
                $this->initReports[$repIdx] = $this->fixReport($report);
            } else {
                unset($this->initReports[$repIdx]);
                unset($this->iReports[$repIdx]);
            }
        }

        $summ = $this->calcMiddleSum(array_keys($this->initReports));

        rslog($summ, 'RESULT2');
    }

    function fixReport($report)
    {
        $prior = [];
        foreach ($this->initPriority as $one) {
            if (in_array($one[0], $report) && in_array($one[1], $report)) {
                $prior[] = $one;
            }
        }

        rslog("==================================", '"=================================="');
        rslog($report, '$report');
        rslog($this->isGoodReport($report), '$this->isGoodReport($report)');
        usort(
            $report,
            function ($a, $b) use ($prior) {
                foreach ($prior as $onep) {
                    if ($onep[0] == $a && $onep[1] == $b) return -1;
                    if ($onep[0] == $b && $onep[1] == $a) return 1;
                }
                return 0;
            }
        );
        rslog($report, '$report AFTER SORT');
        rslog($this->isGoodReport($report), '$this->isGoodReport($report)');
        return $report;
    }

    function getMiddle($report)
    {
        $size = count($report);
        return $report[ceil($size/2-1)];
    }

    function calcMiddleSum($indexes)
    {
        $summ = 0;
        foreach ($indexes as $idx) {
            $summ += $this->getMiddle($this->initReports[$idx]);
//            rslog($this->initReports[$idx], '$this->initReports[$idx]');
        }
        return $summ;
    }

    function isGoodReport($report)
    {
        return $this->isGoodIReport($this->makeIReport($report));
    }

    function isGoodIReport($report)
    {
        foreach ($this->initPriority as $prior) {
            list($low, $high) = $prior;

            if (!isset($report[$low]) || !isset($report[$high])) {
                continue;
            }

            if ($report[$low] >= $report[$high] ) {
                return false;
            }
        }

        return true;
    }

    function makeIReport($report)
    {
        $newReport = [];
        foreach ($report as $key => $value) {
            $newReport[$value] = $key;
        }
        return $newReport;
    }

    function indexReports()
    {
        $index = [];
        foreach ($this->initReports as $one) {
            $index[] = $this->makeIReport($one);
        }
        return $index;
    }

    function readArrays($filename)
    {
        $file = file($filename);
        foreach ($file as $one) {
            $vars = explode('|', $one);
            if (count($vars) == 2) {
                $this->initPriority[] = [(int)$vars[0],(int)$vars[1]];
            } else {
                $vars = explode(',', $one);
                if (count($vars) > 1) {
                    $report = [];
                    foreach ($vars as $var) {
                        $report[] = (int)$var;
                    }
                    $this->initReports[] = $report;
                }
            }
        }
    }
}