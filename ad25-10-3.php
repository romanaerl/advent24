<?php
class Def
{

    protected $indicators = [];
    protected $indicatorSize = [];
    protected $buttons = [];
    protected $joltage = [];

    protected $isDebug = true;
    protected $isMatrixPrint = true;

    protected $lastTS = 0;
    protected $startTs = 0;

    protected $significantDigitChanged = false;


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


    function debug($val, $desc = '', $isSimple = false, $force = false)
    {
        if (!$force &&!$this->isDebug) return;

        if ($isSimple) {
            echo "$desc: $val\r\n";
        } else {
            echo "$desc:\r\n";
            var_dump($val);
            echo "\r\n";
        }
    }


    function getStringFromArray($arr)
    {
        return "'" . implode( '-', $arr) . "'";
    }


    function getMaximumTimesButtonCanHit($id, $obId, $jolt)
    {
        $max = 100000000000;
        $button = $this->buttons[$id][$obId];
        foreach ($button as $switch) {
            $nmax = $jolt[$switch];
            if ($nmax < $max) {
                $max = $nmax;
            }
        }

        return $max;
    }

    function findMinForMachine($id)
    {
        $this->debug($id, 'ID:', true, true);
        $this->debug($this->getStringFromArray($this->joltage[$id]), 'JOLT:', true, true);
        foreach ($this->buttons[$id] as $key => $btn) {
            $prnSwitch = implode(",", $btn);
            $prnBut = "BTNs $key: " . $prnSwitch;
            $this->debug($prnBut, '', true);
        }


        $jolt = $this->joltage[$id];

        $maximums = [];
        foreach ($this->buttons[$id] as $obId => $oneButton)
        {
            $maximums[$obId] = $this->getMaximumTimesButtonCanHit($id, $obId, $jolt);
        }

        $buttonsLengthArray = [];
        foreach ($this->buttons[$id] as $bk => $button) {
            $length = count($button);
            if (!isset($buttonsLengthArray[$length])) $buttonsLengthArray[$length] = [];
            $buttonsLengthArray[$length][] = $bk;
        }

        $maximusArray = [];
        foreach ($this->buttons[$id] as $buttonId => $oneButton) {
            $maxim = $maximums[$buttonId];
            if (!isset($maximusArray[$maxim])) $maximusArray[$maxim] = [];
            $maximusArray[$maxim][] = $buttonId;
        }




        krsort($buttonsLengthArray);

        $lenSortedArrayWithMaximums = [];
        foreach ($buttonsLengthArray as $len => $buttons) {
            foreach ($buttons as $buttonId) {
                $lenSortedArrayWithMaximums[] = ['butId' => $buttonId, 'max' => $maximums[$buttonId], 'info' => $this->getStringFromArray($this->buttons[$id][$buttonId])];
            }
        }

        ksort($maximusArray);
        $maxSortedArray = [];
        foreach ($maximusArray as $max => $buttons) {
            foreach ($buttons as $buttonId) {
                $maxSortedArray[] = ['butId' => $buttonId, 'max' => $maximums[$buttonId], 'info' => $this->getStringFromArray($this->buttons[$id][$buttonId])];
            }
        }

        ksort($buttonsLengthArray);
        $this->debug($this->printLenSortedArrayWithMaximums($lenSortedArrayWithMaximums), 'INITIAL', true);

        $winningIdMax = $this->discountTillZero($id, $lenSortedArrayWithMaximums);
        if ($winningIdMax === false) {
            die('We are on zero and no results');
        }

        $sum = 0;
        foreach ($winningIdMax as $one)
        {
            $sum += (int)$one['max'];
        }
        $this->debug("Sum for $id is $sum ==============<", "=========>", true);


        return $sum;
    }

