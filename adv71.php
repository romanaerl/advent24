<?php
class def
{
    protected $answers = [];
    protected $numbers = [];
    protected $operators = ["*", "+", '|'];
    protected $operatorsCache = [];

    function code()
    {
        ini_set('memory_limit', '1024M');
//        $this->readArray("data/71.example");
        $this->readArray("data/71.inp");

        $count = 0;
        $result = 0;

//        $this->numbers = [1 => $this->numbers[1]];
//        $this->answers = [1 => $this->answers[1]];
        $size = count($this->numbers);
        foreach ($this->numbers as $key => $numb) {
            rslog("$key / $size");
            $possibleResults = $this->getPossibleResults($numb);
            if (in_array($this->answers[$key], $possibleResults)) {
                $count++;
                $result += $this->answers[$key];
            }
        }

        rslog($count, '$count');
        rslog($result, '$result');
    }

    function performOperation($number, $number2, $operator)
    {
        if ($operator == '+') return $number + $number2;
        if ($operator == '*') return $number * $number2;
        if ($operator == '|') return (int) (string)$number . (string)$number2;

        return 0;
    }

    function getPossibleResults($numbers)
    {
        // Can be different for each size of $numbers
        $operators = $this->getOperators($numbers);
        $results = [];
        foreach ($operators as $operSet) {
            $operSet = str_split($operSet);
            $result = $numbers[0];
            for ($i = 1; $i < count($numbers); $i++) {
                $result = $this->performOperation($result, $numbers[$i], $operSet[$i-1]);
            }
            $results[] = $result;
        }
        return $results;
    }

    function getOperators($arr)
    {
        $size = count($arr);
        $operators_cnt = $size -1;
        if (isset($this->operatorsCache[$operators_cnt])) {
            return $this->operatorsCache[$operators_cnt];
        }
        $variations = [''];

        for ($i = 0; $i < $operators_cnt; $i++) {
            $newVars = [];
            foreach ($variations as $var) {
                foreach ($this->operators as $oper) {
                    $newVars[] = $var . $oper;
                }
            }
            $variations = array_merge($variations, $newVars);
        }

        $variations = array_filter($variations, fn($a) => strlen($a) == $operators_cnt);
        $variations = array_values($variations);

        $this->operatorsCache[$operators_cnt] = $variations;

        return $variations;
    }

    function readArray($filename)
    {
        $file = file($filename);
        $i = 0;
        foreach ($file as $one) {
            $arr = explode(":", $one);
            $arr2 = explode(" ", trim($arr[1]));
            $this->answers[$i] = (int)$arr[0];
            $this->numbers[$i] = $arr2;
            $i++;
        }
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}