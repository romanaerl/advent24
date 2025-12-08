<?php
class Def
{
    private $numbers = [];
    private $ranges = [];

    function readInput($filePath)
    {

        $lines = file($filePath);

        foreach ($lines as $line) {
            if (!strlen(trim($line))) {
                continue;
            }
            $s = explode('-', trim($line));
            if (empty($s[1])) {
                $this->numbers[] = (int)$s[0];
            } else {
                $this->ranges[] = [(int)$s[0], (int)$s[1]];
            }
        }


    }


    function code() {
        $sum = 0;

        foreach ($this->numbers as $one) {
            foreach ($this->ranges as $range) {
                if ($one >= $range[0] && $one <= $range[1]) {
                    var_dump($one);
                    $sum++;
                    break;
                }
            }
        }

        return $sum;
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-05-1.example";
//        $filePath = "data/25-05-1.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


$Def = new Def();
$Def->run();

