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

        if (empty($this->startTs)) $this->startTs = time();
        $ts = time() - $this->startTs;
        $ts = str_pad($ts, 9, '0', STR_PAD_LEFT);
        $ts = "[$ts]";


        if ($isSimple) {
            echo "$ts $desc: $val\r\n";
        } else {
            echo "$ts $desc:\r\n";
            var_dump($val);
            echo "\r\n";
        }
    }


    function getStringFromArray($arr)
    {
        return "'" . implode( '-', $arr) . "'";
    }

    function getStringFromArrayWithKeys($arr)
    {
        $a = [];
        foreach ($arr as $key => $val) {
            $a[] = "btn#$key => $val";
        }
        return "|" . implode( '  ', $a) . "|";
    }


    function code()
    {
        $this->startTs = time();
        $sum = 0;
        foreach ($this->indicators as $machineId => $indicator) {
            $linearX = $this->solveLinearSystemForMachine($machineId);
            if (is_null($linearX)) {
                $sum += $this->findMinForMachineBranchAndBound($machineId);
            } else {
                $solved = true;
                foreach ($linearX as $one) {
                    if ($one < 0) $solved = false;
                }
                if ($solved) {
                    $sum += array_sum($linearX);
                } else {
                    // longer but stable
                    $sum += $this->findMinForMachineBranchAndBound($machineId);
                }
            }
        }

        return $sum;
    }

    function findMinForMachineBranchAndBound($id)
    {

        $jolt = array_values(array_map("intval", $this->joltage[$id]));
        $joltCnt = count($jolt);

        $allZero = true;
        foreach ($jolt as $v) {
            if ($v !== 0) {
                $allZero = false;
                break;
            }
        }
        if ($allZero) {
            $this->debug("Machine $id already zero", "BB", true);
            return 0;
        }

        $buttonsInfo = [];
        foreach ($this->buttons[$id] as $buttonId => $button) {
            $switches = [];
            foreach ($button as $idx) {
                $idx = (int)$idx;
                $switches[] = $idx;
            }
            $len = count($switches);
            if (!$len) continue;

            $maxPress = PHP_INT_MAX;
            foreach ($switches as $idx) {
                if ($jolt[$idx] < $maxPress) {
                    $maxPress = $jolt[$idx];
                }
            }
            if ($maxPress <= 0) $maxPress = 0;

            $buttonsInfo[] = [
                "origId"   => $buttonId,
                "switches" => $switches,
                "len"      => $len,
                "maxPress" => $maxPress,
            ];
        }

        $btnCnt = count($buttonsInfo);
        if (!$btnCnt) {
            die("BB: machine $id has no effective buttons but joltage is not zero");
        }

        usort($buttonsInfo, function($a, $b) {
            if ($a["len"] == $b["len"]) return 0;
            return ($a["len"] > $b["len"]) ? -1 : 1;
        });

        $globalMaxLen = 0;
        foreach ($buttonsInfo as $info) {
            if ($info["len"] > $globalMaxLen) {
                $globalMaxLen = $info["len"];
            }
        }

        $remainingCapacity = [];
        $zeroCap = array_fill(0, $joltCnt, 0);
        $remainingCapacity[$btnCnt] = $zeroCap;
        for ($pos = $btnCnt - 1; $pos >= 0; $pos--) {
            $cap = $remainingCapacity[$pos + 1];
            $maxPress = $buttonsInfo[$pos]["maxPress"];
            if ($maxPress > 0) {
                foreach ($buttonsInfo[$pos]["switches"] as $idx) {
                    $cap[$idx] += $maxPress;
                }
            }
            $remainingCapacity[$pos] = $cap;
        }

        $bestSteps = PHP_INT_MAX;
        $bestPressesVec = null;

        $ceilDiv = function($a, $b) {
            if ($b <= 0) return PHP_INT_MAX;
            if ($a <= 0) return 0;
            return intdiv($a + $b - 1, $b);
        };

        $dfs = function($pos, $remaining, $stepsSoFar, $pressesVec)
        use (&$dfs, $btnCnt, $joltCnt, $buttonsInfo, $remainingCapacity, $globalMaxLen,
            &$bestSteps, &$bestPressesVec, $ceilDiv, $id)
        {
            if ($stepsSoFar >= $bestSteps) {
                return;
            }

            foreach ($remaining as $v) {
                if ($v < 0) {
                    return;
                }
            }

            if ($pos == $btnCnt) {
                foreach ($remaining as $v) {
                    if ($v !== 0) {
                        return;
                    }
                }
                if ($stepsSoFar < $bestSteps) {
                    $bestSteps = $stepsSoFar;
                    $bestPressesVec = $pressesVec;
                }
                return;
            }

            $cap = $remainingCapacity[$pos];
            for ($i = 0; $i < $joltCnt; $i++) {
                if ($remaining[$i] > $cap[$i]) {
                    return;
                }
            }

            $sumRem = 0;
            foreach ($remaining as $v) {
                $sumRem += $v;
            }
            if ($sumRem == 0) {
                if ($stepsSoFar < $bestSteps) {
                    $bestSteps = $stepsSoFar;
                    $bestPressesVec = $pressesVec;
                }
                return;
            }

            $theoreticalMin = $ceilDiv($sumRem, $globalMaxLen);
            if ($stepsSoFar + $theoreticalMin >= $bestSteps) {
                return;
            }

            $info = $buttonsInfo[$pos];
            $switches = $info["switches"];
            $maxPress = $info["maxPress"];
            if ($maxPress < 0) $maxPress = 0;

            $maxFeasible = $maxPress;
            if (!empty($switches)) {
                $localMin = PHP_INT_MAX;
                foreach ($switches as $idx) {
                    if ($remaining[$idx] < $localMin) {
                        $localMin = $remaining[$idx];
                    }
                }
                if ($localMin < $maxFeasible) {
                    $maxFeasible = $localMin;
                }
                if ($maxFeasible < 0) {
                    $maxFeasible = 0;
                }
            }

            for ($presses = 0; $presses <= $maxFeasible; $presses++) {
                $newSteps = $stepsSoFar + $presses;
                if ($newSteps >= $bestSteps) {
                    break;
                }

                if ($presses > 0) {
                    $newRemaining = $remaining;
                    foreach ($switches as $idx) {
                        $newRemaining[$idx] -= $presses;
                        if ($newRemaining[$idx] < 0) {
                            continue 2;
                        }
                    }
                } else {
                    $newRemaining = $remaining;
                }

                $newPressesVec = $pressesVec;
                $newPressesVec[$pos] = $presses;

                $dfs($pos + 1, $newRemaining, $newSteps, $newPressesVec);
            }
        };

        $initialPressesVec = array_fill(0, $btnCnt, 0);
        $this->debug("Machine $id:BnB search starting", "BB", true);
        $dfs(0, $jolt, 0, $initialPressesVec);

        if ($bestSteps === PHP_INT_MAX) {
            die("BB: no solution found for machine $id");
        }

        $dbg = [];
        if (!is_null($bestPressesVec)) {
            foreach ($bestPressesVec as $pos => $cnt) {
                if ($cnt > 0) {
                    $origId = $buttonsInfo[$pos]["origId"];
                    $infoStr = $this->getStringFromArray($this->buttons[$id][$origId]);
                    $dbg[] = "btn#" . $origId . " x " . $cnt . " " . $infoStr;
                }
            }
        }
        if (!empty($dbg)) {
            $this->debug(implode(" | ", $dbg), "BB best combination for machine $id", true);
        }

        $this->debug("Machine $id minimal presses (BB) = $bestSteps", "BB RESULT", true);
        return $bestSteps;
    }

    function solveLinearSystem($A, $b)
    {
        $m = count($A);
        if (!$m) return [];

        $n = count($A[0]);
        if (!$n) return [];

        // Расширенная матрица [A | b]
        for ($i = 0; $i < $m; $i++) {
            if (count($A[$i]) != $n) {
                die("solveLinearSystem: wrong row length $i");
            }
            $A[$i][] = $b[$i];
        }

        $EPS = 1e-9;
        $row = 0;

        // Прямой ход Гаусса
        for ($col = 0; $col < $n && $row < $m; $col++) {
            $sel = $row;
            for ($i = $row + 1; $i < $m; $i++) {
                if (abs($A[$i][$col]) > abs($A[$sel][$col])) {
                    $sel = $i;
                }
            }

            if (abs($A[$sel][$col]) < $EPS) {
                continue;
            }

            if ($sel != $row) {
                $tmp     = $A[$sel];
                $A[$sel] = $A[$row];
                $A[$row] = $tmp;
            }

            $div = $A[$row][$col];
            for ($j = $col; $j <= $n; $j++) {
                $A[$row][$j] /= $div;
            }

            for ($i = 0; $i < $m; $i++) {
                if ($i == $row) continue;
                $factor = $A[$i][$col];
                if (abs($factor) < $EPS) continue;
                for ($j = $col; $j <= $n; $j++) {
                    $A[$i][$j] -= $factor * $A[$row][$j];
                }
            }

            $row++;
        }

        // Проверка на противоречия: 0 ... 0 | c, c != 0 => нет решения вообще
        for ($i = 0; $i < $m; $i++) {
            $allZero = true;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j]) > $EPS) {
                    $allZero = false;
                    break;
                }
            }
            if ($allZero && abs($A[$i][$n]) > $EPS) {
                // 0 = c, несовместная система
                return null;
            }
        }

        // Здесь система совместна. Даже если rank < n (бесконечно много решений),
        // мы просто берём одно конкретное: свободные переменные = 0.
        $x = array_fill(0, $n, 0.0);

        for ($i = 0; $i < $m; $i++) {
            $pivotCol = -1;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j] - 1.0) < $EPS) {
                    $pivotCol = $j;
                    break;
                }
            }
            if ($pivotCol >= 0) {
                // Мы не вычитаем вклады свободных переменных,
                // фактически считаем их равными 0.
                $x[$pivotCol] = $A[$i][$n];
            }
        }

        return $x;
    }

    function solveLinearSystemForMachine($id)
    {
        $jolt = array_values(array_map('intval', $this->joltage[$id]));
        $size = count($jolt);

        $K = count($this->buttons[$id]);

        // Matrix b
        $matrixB = [];
        for ($i = 0; $i < $size; $i++) {
            $matrixB[$i] = array_fill(0, $K, 0.0);
        }

        foreach ($this->buttons[$id] as $buttonId => $button) {
            foreach ($button as $idx) {
                $idx = (int)$idx;
                if ($idx < 0 || $idx >= $size) {
                    die("button $buttonId bad index $idx on machine $id");
                }
                $matrixB[$idx][$buttonId] = 1.0;
            }
        }

        $res = $this->solveLinearSystem($matrixB, $jolt);
        if (is_null($res)) {
            $this->debug("No unique solution(!) Use other method", "Machine: $id", true);
            return null;
        }

        $intX = [];
        foreach ($res as $v) {
            $intX[] = (int)round($v);
        }

//        $this->debug($res,   "Linear solution (double) for machine $id", false);
        $this->debug($this->getStringFromArrayWithKeys($intX),"Linear solution (ints) for machine $id", true);

        // CHecking results
        $check = array_fill(0, $size, 0);
        foreach ($this->buttons[$id] as $buttonId => $button) {
            $times = isset($intX[$buttonId]) ? $intX[$buttonId] : 0;
            if (!$times) continue;
            foreach ($button as $idx) {
                $idx = (int)$idx;
                $check[$idx] += $times;
            }
        }

        $this->debug($this->getStringFromArray($jolt),     "jolt original for machine $id", true);
        $this->debug($this->getStringFromArray($check), "matrox Result check for machine $id", true);

        return $intX;
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

