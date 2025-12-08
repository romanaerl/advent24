<?php
class Def
{
    private $columns = [];
    private $signs = [];

    function readInput($filePath)
    {

        ini_set("memory_limit", '5120M');
        $lines = file($filePath);

        $maxLen = 0;
        foreach ($lines as $line) {
            $new = strlen($line);
            if ($new > $maxLen) $maxLen = $new;
        }

        $last_sign = '';
        $sum = 0;
        $buffers = [];
        for ($pointer = 0; $pointer <= $maxLen; $pointer++) {
            $allSpaces = true;
            foreach ($lines as $lineKey => $line) {
                $symb = $line[$pointer] ?? ' ';
                if (!isset($buffers[$lineKey])) $buffers[$lineKey] = '';
                if (!in_array($symb, ['+', '*'])) {
                    $buffers[$lineKey] .= $symb;
                } else {
                    $last_sign = $symb;
                }
                if ($symb !== ' ') $allSpaces = false;
            }
            if ($allSpaces) {
                $sum += $this->calcBuffers($buffers, $last_sign);
                $buffers = [];
                $last_sign = '';
            }
        }

        var_dump($sum);

        return $sum;
    }

    function calcBuffers($buffers, $last_sign)
    {
        var_dump("======================");
        $toSum = $last_sign == '+';
        $res = 1;
        var_dump($toSum);
        foreach ($buffers as $line) {
            if (!strlen(trim($line))) continue;
            $new = (int)trim($line);
            echo "$new\r\n";
            if ($toSum) {
                $res += $new;
            } else {
                $res *= $new;
            }
        }

        if ($toSum) $res--;

        var_dump($res);

        return $res;
    }


    function code() {
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-06-1.example";
        $filePath = "data/25-06-1.inp";
//        $filePath = "data/25-06-2.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


$Def = new Def();
$Def->run();

