<?php
class def
{
    function run()
    {
        $f = file("data/42.example");
        $f = file("data/adv41.inp");

        $mat = $this->makeMatrix($f);
        $size = count($mat);
        if ($size != count($mat[0] ??[0])) {
            die("Incorrect matrix!");
        }

        $this->printM($mat);

        $count = 0;
        for ($x=1; $x < $size-1; $x++) {
            for ($y=1; $y < $size-1; $y++) {
                if ($mat[$x][$y] == "A") {
                    $count+=$this->checkPosition($x,$y, $mat);
                }
            }
        }

        rslog($count, '$count');
    }

    function checkDiag($x,$y,$mat, $directionChange = 0)
    {
        if (!$directionChange) {
            $s1 = $mat[$x-1][$y-1];
            $s2 = $mat[$x+1][$y+1];
        } else {
            $s1 = $mat[$x-1][$y+1];
            $s2 = $mat[$x+1][$y-1];
        }

        return in_array($s1.$s2, ["SM", "MS"]);

    }

    function checkPosition($x,$y,$mat)
    {
        $checkDiag1 = $this->checkDiag($x,$y,$mat);
        $checkDiag2 = $this->checkDiag($x,$y,$mat,true);

        if ($checkDiag1 && $checkDiag2) return 1;

        return 0;
    }

    function printM($mat)
    {
        rslog("PRINT THE MATRIX");
        foreach ($mat as $one) {
            if (is_array($one)) {
                $one = join($one);
            }
            echo "$one\r\n";
        }
    }

    function makeMatrix($f) {
        $matrix = [];
        foreach ($f as $one) {
            $matrix[] = str_split(trim($one));
        }

        return $matrix;
    }
}