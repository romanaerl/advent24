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





    function findMinForMachineBranchAndBound($id)
    {
        // Подготовка данных
        if (empty($this->joltage[$id])) {
            die("BB: no joltage for machine $id");
        }
        if (empty($this->buttons[$id])) {
            die("BB: no buttons for machine $id");
        }

        // Вектор начальных зарядов (0..N-1)
        $j = array_values(array_map('intval', $this->joltage[$id]));
        $N = count($j);

        // Быстрая проверка: всё нули
        $allZero = true;
        foreach ($j as $v) {
            if ($v !== 0) {
                $allZero = false;
                break;
            }
        }
        if ($allZero) {
            $this->debug("Machine $id already zero", 'BB', true);
            return 0;
        }

        // Собираем кнопки в удобную структуру и считаем maxPress для каждой
        // buttonsInfo[k] = [
        //   'origId'  => исходный индекс кнопки,
        //   'switches'=> [индексы ячеек],
        //   'len'     => сколько ячеек затрагивает,
        //   'maxPress'=> максимум раз, сколько её можно нажать
        // ]
        $buttonsInfo = [];
        foreach ($this->buttons[$id] as $buttonId => $button) {
            $switches = [];
            foreach ($button as $idx) {
                $idx = (int)$idx;
                if ($idx < 0 || $idx >= $N) {
                    die("BB: button $buttonId for machine $id references invalid index $idx");
                }
                $switches[] = $idx;
            }
            $len = count($switches);
            if ($len === 0) {
                // Кнопка, которая ничего не делает — бессмысленна, но можно просто пропустить
                continue;
            }
            // maxPress = мин(j[i]) по всем i из switches
            $maxPress = PHP_INT_MAX;
            foreach ($switches as $idx) {
                if ($j[$idx] < $maxPress) {
                    $maxPress = $j[$idx];
                }
            }
            if ($maxPress <= 0) {
                // Вообще не можем жать (сразу утащит в минус или бесполезна)
                $maxPress = 0;
            }

            $buttonsInfo[] = [
                'origId'   => $buttonId,
                'switches' => $switches,
                'len'      => $len,
                'maxPress' => $maxPress,
            ];
        }

        $K = count($buttonsInfo);
        if ($K === 0) {
            die("BB: machine $id has no effective buttons but joltage is not zero");
        }

        // Сортируем кнопки по длине (кол-во затрагиваемых ячеек) по убыванию,
        // чтобы сначала рассматривать "самые мощные" кнопки.
        usort($buttonsInfo, function ($a, $b) {
            if ($a['len'] === $b['len']) return 0;
            return ($a['len'] > $b['len']) ? -1 : 1;
        });

        // Максимальная длина кнопки (для грубой нижней оценки)
        $globalMaxLen = 0;
        foreach ($buttonsInfo as $info) {
            if ($info['len'] > $globalMaxLen) {
                $globalMaxLen = $info['len'];
            }
        }

        // Предвычисляем остаточную "ёмкость" по ячейкам от кнопок, начиная с позиции pos
        // remainingCapacity[pos][i] = максимум единиц заряда, которое можно ещё снять на ячейке i
        // кнопками с индекса pos .. K-1 (если нажать их максимально).
        $remainingCapacity = [];
        $zeroCap = array_fill(0, $N, 0);
        $remainingCapacity[$K] = $zeroCap;

        for ($pos = $K - 1; $pos >= 0; $pos--) {
            $cap = $remainingCapacity[$pos + 1]; // копия
            $maxPress = $buttonsInfo[$pos]['maxPress'];
            if ($maxPress > 0) {
                foreach ($buttonsInfo[$pos]['switches'] as $idx) {
                    $cap[$idx] += $maxPress;
                }
            }
            $remainingCapacity[$pos] = $cap;
        }

        // Лучшая найденная верхняя граница по количеству нажатий
        $bestSteps      = PHP_INT_MAX;
        $bestPressesVec = null; // для отладки/интереса

        // Вспомогательная функция для ceil(a/b)
        $ceilDiv = function($a, $b) {
            if ($b <= 0) return PHP_INT_MAX;
            if ($a <= 0) return 0;
            return intdiv($a + $b - 1, $b);
        };

        // Рекурсивный DFS с ветвлением и отсечениями
        $dfs = function($pos, $remaining, $stepsSoFar, $pressesVec)
        use (&$dfs, $K, $N, $buttonsInfo, $remainingCapacity, $globalMaxLen,
            &$bestSteps, &$bestPressesVec, $ceilDiv, $id)
        {
            // Если уже хуже текущего лучшего — отсечём сразу
            if ($stepsSoFar >= $bestSteps) {
                return;
            }

            // Проверка: остаток по какой-либо ячейке отрицательный — недопустимо
            foreach ($remaining as $v) {
                if ($v < 0) {
                    return;
                }
            }

            // Если дошли до конца списка кнопок
            if ($pos === $K) {
                // Проверяем, все ли заряды обнулены
                foreach ($remaining as $v) {
                    if ($v !== 0) {
                        return;
                    }
                }
                // Все нули: обновляем лучшее решение
                if ($stepsSoFar < $bestSteps) {
                    $bestSteps      = $stepsSoFar;
                    $bestPressesVec = $pressesVec;
                }
                return;
            }

            // Проверка "физической достижимости":
            // для каждой ячейки остаток не должен превышать суммарную ёмкость
            // оставшихся кнопок
            $cap = $remainingCapacity[$pos];
            for ($i = 0; $i < $N; $i++) {
                if ($remaining[$i] > $cap[$i]) {
                    // Не сможем добить до нуля — отсечь
                    return;
                }
            }

            // Грубая нижняя граница по суммарному количеству нажатий:
            // sum(remaining) нельзя снять меньше чем за ceil(sum / globalMaxLen) нажатий
            $sumRem = 0;
            foreach ($remaining as $v) {
                $sumRem += $v;
            }
            if ($sumRem === 0) {
                // На самом деле это уже решение (но сюда мы могли дойти не на pos=K)
                if ($stepsSoFar < $bestSteps) {
                    $bestSteps      = $stepsSoFar;
                    $bestPressesVec = $pressesVec;
                }
                return;
            }

            $theoreticalMin = $ceilDiv($sumRem, $globalMaxLen);
            if ($stepsSoFar + $theoreticalMin >= $bestSteps) {
                // Даже в лучшем случае тут не получится лучше текущего bestSteps
                return;
            }

            // Текущая кнопка
            $info     = $buttonsInfo[$pos];
            $switches = $info['switches'];
            $maxPress = $info['maxPress'];
            if ($maxPress < 0) $maxPress = 0;

            // Дополнительная верхняя граница по этой кнопке:
            // нельзя нажать больше, чем минимальный остаток по её ячейкам
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

            // Важно: перебираем от 0 до maxFeasible (минимальные нажатия вперёд),
            // чтобы быстрее находить хорошие (малые) решения и улучшать верхнюю границу.
            for ($presses = 0; $presses <= $maxFeasible; $presses++) {
                $newSteps = $stepsSoFar + $presses;
                if ($newSteps >= $bestSteps) {
                    // уже хуже текущего лучшего — нет смысла углубляться
                    break;
                }

                // Применяем текущую кнопку presses раз к остатку
                if ($presses > 0) {
                    $newRemaining = $remaining;
                    foreach ($switches as $idx) {
                        $newRemaining[$idx] -= $presses;
                        if ($newRemaining[$idx] < 0) {
                            // при увеличении presses будет только хуже — можно выйти из цикла
                            continue 2; // перейти к следующему значению presses в for
                        }
                    }
                } else {
                    $newRemaining = $remaining;
                }

                $newPressesVec        = $pressesVec;
                $newPressesVec[$pos]  = $presses;

                // Рекурсивно переходим к следующей кнопке
                $dfs($pos + 1, $newRemaining, $newSteps, $newPressesVec);
            }
        };

        // Запуск поиска
        $initialPressesVec = array_fill(0, $K, 0);
        $this->debug("Machine $id: starting Branch-and-Bound search", 'BB', true);
        $dfs(0, $j, 0, $initialPressesVec);

        if ($bestSteps === PHP_INT_MAX) {
            die("BB: no solution found for machine $id");
        }

        // Для интереса можно вывести найденное распределение нажатий по отсортированным кнопкам
        $dbg = [];
        if ($bestPressesVec !== null) {
            foreach ($bestPressesVec as $pos => $cnt) {
                if ($cnt > 0) {
                    $origId  = $buttonsInfo[$pos]['origId'];
                    $infoStr = $this->getStringFromArray($this->buttons[$id][$origId]);
                    $dbg[]   = "btn#{$origId} x {$cnt} {$infoStr}";
                }
            }
        }
        if (!empty($dbg)) {
            $this->debug(implode(" | ", $dbg), "BB best combination for machine $id", true);
        }

        $this->debug("Machine $id minimal presses (BB) = $bestSteps", 'BB RESULT', true);
        return $bestSteps;
    }


    function code()
    {
        $this->startTs = time();
        $sum = 0;
        foreach ($this->indicators as $machineId => $indicator) {
//            $sum += $this->findMinForMachineBFS($machineId);
//            $sum += $this->findMinForMachineBranchAndBound($machineId);
            $linearX = $this->solveLinearSystemForMachine($machineId);
            if (is_null($linearX)) {
                $sum += $this->findMinForMachineBranchAndBound($machineId);
            } else {
                $sum += array_sum($linearX);
            }
        }

        return $sum;
    }

    protected function solveLinearSystem(array $A, array $b)
    {
        $m = count($A);          // число уравнений
        if ($m === 0) return [];

        $n = count($A[0]);       // число неизвестных
        if ($n === 0) return [];

        // Строим расширенную матрицу [A | b]
        for ($i = 0; $i < $m; $i++) {
            if (count($A[$i]) !== $n) {
                throw new \RuntimeException("solveLinearSystem: row $i has wrong length");
            }
            $A[$i][] = $b[$i]; // последний столбец — правая часть
        }

        $EPS = 1e-9;
        $row = 0;

        // Прямой ход
        for ($col = 0; $col < $n && $row < $m; $col++) {
            // Ищем строку с максимальным по модулю элементом в этом столбце (частичный выбор главного элемента)
            $sel = $row;
            for ($i = $row + 1; $i < $m; $i++) {
                if (abs($A[$i][$col]) > abs($A[$sel][$col])) {
                    $sel = $i;
                }
            }

            // Если ведущий элемент слишком мал — считаем столбец нулевым, переходим к следующему
            if (abs($A[$sel][$col]) < $EPS) {
                continue;
            }

            // Меняем текущую строку и строку с ведущим элементом
            if ($sel !== $row) {
                $tmp      = $A[$sel];
                $A[$sel]  = $A[$row];
                $A[$row]  = $tmp;
            }

            // Нормируем ведущую строку
            $div = $A[$row][$col];
            for ($j = $col; $j <= $n; $j++) {
                $A[$row][$j] /= $div;
            }

            // Обнуляем все остальные строки по этому столбцу
            for ($i = 0; $i < $m; $i++) {
                if ($i === $row) continue;
                $factor = $A[$i][$col];
                if (abs($factor) < $EPS) continue;
                for ($j = $col; $j <= $n; $j++) {
                    $A[$i][$j] -= $factor * $A[$row][$j];
                }
            }

            $row++;
        }

        // Проверяем на противоречия: строка вида [0 0 0 ... 0 | c], c != 0
        for ($i = $row; $i < $m; $i++) {
            $allZero = true;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j]) > $EPS) {
                    $allZero = false;
                    break;
                }
            }
            if ($allZero && abs($A[$i][$n]) > $EPS) {
                // 0 = c, нет решения
                return null;
            }
        }

        // Определяем ранг
        $rank = 0;
        for ($i = 0; $i < $m; $i++) {
            $nonZero = false;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j]) > $EPS) {
                    $nonZero = true;
                    break;
                }
            }
            if ($nonZero) $rank++;
        }

        // Если ранг < n — бесконечно много решений (свободные переменные).
        // Для простоты здесь вернём null, чтобы не делать выбор свободных переменных.
        if ($rank < $n) {
            return null;
        }

        // Теперь матрица в приведённом ступенчатом виде, решение считываем из последнего столбца.
        $x = array_fill(0, $n, 0.0);

        // Ищем ведущие единицы по столбцам
        for ($i = 0; $i < $m; $i++) {
            $pivotCol = -1;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j] - 1.0) < $EPS) {
                    $pivotCol = $j;
                    break;
                }
            }
            if ($pivotCol >= 0) {
                $x[$pivotCol] = $A[$i][$n];
            }
        }

        return $x;
    }

    /**
     * Построить матрицу B и вектор j для машины $id и решить B x = j.
     * Возвращает массив x (вещественные значения, сколько раз нажать каждую кнопку),
     * либо null, если система не имеет единственного решения.
     */
    function solveLinearSystemForMachine($id)
    {
        // j — вектор правой части
        $j = array_values(array_map('intval', $this->joltage[$id]));
        $N = count($j);

        // Строим матрицу B: N строк, K столбцов
        $K = count($this->buttons[$id]);
        $B = [];
        for ($i = 0; $i < $N; $i++) {
            $B[$i] = array_fill(0, $K, 0.0);
        }

        foreach ($this->buttons[$id] as $buttonId => $button) {
            foreach ($button as $idx) {
                $idx = (int)$idx;
                if ($idx < 0 || $idx >= $N) {
                    die("solveLinearSystemForMachine: button $buttonId references invalid index $idx for machine $id");
                }
                // Эта кнопка уменьшает ячейку idx на 1 ⇒ в матрице B на позиции (idx, buttonId) ставим 1
                $B[$idx][$buttonId] = 1.0;
            }
        }

        // Решаем систему B x = j
        $x = $this->solveLinearSystem($B, $j);
        if ($x === null) {
            $this->debug(null, "solveLinearSystemForMachine: no unique solution for machine $id", true);
            return null;
        }

        // Для удобства округлим до ближайших целых и сразу выведем
        $xInt = [];
        foreach ($x as $v) {
            $xInt[] = (int)round($v);
        }

        $this->debug($x,   "Linear solution (double) for machine $id", false);
        $this->debug($xInt,"Linear solution (rounded ints) for machine $id", false);

        // Можно сразу проверить, что B * xInt == j
        $check = array_fill(0, $N, 0);
        foreach ($this->buttons[$id] as $buttonId => $button) {
            $times = $xInt[$buttonId] ?? 0;
            if ($times == 0) continue;
            foreach ($button as $idx) {
                $idx = (int)$idx;
                $check[$idx] += $times;
            }
        }

        $this->debug($this->getStringFromArray($j),     "j original", true);
        $this->debug($this->getStringFromArray($check), "B*xInt check", true);

        return $xInt; // или вернуть $x (вещественное), как тебе удобнее
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

