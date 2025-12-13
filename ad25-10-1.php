<?php
class Def
{

    protected $indicators = [];
    protected $indicatorSize = [];
    protected $buttons = [];
    protected $joltage = [];

    protected $isDebug = true;
    protected $isMatrixPrint = true;


    function readInput($filePath)
    {
        $lines = file($filePath);

        foreach ($lines as $id=>$line) {
            $matches = [];
            if (!preg_match_all("/[.#]+/",$line, $matches)) {
                die('cant read');
            } else {
                $res = $matches[0][0];
                $this->indicatorSize[$id] = strlen($res);
                $res = str_split($res);
                foreach ($res as $k=>$v) {
                    $this->indicators[$id][$k] = $v == '#';
                }
            }
            if (!preg_match_all("/[{\d,}]+/",$line, $matches)) {
                die('cant read');
            } else {
                $res = $matches[0];
                $jolts = array_pop($res);
                $jolts = str_replace(['{', '}'], "", $jolts);
                $jolts = explode(',', $jolts);
                $this->joltage[$id] = $jolts;
                foreach($res as $match) {
                    $button = explode(',', $match);
                    $this->buttons[$id][] = $button;
                }

            }
//return;
        }
    }


    function debug($val, $desc = '', $isSimple = false)
    {
        if (!$this->isDebug) return;

        if ($isSimple) {
            echo "$desc: $val\r\n";
        } else {
            echo "$desc:\r\n";
            var_dump($val);
            echo "\r\n";
        }
    }


    function indi2Str($indi)
    {
        $str = '';
        foreach ($indi as $v) {
            $str .= (string)$v;
        }
        return $str;
    }

    function getAllCombinationsExclude($buttonsCnt, $lenght, $exclude)
    {
        $results = [];

        for($i = 0; $i < $buttonsCnt; $i++) {
            if (in_array($i, $exclude)) continue;
            $oneResult = [$i];
            if ($lenght < count($exclude) + 1) {
                foreach ($this->getAllCombinationsExclude($buttonsCnt, $lenght-1, array_merge($exclude, [$i])) as $oneRes2) {
                    $results[] = array_merge($oneResult, $oneRes2);
                }
            } else {
                $results[] = $oneResult;
            }
        }

        return $results;
    }

    function combIsInVariations($inputArr, $addition, $existingCombs)
    {
        $search = array_merge($inputArr, $addition); // comb we want to check for existence in ExistingCombs
        foreach ($existingCombs as $key => $exist) { // for each existing combs we will check each one
            $toCheck = true;
            foreach ($search as $buttonId) { // for each button in search comb
                if (in_array($buttonId, $exist)) { // if it is present in existing - we will deduct it from
                    $exist = array_diff($exist, [$buttonId]);
                } else {
                    // This combination does not contain the button we're checking, so it's different.
                    // No further checks for this comb
                    $toCheck = false;
                    break;
                }
                if ($toCheck && empty($exist)) return true;
            }
        }
        return false;
    }

    function addLevelOfCombinations($id, $inputArr, $otherResults) {

        $results = [];
        $buttonsCnt = count($this->buttons[$id]);

        for ($i = 0; $i< $buttonsCnt; $i++) {
            if (!in_array($i, $inputArr) && !$this->combIsInVariations($inputArr, [$i], $otherResults)) {
                $results[] = array_merge($inputArr, [$i]);
            }
        }

        return $results;
    }

    function calcIndicatorIsZero($id, $comb)
    {
//        $this->debug($id, 'ID:', true);
//        $this->debug($comb, 'COMB');

        $temp = $this->indicators[$id];
        if (!empty($comb)){
            foreach ($comb as $buttonId) {
//                echo "$id, $buttonId <--- buttonId\r\n";

                $butArr = $this->buttons[$id][$buttonId];
                foreach ($butArr as $switch) {
                    $temp[$switch] = ! $temp[$switch];
                }
            }
        }
        return array_sum($temp) == 0;
    }

    function findMinForMachine($id)
    {
        $this->debug($id, 'ID:', true);

        if (array_sum($this->indicators[$id]) == 0) return 0;


//        var_dump($this->buttons[$id], "buttons");
//        die();

//        $emptyKeysComb = array_fill(0, count($this->buttons[$id]), []);
//        if ($this->calcIndicatorIsZero($id, $emptyKeysComb)) {
//            // Indicator is already in the proper state
//            return 0;
//        }
//
        $combs = [];
        foreach ($this->buttons[$id] as $buttonKey => $buttonSwitches) {
            // We will start with just one button
            $combs[] = [$buttonKey];
        }


        $buttonsCnt = count($this->buttons[$id]);

        for ($lenght = 1; $lenght <= $buttonsCnt; $lenght++) {
//            var_dump($combs, 'COMBS1');
            if ($lenght > 1) {
                $newcombs = [];
                foreach ($combs as $comb) {
                    $newcombs = array_merge($newcombs, $this->addLevelOfCombinations($id, $comb, $newcombs));
                }
                $combs = $newcombs;
            }
//            var_dump($combs, 'COMBS2');
            foreach ($combs as $comb) {
                if ($this->calcIndicatorIsZero($id, $comb)) {
                    // FOUND
                    return $lenght;
                }
            }
        }


        die("NOT FOUND FOR MACHINE $id");

    }


    function code()
    {
        $sum = 0;
        foreach ($this->indicators as $machineId => $indicator) {
            $sum += $this->findMinForMachine($machineId);
        }

        return $sum;
    }


    function run()
    {
        // Main Execution
        $filePath = "data/25-10-1.example"; $this->isMatrixPrint = true; $this->isDebug = true;
        $filePath = "data/25-10-1.inp"; $this->isMatrixPrint = false; $this->isDebug = true;
//        $filePath = "data/25-10-2.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
//        $filePath = "data/25-10-3.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }


}


ini_set("memory_limit", '32000M');
ini_set("max_execution_time", '60000');
ini_set("max_input_nesting_level", '10000');
//system('stty cbreak -echo');
$Def = new Def();
$Def->run();

