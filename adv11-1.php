<?php
class def
{
    protected $mat = [];
    protected $heads = [];

    function code()
    {
        ini_set('memory_limit', '2048M');
        $this->readArray("data/11-1.inp");
//        $this->readArray("data/11-1.example");
        $this->printArray($this->mat, "INITIAL");

//        for ($i = 0; $i < 75; $i++) {
//            rslog($i, '$i');
            $sum = $this->blinkOneByOne(75-1);
//            $this->printArray($this->mat, "Iteration $i:");
//        }

        rslog($sum, '$sum');
    }

    function blinkOneByOne($count)
    {
        $sum = 0;
        foreach ($this->mat as $key => $val) {
            rslog($key, '$key');
            $sum += 1 + $this->blinkOne($val, $count);
        }
        return $sum;
    }

    function blinkOne($val, $count, $curCount = 0)
    {
        $sum = 0;

        if ($val < 10000)
        if (isset($this->heads[$val][$curCount])) {
            return $this->heads[$val][$curCount];
        }



        if ($curCount > $count) {
//            rslog($val, '$val');
            return 0;
        }

        $val = (int)$val;
        if ($val == 0) {
            $sum += $this->blinkOne(1, $count, $curCount + 1);
        } else {
            $arr = str_split(trim((string)(int)$val));
            if (!(count($arr) % 2)) {
                $val1 = join("", array_slice($arr, 0, count($arr)/2));
                $val2 = join("", array_slice($arr, count($arr)/2, count($arr)/2));
                $sum += 1;
                $sum += $this->blinkOne((int)$val1, $count, $curCount + 1);
                $sum += $this->blinkOne((int)$val2, $count, $curCount + 1);
            } else {
                $sum += $this->blinkOne($val * 2024, $count, $curCount + 1);
            }
        }

        if ($val < 10000) $this->heads[$val][$curCount] = $sum;

        return $sum;
    }

    function printArray($mat, $comment = "Matrix:")
    {
        rslog($comment);
        echo join(' ', $mat);
    }

    function readArray($filename)
    {
        $str = trim(file_get_contents($filename));
        $mat = explode(" ", $str);
        foreach ($mat as $val) $this->mat[] = (int) $val;
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}