    function deductChecking($id, $lenSortedArrayWithMaximums, $original)
    {
        $maxDigitPos = count($lenSortedArrayWithMaximums)-1;

        $oneToCheck = [];
        // Will check the first part only. If it goes below zero already - we will deduct it even before
        if ($this->significantDigitChanged)
            for ($i = 0; $i <= $maxDigitPos ; $i++) {
                $this->significantDigitChanged = false;
                $oneToCheck[] = $lenSortedArrayWithMaximums[$i];
                $continue = true;
                $hadNulls = false;
                while($continue && is_null($this->checkVariation($id, $oneToCheck))) {
                    $hadNulls = true;
                    $max = $oneToCheck[$i]['max'];
                    if ($max > 0) {
                        $oneToCheck[$i]['max']--;
                    } else {
                        $continue = false;
                    }
                }
                if ($continue && $hadNulls) {
                    // this means the nulls have stopped, se we are no longer going below zero for this number
                    foreach ($oneToCheck as $k=>$one) {
                        // we will set new deducted first digits to the number
                        $lenSortedArrayWithMaximums[$k]['max'] = $one['max'];
                    }
                    for ($ii = $i+1; $ii <= $maxDigitPos; $ii++) {
                        // and restore all right side numbers back to its original state
                        $lenSortedArrayWithMaximums[$ii]['max'] = $original[$ii]['max'];
                    }
                    break;
                }
            }


        // Normal one by one deduct
        $digit = $maxDigitPos;
        for ($i = $digit; $i>=0; $i--) {
            // Only if the current digit is more than zero. Otherwise, will go to the left digit
            if ((int)$lenSortedArrayWithMaximums[$i]['max']) {
                $lenSortedArrayWithMaximums[$i]['max']--;
                if ($i < $maxDigitPos - 2) $this->significantDigitChanged = true;
                for ($ii = $i+1; $ii <= $maxDigitPos; $ii++) {
                    // restoring all other values to default
                    $lenSortedArrayWithMaximums[$ii]['max'] = $original[$ii]['max'];
                }
                // return modified array (deducted by 1)
                return $lenSortedArrayWithMaximums;
            }
        }
        return false;
    }

    function checkVariation($id, $lenSortedArrayWithMaximums)
    {
        $joltage = $this->joltage[$id];
        foreach ($lenSortedArrayWithMaximums as $one) {
            $button = $this->buttons[$id][$one['butId']];
            $times = (int)$one['max'];
            foreach ($button as $switch) {
                $joltage[$switch] -= $times;
                if ($joltage[$switch] < 0) {
                    // Joltage goes below zero
                    return null;
                }
            }
        }

        return array_sum($joltage) == 0;
    }

    function printLenSortedArrayWithMaximums($arr)
    {
        $maxes = [];
        foreach ($arr as $one) {
            $maxes[] = str_pad("b" . $one['butId'] . " x " . $one['max'] . " ", 12, " ");
        }
        return implode("=", $maxes);
    }

    function discountTillZero($id, $lenSortedArrayWithMaximums, $lenSortedArrayWithMaximums2 = null) {
        $originalIdMax = $lenSortedArrayWithMaximums;
        if ($lenSortedArrayWithMaximums2)
            $originalIdMax2 = $lenSortedArrayWithMaximums2;



        $continue = true;
        while ($continue) {
            if (($this->lastTS < time() - 1) && is_array($lenSortedArrayWithMaximums)) {
                $this->lastTS = time();
                $tsSince = time() - $this->startTs;
                $tsSince = str_pad($tsSince, 10, '0', STR_PAD_LEFT);
                $prnt = $this->printLenSortedArrayWithMaximums($lenSortedArrayWithMaximums);
                $this->debug($prnt, "[$tsSince]CurrentComb1:", true);
                if ($lenSortedArrayWithMaximums2) {
                    $prnt2 = $this->printLenSortedArrayWithMaximums($lenSortedArrayWithMaximums2);
                    $this->debug($prnt2, "CurrentComb2:", true);
                }
            }

            $result = $this->checkVariation($id, $lenSortedArrayWithMaximums);
            if (!$result) {
                $res = $this->deductChecking($id, $lenSortedArrayWithMaximums, $originalIdMax);
                if (empty($res)) {
                    return false;
                } else {
                    $lenSortedArrayWithMaximums = $res;
                }
            } else {
                // Win!
                return $lenSortedArrayWithMaximums;
            }
            if ($lenSortedArrayWithMaximums2)
            {
                $result2 = $this->checkVariation($id, $lenSortedArrayWithMaximums2);
                if (!$result2) {
                    $res = $this->deductChecking($id, $lenSortedArrayWithMaximums2, $originalIdMax2);
                    if (empty($res)) {
                        return false;
                    } else {
                        $lenSortedArrayWithMaximums2 = $res;
                    }
                } else {
                    // Win!
                    return $lenSortedArrayWithMaximums2;
                }
            }
        }

        return [];
    }

