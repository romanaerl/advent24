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

    protected $cache = [];
    protected $cacheFileName = 'data/ad25-10-1-cachev3.txt';



    function writeCache($id, $sum)
    {
        $cache = $this->readCache();
        $cache[$id] = $sum;
        $data = [];
        foreach ($cache as $id => $sum) {
            $data[] = $id . '==>' . $sum;
        }
        file_put_contents($this->cacheFileName, implode(PHP_EOL, $data));
    }

    function readCache()
    {
        $cache = [];
        if (file_exists($this->cacheFileName)) {
            $lines = file($this->cacheFileName);
            foreach ($lines as $line) {
                $l = explode('==>', $line);
                if (count($l) < 2) {
                    continue;
                }
                //id - sum
                $cache[$l[0]] = $l[1];
            }
        }
        return $cache;
    }

    function readInput($filePath)
    {
        $this->cache = $this->readCache();

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

    function getResult($machineId)
    {
        $res = true;
        // Пытаемся решить "чисто уравнениями" с учётом целых и >= 0
        $linearInt = $this->solveLinearSystemNonNegativeIntForMachine($machineId);
        if (is_null($linearInt)) {
            $res = false;
        } else {
            foreach ($linearInt as $one) {
                if ($one < 0) $res = false;
            }
        }

        if ($res) {
            return array_sum($linearInt);
        }

//        return 10;

        if (isset($this->cache[$machineId])) {
            return $this->cache[$machineId];
        } else {
            // Если не получилось найти хорошее решение — запасной вариант (B&B)
            $res = $this->findMinForMachineBranchAndBound($machineId, 2);
            $this->writeCache($machineId, $res);
            return $res;
        }
    }


    function code()
    {
        $this->startTs = time();

        // all machine Ids
        $tasks = array_keys($this->buttons);

        $results  = [];
        $children = [];

        $pid2id = [];

        foreach ($tasks as $id => $payload) {

            // создаём канал «родитель ↔ ребёнок»
            $pair = [];
            $res = socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair);
            if ($res === false) {
                die("socket_create_pair failed");
            }

            $pid = pcntl_fork();
            $pid2id[$pid] = $id;
            if ($pid === -1) {
                die("fork failed");
            }

            if ($pid === 0) {
                // ---- CHILD ----
                socket_close($pair[0]); // закрываем сторону родителя

                // здесь выполняем работу
                $result = $this->getResult($payload);

                // передаём обратно результат
                $data = json_encode(['id' => $id, 'result' => $result]);
                socket_write($pair[1], $data);

                socket_close($pair[1]);
                exit(0);
            }

            // ---- PARENT ----
            socket_close($pair[1]); // закрываем сторону ребёнка

            // ВАЖНО: сохраняем САМИЙ СОКЕТ, а не массив
            $children[$pid] = $pair[0];
        }

        while (!empty($children)) {

            // ---- 1) Раз в секунду выводим статус ----
            $inProg = [];
            foreach (array_keys($children) as $pid1) {
                $inProg[] = $pid2id[$pid1];
            }
            $this->debug("Still waiting for children: " . implode(', ', $inProg), "", true);

            // ---- 2) Проверяем, завершился ли какой-нибудь ребёнок ----
            foreach ($children as $pid => $sock) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res > 0) {
                    // ребёнок завершился — читаем его данные
                    $buffer = '';
                    while (($chunk = socket_read($sock, 2048)) !== false && $chunk !== '') {
                        $buffer .= $chunk;
                    }
                    socket_close($sock);

                    $decoded = json_decode($buffer, true);
                    if ($decoded) {
                        $results[$decoded['id']] = $decoded['result'];
                    }

                    unset($children[$pid]);
                }
            }

            // маленькая пауза
            sleep(1);
        }

        return array_sum($results);
    }

    function findMinForMachineBranchAndBound($id, $workers = 4)
    {
        // ==== 1. Подготовка (это твой старый код) ====

        $jolt = array_values(array_map("intval", $this->joltage[$id]));
        $joltCnt = count($jolt);

        // Если уже все нули — ответ 0
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

        // Собираем информацию по кнопкам
        $buttonsInfo = [];
        foreach ($this->buttons[$id] as $buttonId => $button) {
            $switches = [];
            foreach ($button as $idx) {
                $idx = (int)$idx;
                $switches[] = $idx;
            }
            $len = count($switches);
            if (!$len) continue;

            // максимальное возможное число нажатий по этой кнопке,
            // исходя из минимального jolt среди затронутых индексов
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

        // Сортируем кнопки по длине (как у тебя раньше)
        usort($buttonsInfo, function($a, $b) {
            if ($a["len"] == $b["len"]) return 0;
            return ($a["len"] > $b["len"]) ? -1 : 1;
        });

        // Максимальная длина кнопки
        $globalMaxLen = 0;
        foreach ($buttonsInfo as $info) {
            if ($info["len"] > $globalMaxLen) {
                $globalMaxLen = $info["len"];
            }
        }

        // Предварительно считаем remainingCapacity как в старом коде
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

        $this->debug("Machine $id:BnB search starting (parallel)", "BB", true);

        // ==== 2. Формируем подзадачи по первой кнопке (pos = 0) ====

        $firstInfo  = $buttonsInfo[0];
        $switches0  = $firstInfo["switches"];
        $maxPress0  = $firstInfo["maxPress"];
        if ($maxPress0 < 0) $maxPress0 = 0;

        // ограничим maxFeasible0 так же, как в DFS
        $maxFeasible0 = $maxPress0;
        if (!empty($switches0)) {
            $localMin = PHP_INT_MAX;
            foreach ($switches0 as $idx) {
                if ($jolt[$idx] < $localMin) {
                    $localMin = $jolt[$idx];
                }
            }
            if ($localMin < $maxFeasible0) {
                $maxFeasible0 = $localMin;
            }
            if ($maxFeasible0 < 0) {
                $maxFeasible0 = 0;
            }
        }

        // Список стартовых подзадач: все варианты нажатий первой кнопки
        $jobs = [];
        for ($p0 = 0; $p0 <= $maxFeasible0; $p0++) {
            $remaining = $jolt;
            if ($p0 > 0) {
                foreach ($switches0 as $idx) {
                    $remaining[$idx] -= $p0;
                }
            }
            $pressesVec    = array_fill(0, $btnCnt, 0);
            $pressesVec[0] = $p0;
            $stepsSoFar    = $p0;

            $jobs[] = [
                'remaining'  => $remaining,
                'stepsSoFar' => $stepsSoFar,
                'pressesVec' => $pressesVec,
                'startPos'   => 1,
            ];
        }

        // ==== 3. Распараллеливаем эти подзадачи по процессам ====

        $jobCount = count($jobs);
        if ($jobCount < $workers) {
            $workers = $jobCount;
        }
        if ($workers < 1) {
            $workers = 1;
        }

        $this->debug("Machine $id: BnB parallel, jobs=$jobCount, workers=$workers", "BB", true);

        $globalBestSteps   = PHP_INT_MAX;
        $globalBestPresses = null;

        $children = [];

        for ($w = 0; $w < $workers; $w++) {
            $pair = [];
            $res  = socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair);
            if ($res === false) {
                die("socket_create_pair failed (BB)");
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                die("fork failed (BB)");
            }

            if ($pid === 0) {
                // ---- CHILD ----
                socket_close($pair[0]);

                $localBestSteps   = PHP_INT_MAX;
                $localBestPresses = null;

                // Каждому воркеру — задачи j = w, w+workers, ...
                for ($j = $w; $j < $jobCount; $j += $workers) {
                    $job       = $jobs[$j];
                    $remaining = $job['remaining'];
                    $stepsSoFar = $job['stepsSoFar'];
                    $pressesVec = $job['pressesVec'];
                    $startPos   = $job['startPos'];

                    $bestSteps   = $localBestSteps;
                    $bestPresses = $localBestPresses;

                    $this->bnbDfsForMachine(
                        $id,
                        $buttonsInfo,
                        $joltCnt,
                        $remainingCapacity,
                        $globalMaxLen,
                        $startPos,
                        $remaining,
                        $stepsSoFar,
                        $pressesVec,
                        $bestSteps,
                        $bestPresses
                    );

                    if ($bestSteps < $localBestSteps) {
                        $localBestSteps   = $bestSteps;
                        $localBestPresses = $bestPresses;
                    }
                }

                // Отправляем результат родителю
                $payload = json_encode([
                    'bestSteps'   => $localBestSteps,
                    'bestPresses' => $localBestPresses,
                ]);
                socket_write($pair[1], $payload);
                socket_close($pair[1]);
                exit(0);
            }

            // ---- PARENT ----
            socket_close($pair[1]);
            $children[$pid] = $pair[0];
        }

        // ==== 4. Собираем результаты от всех воркеров ====

        foreach ($children as $pid => $sock) {
            pcntl_waitpid($pid, $status);
            $buffer = '';
            while (($chunk = socket_read($sock, 2048)) !== false && $chunk !== '') {
                $buffer .= $chunk;
            }
            socket_close($sock);

            if ($buffer) {
                $decoded = json_decode($buffer, true);
                if ($decoded && isset($decoded['bestSteps']) && $decoded['bestSteps'] < $globalBestSteps) {
                    $globalBestSteps   = $decoded['bestSteps'];
                    $globalBestPresses = $decoded['bestPresses'];
                }
            }
        }

        if ($globalBestSteps === PHP_INT_MAX) {
            die("BB: no solution found for machine $id (parallel)");
        }

        // Дебаг по лучшей комбинации
        $dbg = [];
        if (!is_null($globalBestPresses)) {
            foreach ($globalBestPresses as $pos => $cnt) {
                if ($cnt > 0) {
                    $origId  = $buttonsInfo[$pos]["origId"];
                    $infoStr = $this->getStringFromArray($this->buttons[$id][$origId]);
                    $dbg[]   = "btn#" . $origId . " x " . $cnt . " " . $infoStr;
                }
            }
        }
        if (!empty($dbg)) {
            $this->debug(implode(" | ", $dbg), "BB best combination for machine $id (parallel)", true);
        }

        $this->debug("Machine $id minimal presses (BB parallel) = $globalBestSteps", "BB RESULT", true);
        return $globalBestSteps;
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

    function solveLinearSystemNonNegativeInt(array $A, array $b, int $searchLimit = 30): ?array
    {
        $m = count($A);
        if (!$m) return [];

        $n = count($A[0]);
        if (!$n) return [];

        // 1) Вещественное базовое решение x0 (свободные переменные считаем 0)
        $A_copy = $A;
        $x0 = $this->gaussianParticularSolution($A_copy, $b);
        if (is_null($x0)) {
            return null; // система вообще несовместна
        }

        // 2) RREF(A) и базис ядра A
        $pivotCols = [];
        $R = $this->rrefMatrix($A, $pivotCols);
        $basis = $this->buildNullspaceBasis($R, $pivotCols, $n);

        $numFree = count($basis);

        $bestX   = null;
        $bestSum = PHP_INT_MAX;

        $mLocal = $m;
        $nLocal = $n;
        $A_local = $A;
        $b_local = $b;

        // Вспомогательная функция проверки кандидата
        $checkSolution = function(array $xCandidate) use ($A_local, $b_local, $mLocal, $nLocal, &$bestX, &$bestSum) {
            $nLocal2 = $nLocal;

            // Округляем до целых и проверяем x >= 0
            $xInt = [];
            for ($i = 0; $i < $nLocal2; $i++) {
                $xi = (int)round($xCandidate[$i]);
                if ($xi < 0) {
                    return; // отрицательное число нажатий не допускаем
                }
                $xInt[$i] = $xi;
            }

            // Проверяем A * xInt == b
            for ($row = 0; $row < $mLocal; $row++) {
                $sum = 0;
                for ($col = 0; $col < $nLocal2; $col++) {
                    $sum += $A_local[$row][$col] * $xInt[$col];
                }
                if ($sum !== $b_local[$row]) {
                    return; // не даёт точного jolt-вектора
                }
            }

            $s = array_sum($xInt);
            if ($s < $bestSum) {
                $bestSum = $s;
                $bestX   = $xInt;
            }
        };

        // Если нет свободных переменных — либо единственное решение, либо его нет
        if ($numFree === 0) {
            $this->debug("No free vars, check unique solution", "INT solver", true);
            $checkSolution($x0);
            return $bestX;
        }

        // 3) Перебор по свободным переменным
        if ($numFree === 1) {
            for ($alpha = -$searchLimit; $alpha <= $searchLimit; $alpha++) {
                $xCandidate = $x0;
                for ($i = 0; $i < $n; $i++) {
                    $xCandidate[$i] += $alpha * $basis[0][$i];
                }
                $checkSolution($xCandidate);
            }
        } elseif ($numFree === 2) {
            for ($alpha = -$searchLimit; $alpha <= $searchLimit; $alpha++) {
                for ($beta = -$searchLimit; $beta <= $searchLimit; $beta++) {
                    $xCandidate = $x0;
                    for ($i = 0; $i < $n; $i++) {
                        $xCandidate[$i] += $alpha * $basis[0][$i] + $beta * $basis[1][$i];
                    }
                    $checkSolution($xCandidate);
                }
            }
        } else {
            // Слишком большая размерность ядра — не лезем, что бы не взорваться по времени
            $this->debug("Too many free vars ($numFree), INT solver skipped", "INT solver", true);
            return null;
        }

        return $bestX;
    }
    function rrefMatrix(array $A, array &$pivotCols): array
    {
        $m = count($A);
        if (!$m) {
            $pivotCols = [];
            return $A;
        }
        $n = count($A[0]);

        $EPS = 1e-9;
        $row = 0;
        $pivotCols = [];

        for ($col = 0; $col < $n && $row < $m; $col++) {
            $sel = -1;
            for ($i = $row; $i < $m; $i++) {
                if (abs($A[$i][$col]) > $EPS) {
                    if ($sel === -1 || abs($A[$i][$col]) > abs($A[$sel][$col])) {
                        $sel = $i;
                    }
                }
            }

            if ($sel === -1) {
                continue;
            }

            if ($sel != $row) {
                $tmp     = $A[$sel];
                $A[$sel] = $A[$row];
                $A[$row] = $tmp;
            }

            $div = $A[$row][$col];
            for ($j = $col; $j < $n; $j++) {
                $A[$row][$j] /= $div;
            }

            for ($i = 0; $i < $m; $i++) {
                if ($i == $row) continue;
                $factor = $A[$i][$col];
                if (abs($factor) < $EPS) continue;
                for ($j = $col; $j < $n; $j++) {
                    $A[$i][$j] -= $factor * $A[$row][$j];
                }
            }

            $pivotCols[] = $col;
            $row++;
        }

        return $A;
    }


    function buildNullspaceBasis(array $R, array $pivotCols, int $n): array
    {
        $basis = [];
        $m = count($R);
        $EPS = 1e-8;

        // Какие столбцы являются ведущими
        $pivotByCol = array_fill(0, $n, -1);
        foreach ($pivotCols as $rowIndex => $col) {
            $pivotByCol[$col] = $rowIndex;
        }

        // Свободные столбцы
        $freeCols = [];
        for ($j = 0; $j < $n; $j++) {
            if ($pivotByCol[$j] === -1) {
                $freeCols[] = $j;
            }
        }

        // Для каждого свободного столбца строим один базисный вектор
        foreach ($freeCols as $freeCol) {
            $v = array_fill(0, $n, 0.0);
            $v[$freeCol] = 1.0;

            // Вычисляем значения в ведущих столбцах по уравнениям R * v = 0
            for ($k = count($pivotCols) - 1; $k >= 0; $k--) {
                $col = $pivotCols[$k];
                $sum = 0.0;

                for ($j = $col + 1; $j < $n; $j++) {
                    if (abs($R[$k][$j]) > $EPS) {
                        $sum += $R[$k][$j] * $v[$j];
                    }
                }

                $v[$col] = -$sum; // коэффициент при ведущем элементе (он равен 1)
            }

            $basis[] = $v;
        }

        return $basis;
    }


    function gaussianParticularSolution(array $A, array $b): ?array
    {
        $m = count($A);
        if (!$m) return [];

        $n = count($A[0]);
        if (!$n) return [];

        for ($i = 0; $i < $m; $i++) {
            if (count($A[$i]) != $n) {
                die("gaussianParticularSolution: wrong row length $i");
            }
            $A[$i][] = $b[$i];
        }

        $EPS = 1e-9;
        $row = 0;

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

        // Проверка на противоречия
        for ($i = 0; $i < $m; $i++) {
            $allZero = true;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j]) > $EPS) {
                    $allZero = false;
                    break;
                }
            }
            if ($allZero && abs($A[$i][$n]) > $EPS) {
                return null; // несовместная система
            }
        }

        // Строим решение: свободные переменные = 0
        $x = array_fill(0, $n, 0.0);

        for ($i = 0; $i < $m; $i++) {
            $pivotCol = -1;
            for ($j = 0; $j < $n; $j++) {
                if (abs($A[$i][$j] - 1.0) < 1e-6) {
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

    function solveLinearSystemNonNegativeIntForMachine($id)
    {
        $jolt = array_values(array_map('intval', $this->joltage[$id]));
        $size = count($jolt);

        $K = count($this->buttons[$id]);

        // Матрица A размера [size x K]
        $matrixA = [];
        for ($i = 0; $i < $size; $i++) {
            $matrixA[$i] = array_fill(0, $K, 0.0);
        }

        foreach ($this->buttons[$id] as $buttonId => $button) {
            foreach ($button as $idx) {
                $idx = (int)$idx;
                if ($idx < 0 || $idx >= $size) {
                    die("button $buttonId bad index $idx on machine $id");
                }
                $matrixA[$idx][$buttonId] = 1.0;
            }
        }

        // Пытаемся найти неотрицательное целочисленное решение
        $res = $this->solveLinearSystemNonNegativeInt($matrixA, $jolt, 30);

        if (is_null($res)) {
            $this->debug("No non-negative integer solution by linear method", "Machine: $id", true);
            return null;
        }

        $this->debug(
            $this->getStringFromArrayWithKeys($res),
            "Linear INT solution for machine $id",
            true
        );

        return $res;
    }

    function bnbDfsForMachine(
        $id,
        $buttonsInfo,
        $joltCnt,
        $remainingCapacity,
        $globalMaxLen,
        $pos,
        $remaining,
        $stepsSoFar,
        $pressesVec,
        &$bestSteps,
        &$bestPressesVec
    ) {
        $ceilDiv = function($a, $b) {
            if ($b <= 0) return PHP_INT_MAX;
            if ($a <= 0) return 0;
            return intdiv($a + $b - 1, $b);
        };

        if ($stepsSoFar >= $bestSteps) {
            return;
        }

        foreach ($remaining as $v) {
            if ($v < 0) {
                return;
            }
        }

        $btnCnt = count($buttonsInfo);

        if ($pos == $btnCnt) {
            foreach ($remaining as $v) {
                if ($v !== 0) {
                    return;
                }
            }
            if ($stepsSoFar < $bestSteps) {
                $bestSteps     = $stepsSoFar;
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
                $bestSteps     = $stepsSoFar;
                $bestPressesVec = $pressesVec;
            }
            return;
        }

        $theoreticalMin = $ceilDiv($sumRem, $globalMaxLen);
        if ($stepsSoFar + $theoreticalMin >= $bestSteps) {
            return;
        }

        $info     = $buttonsInfo[$pos];
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

            $newPressesVec          = $pressesVec;
            $newPressesVec[$pos]    = $presses;

            $this->bnbDfsForMachine(
                $id,
                $buttonsInfo,
                $joltCnt,
                $remainingCapacity,
                $globalMaxLen,
                $pos + 1,
                $newRemaining,
                $newSteps,
                $newPressesVec,
                $bestSteps,
                $bestPressesVec
            );
        }
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

