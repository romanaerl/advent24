<?php
class def
{
    protected $mat = [];

    function code()
    {
        for ($i = 0; $i <5; $i++)
            rslog("============================BEGIN========================");
        ini_set('memory_limit', '2048M');
//        $this->readArray("data/13-1.inp");
        $this->readArray("data/13-1.example");
//        $this->readArray("data/13-1-1.example");
        $this->printArray($this->mat, "INITIAL");


        $sum = 0;
        foreach ($this->mat as $mat) {
            $one = $this->getCheapestCombination($mat);
            rslog($one, '$one');
            $sum += $one;
        }
        rslog($sum, '$comb');
    }

    function getGcd($a, $b) {
        // Алгоритм Евклида для нахождения НОД
        while ($b != 0) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }
        return $a;
    }

    function getCheapestCombination($mat)
    {
        $searchX = $mat['p'][0];
        $searchY = $mat['p'][1];
        $AX = $mat['a'][0];
        $AY = $mat['a'][1];
        $BX = $mat['b'][0];
        $BY = $mat['b'][1];

        $gcdX = $this->getGcd($AX, $BX);
        if ($searchX % $gcdX !== 0) {
            return false;
        } else {
            $searchX = intdiv($searchX, $gcdX);
            $AX = intdiv($AX, $gcdX);
            $BX = intdiv($BX, $gcdX);
        }

        $gcdY = $this->getGcd($AY, $BY);
        if ($searchY % $gcdY !== 0) {
            return false;
        } else {
            $searchY = intdiv($searchY, $gcdY);
            $AY = intdiv($AY, $gcdY);
            $BY = intdiv($BY, $gcdY);
        }

        $maxAmountAX = ceil($searchX / $AX);
        $maxAmountAY = ceil($searchY / $AY);
        $maxAmountBX = ceil($searchX / $BX);
        $maxAmountBY = ceil($searchY / $BY);
        $maxAmountA = min($maxAmountAX, $maxAmountAY);
        $maxAmountB = min($maxAmountBX, $maxAmountBY);

        for ($amountB = $maxAmountB; $amountB >=0; $amountB--) {
            $curBX = $amountB * $BX;
            $remainX = $searchX - $curBX;
            if (!$remainX || ($remainX % $AX) == 0) {
                $amountA = intdiv($remainX, $AX);
                rslog("CHECKING Y", '"CHECKING Y"');
                // Checking if Y satisfies too
                if ($searchY == ($amountB * $BY) + ($amountA * $AY)) {
                    // We found a solution
                    rslog("FOUND", '"FOUND"');
                    return $amountA*3+$amountB;
                }
            }
            if ($amountB % 1000000 == 0) {
                rslog($amountB);
            }
        }
        return false;
    }

    function printArray($mat, $comment = "Matrix:")
    {
        rslog($comment, 'MAT');
        foreach ($this->mat as $one) {
            $string = "a [{$one['a'][0]},{$one['a'][1]}]  b [{$one['b'][0]},{$one['b'][1]}]  PRIZE [{$one['p'][0]},{$one['p'][1]}]";
            rslog($string, '$string');
        }
    }

    function readArray($filename)
    {
        $lines = file_get_contents($filename);
        $buttonsA = $buttonsB = $prizes = [];
        preg_match_all('|\s?A: X\+(\d+), Y\+(\d+)|mis', $lines, $buttonsA);
        preg_match_all('|\s?B: X\+(\d+), Y\+(\d+)|mis', $lines, $buttonsB);
        preg_match_all('|Prize: X=(\d+), Y=(\d+)|mis', $lines, $prizes);
        foreach ($prizes[1] as $key => $value) {
            $this->mat[] = [
                'a' => [$buttonsA[1][$key], $buttonsA[2][$key]],
                'b' => [$buttonsB[1][$key], $buttonsB[2][$key]],
                'p' => [$prizes[1][$key] + 10000000000000, $prizes[2][$key] + 10000000000000]
            ];
        }
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}