    function findMinForMachineBFS($id)
    {
        // Начальное состояние зарядов
        $start = array_map('intval', $this->joltage[$id]);

        // Быстрый check: уже все нули
        $allZero = true;
        foreach ($start as $v) {
            if ($v !== 0) {
                $allZero = false;
                break;
            }
        }
        if ($allZero) {
            $this->debug("Machine $id already zero", 'BFS', true);
            return 0;
        }

        if (empty($this->buttons[$id])) {
            die("BFS: no buttons for machine $id but joltage is not zero");
        }

        // Очередь для BFS: каждый элемент = ['state' => [...], 'steps' => N]
        $queue = new \SplQueue();
        $queue->enqueue(['state' => $start, 'steps' => 0]);

        // Множество посещенных состояний, чтобы не зациклиться
        // Используем json_encode как ключ
        $visited = [];
        $visited[json_encode($start)] = true;

        $this->debug($this->getStringFromArray($start), "BFS start state for machine $id", true);

        // Основной цикл BFS
        while (!$queue->isEmpty()) {
            $current = $queue->dequeue();
            $state   = $current['state'];
            $steps   = $current['steps'];

            // Перебираем все кнопки, нажимаем каждую по одному разу
            foreach ($this->buttons[$id] as $buttonId => $button) {
                $next = $state;
                $ok   = true;

                // Применяем кнопку: уменьшаем все указанные индексы на 1
                foreach ($button as $idx) {
                    $idx = (int)$idx;
                    if (!array_key_exists($idx, $next)) {
                        // На всякий случай — некорректный индекс
                        $ok = false;
                        break;
                    }
                    $next[$idx]--;
                    if ($next[$idx] < 0) {
                        // Ушли в минус — недопустимое состояние, не добавляем в очередь
                        $ok = false;
                        break;
                    }
                }

                if (!$ok) {
                    continue;
                }

                $key = json_encode($next);
                if (isset($visited[$key])) {
                    // Уже видели это состояние с меньшим или равным количеством шагов
                    continue;
                }
                $visited[$key] = true;

                // Проверяем — все ли нули
                $allZero = true;
                foreach ($next as $v) {
                    if ($v !== 0) {
                        $allZero = false;
                        break;
                    }
                }

                if ($allZero) {
                    $resultSteps = $steps + 1;
                    $this->debug("Machine $id BFS minimal presses = $resultSteps", 'BFS RESULT', true);
                    return $resultSteps;
                }

                // Продолжаем BFS от этого состояния
                $queue->enqueue(['state' => $next, 'steps' => $steps + 1]);
            }
        }

        // Если мы сюда дошли, значит, состояние "все нули" недостижимо
        die("BFS: no solution found for machine $id");
    }


    function code()
    {
        $this->startTs = time();
        $sum = 0;
        foreach ($this->indicators as $machineId => $indicator) {
            $sum += $this->findMinForMachineBFS($machineId);
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

        echo "TOTAL SUM Result " . json_encode($this->code()) ."\r\n";

    }


}


ini_set("memory_limit", '320000M');
ini_set("max_execution_time", '60000');
ini_set("max_input_nesting_level", '10000');
//system('stty cbreak -echo');
$Def = new Def();
$Def->run();

