<?php
class Def
{
    public $numbers = [];
    public $inv_summ = 0;

    private $digits = [];
    private $digits_lenght = 12;

    function candidateNumber($digitPos, $lineLen, $linePos, $cand)
    {
        $cand = (int)$cand;

        $processed = false;
        // check pos allows to continue
        if ($digitPos < $lineLen - $linePos) {
            if ($cand > $this->digits[$digitPos]) {
                $this->digits[$digitPos] = $cand;
                for ($i = $digitPos - 1; $i >= 0; $i--) {
                    $this->digits[$i] = 0;
                }
                $processed = true;
            }
        }
        if (!$processed && $digitPos) {
            $this->candidateNumber($digitPos - 1, $lineLen, $linePos, $cand);
        }

    }

    function readInput($filePath)
    {

        $sum = 0;
        $lines = file($filePath);
        foreach ($lines as $line) {
            for ($i = $this->digits_lenght; $i >= 0; $i--) {
                $this->digits[$i] = 0;
            }
            $line = trim($line);
            $lineLen = strlen($line);
            for ($linePos = 0; $linePos < $lineLen; $linePos++) {
                $this->candidateNumber(
                    $this->digits_lenght - 1,
                    $lineLen,
                    $linePos,
                    $line[$linePos]
                );

            }

            $newCand = 0;
            for ($num = $this->digits_lenght-1; $num >= 0; $num--) {
                $newCand += (10**$num*$this->digits[$num]);
            }
            $sum+=$newCand;
            var_dump($line." ====>  " . $newCand);
        }

        $this->inv_summ = $sum;
    }

    function code() {
        return $this->inv_summ;
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-03-1.example";
        $filePath = "data/25-03-1.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


$Def = new Def();
$Def->run();

