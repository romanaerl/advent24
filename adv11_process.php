<?php

class def
{
    function run()
    {
        $row1 = $row2 = $diff = [];
        $totalDiff = 0;
        $f = file("data/adcode1-1_data.txt");
        foreach ($f as $v) {
//            rslog("strung" . $v);
            [$firstRowLine, $secondRowLine] = explode('   ', $v);
            $row1[] = (int)trim($firstRowLine);
            $row2[] = (int)trim($secondRowLine);
        }

        rslog($row1, 'FIRST');
        rslog($row2, 'SECOND');

        sort($row1);
        sort($row2);

        foreach ($row1 as $key => $val1) {
            rslog($row1[$key], '$row1[$key]');
            rslog($row2[$key], '$row2[$key]');
            rslog(abs($row1[$key] - $row2[$key]), 'diff');
            $totalDiff += abs($row1[$key] - $row2[$key]);
        }

        rslog($totalDiff);

        $totalSimilarity = 0;

        foreach ($row1 as $val1) {
            rslog($val1, '$val1');
            $appearances = self::countAppearances($val1, $row2);
            rslog($appearances, '$appearances');
            $totalSimilarity += $val1 * $appearances;
        }


        rslog($totalSimilarity, '$totalSimilarity');


    }

    protected static function countAppearances($value, $array)
    {
        $count = 0;
        foreach ($array as $value2) {
            if ($value2 == $value) {
                $count++;
            }
        }

        return $count;
    }
}