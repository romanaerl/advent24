<?php
class Def
{
    public $numbers = [];
    public $inv_summ = 0;

    function readInput($filePath)
    {
        $this->inv_summ = 0;
        $line = file_get_contents($filePath);
        $groups = explode(",", $line);
        foreach ($groups as $one) {
            list($start, $end) = explode("-", $one);
            if ($start[0] == '0' || $end == '0') {
                continue;
            }
            for ($i = (int)$start; $i <= (int)$end; $i++) {
                if ($this->invalidNum($i)) {
                    $this->numbers[] = $i;
                    $this->inv_summ += $i;
                }
            }

        }
    }

    function invalidNum($int) {
        $str = (string) $int;
        $len = strlen($str);


        for ($i = 1; $i <= $len/2 ; $i++) {
            $sub = substr($str, 0, $i);
            $subLen = strlen($sub);
            if ($len % $subLen == 0) {
                $mult = $len / $subLen;
                if ($str === str_repeat($sub, $mult)) {
                    return true;
                }
            }
        }

        return false;
    }


    function code() {
        var_dump($this->numbers);
        var_dump("\r\n");
        return $this->inv_summ;
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-02-1.example";
        $filePath = "data/25-02-1.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


$Def = new Def();
$Def->run();

