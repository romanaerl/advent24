<?php
class Def
{
    public $numbers = [];
    public $directions = [];
    public $zeros_cnt = 0;


    public const DIAL_SIZE = 100;
    public const START_DIAL = 50;


    function readInput($filePath)
    {
        $lines = file($filePath);
        $i = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $this->directions[$i] = $line[0];
            $this->numbers[$i] = (int) substr($line, 1);
            $i++;
        }
    }

    function getNumber($i) {
        $number = (int)$this->numbers[$i];
        if ($number >= self::DIAL_SIZE) {
            // uncomment when passing zero counts
//            $this->zeros_cnt += floor($number / self::DIAL_SIZE);
//            $number = $number % self::DIAL_SIZE;
        }
        return ($this->directions[$i] == 'L' ? -1 : 1) * $number;
    }

    function code() {
        $size = count($this->numbers);
        $number = self::START_DIAL;
        $this->zeros_cnt = 0;


        for ($i = 0;$i<$size;$i++) {

            $init_number = $number;

            $init_newNum = $newNum = $this->getNumber($i);

            echo "init_number: $init_number \r\n";
            echo "init_newNum: $init_newNum \r\n";


            $rounds = floor(abs($newNum)/self::DIAL_SIZE);
            if ($rounds) {
                if ($newNum > 0) {
                    $newNum -= self::DIAL_SIZE * $rounds;
                } else {
                    $newNum += self::DIAL_SIZE * $rounds;
                }
            }


            $number += $newNum;


            $zeros = 0;

            if ($number < 0) {
                $zeros = 1;
                $number += self::DIAL_SIZE;
            } else if ($number >= self::DIAL_SIZE) {
                $zeros = 1;
                $number -= self::DIAL_SIZE;
            }

            $this->zeros_cnt += ($zeros);
            if ($rounds) {
                $this->zeros_cnt += $rounds;
            }

//            if ($init_number == 0 && $rounds) {
//                $this->zeros_cnt--;
//            }


            if ($init_number == 0 && $zeros) {
                echo "DISCOUNT\r\n";
                $this->zeros_cnt--;
            }

            echo "rounds: $rounds \r\n";
            echo "zeros: $zeros \r\n";

            if ($number == 0 && !$zeros) {
                echo "ADDITIONAL EXACT ZERO \r\n";
                $this->zeros_cnt++;
            }
            echo "total zero: $this->zeros_cnt \r\n";

            echo "RESULT number: $number \r\n";

            var_dump($newNum . " -------- " . $number);
            var_dump("--------------------------------------------------");
            echo " \r\n";

//            if ($init_number <= 0 && $number >= 0) {
//                $this->zeros_cnt ++;
//            }
//
//            if ($init_number >= 0 && $number <= 0) {
//                $this->zeros_cnt ++;
//            }

        }
        return $this->zeros_cnt;
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-01-1.example";
        $filePath = "data/25-01-1.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


$Def = new Def();
$Def->run();

