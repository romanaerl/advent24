<?php
class def {
    function run()
    {
        $muls = [];
        $summ = 0;
        $wedo = 1;
        $f = file_get_contents("data/adv31.inp");
        preg_match_all("#don't\(\)|do\(\)|mul\((\d+,\d+)\)#mis", $f, $muls);
        foreach ($muls[0] as $key => $value) {
            switch ($value) {
                case "do()":
                    $wedo = 1;
                    rslog($key, "WEDO");
                    break;
                case "don't()":
                    rslog($key, "WEDONT");
                    $wedo = 0;
                    break;

                default:
                    if (!$wedo) {
                        continue;
                    } else {
                        $ab = explode(',', $muls[1][$key]);
                        $res = (int)$ab[0] * (int)$ab[1];
                        $summ += $res;
                    }
            }
        }
        rslog($muls, '$muls');
        rslog($summ, '$summ');
    }
}
