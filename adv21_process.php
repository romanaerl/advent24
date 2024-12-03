<?php
class def
{
    function run()
    {
        $f = file("data/adv21.inp");
        $count = 0;

        foreach ($f as $line) {
            $arr = explode(' ', $line);
            foreach ($arr as $key => $value) {
                $arr[$key] = (int)$value;
            }
            if (self::isSafe($arr)) {
                $count++;
            } else {
                $t = [];
                foreach ($arr as $key => $value) {
                    $tmpArr = $arr;
                    unset($tmpArr[$key]);

                    $t[] = $tmpArr;
                }
                rslog($t, '$t');
                foreach ($t as $one) {
                    if (self::isSafe($one)) {
                        rslog("AFTERCHECKWORKED", '"AFTERCHECKWORKED"');
                        $count++;
                        break;
                    }
                }
            }
        }

        rslog($count);
    }

    protected static function isSafe($arr)
    {
        $curState = null;
        $errCnt = 0;
        $curDirection = null;
        $direction = $prevVal = null;
        rslog($arr, '$arr');
        $symb = 0;
        foreach ($arr as $key => $val) {
            rslog($key, '$key');
            if ($symb == 0) {
                rslog("Zero, continue", '"Zero, continue"');
                $prevVal = $val;
                $symb++;
                continue;
            }
            rslog($val, '$val');
            rslog($prevVal, '$prevVal');
            if ($val == $prevVal) {
                rslog("WRONG SAME", '"WRONG SAME"');
                return false;
            }
            if ($symb == 1) {
                if ($prevVal < $val) {
                    $direction = 1;
                } else {
                    $direction = 0;
                }
            }
            rslog($direction, '$direction');
            if ($symb > 1) {
                $curDirection = $prevVal < $val ? 1 : 0;
                rslog($curDirection, '$curDirection');
                if ($curDirection !== $direction) {
                    rslog("WRONG DIR");
                    return false;
                }
            }
            $diff = abs($prevVal - $val);
            rslog($diff, '$diff');
            if ($diff > 3) {
                rslog("WRONG BIGDIFF", '"WRONG BIGDIFF"');
                return false;
            }
            $prevVal = $val;
            $symb++;
        }

        rslog("ITZOK", '"ITZOK"');
        return true;
    }
}