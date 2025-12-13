<?php
class Def
{
    protected $isDebug = true;
    protected $isMatrixPrint = true;
    protected $startTs = 0;

//    protected $youTop = -1;
    protected $svrTop = -1;
    protected $dacTop = -1;
    protected $fftTop = -1;
    protected $outTop = -1;
    protected $tops = [];
    protected $names = [];
    protected $namesBack = [];

    protected $topsReverse = [];

    protected $cache = [];
    protected $cacheReverse = [];

    function readInput($filePath)
    {
        $tempRoutes = [];
        $idNew = -1;
        $lines = file($filePath);
        foreach ($lines as $line) {
            $idNew++;
            $parts = explode(':', trim($line));
            $name = trim($parts[0]);

            $this->names[$idNew] = $name;
            if (isset($this->namesBack[$name])) {
                die("Records are not unique names!");
            }
            $this->namesBack[$name] = $idNew;

            $tempRoutes[$idNew] = explode(" ", trim($parts[1]));
        }
        // Now we have all names in $names - we can create ID links
        foreach ($tempRoutes as $id => $names) {
            $idsTo = [];
            foreach ($names as $name) {
                if (!isset($this->namesBack[$name])) {
                    $idNew++;
                    $this->names[$idNew] = $name;
                    $this->namesBack[$name] = $idNew;
                }
                $idsTo[] = $this->namesBack[$name];
            }
            $this->tops[$id] = $idsTo;
        }
//        if (!isset($this->namesBack['you'])) {
//            die("no YOU found");
//        } else {
//            $this->youTop = $this->namesBack['you'];
//        }
        if (!isset($this->namesBack['out'])) {
            die("no OUT found");
        } else {
            $this->outTop = $this->namesBack['out'];
        }
        if (!isset($this->namesBack['svr'])) {
            die("no svr found");
        } else {
            $this->svrTop = $this->namesBack['svr'];
        }
        if (!isset($this->namesBack['dac'])) {
            die("no dac found");
        } else {
            $this->dacTop = $this->namesBack['dac'];
        }
        if (!isset($this->namesBack['fft'])) {
            die("no fft found");
        } else {
            $this->fftTop = $this->namesBack['fft'];
        }


        // Make a reverse tree
        foreach ($this->tops as $id => $routesTo) {
            foreach ($routesTo as $routeTo) {
                if (!isset($this->topsReverse[$routeTo])) {
                    $this->topsReverse[$routeTo] = [];
                }
                $this->topsReverse[$routeTo][] = $id;
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

    function getStringFromArrayWithKeys($arr, $separator = '|')
    {
        $a = [];
        foreach ($arr as $key => $val) {
            if (is_array($val)) $val = $this->getStringFromArray($val, '-');
            $a[] = "k#$key => $val";
        }
        return $separator . implode( '  ', $a) . $separator;
    }

    function printPath($path) {
        $pathstr = '';
        foreach ($path as $id) {
            $pathstr .= " ==> " . $this->names[$id];
        }
        $this->debug($pathstr, 'PathFound', true);
    }

    function findPathSumReverse($startId, $searchId, $visitedLegs = [], $path = [])
    {
        if (empty($visitedLegs)) {
            // Its a first start we reset the cache
            $this->cacheReverse = [];
        }
        if ($startId == $searchId) {
            $this->printPath($path);
            return 1;
        }


        $sum = 0;

        if (!isset($this->topsReverse[$startId])) {
            return 0;
            var_dump($startId);
            die("NO ROUTES");
        }

        foreach ($this->topsReverse[$startId] as $routeFrom) {
            if (isset($visitedLegs[$startId]) && in_array($routeFrom, $visitedLegs[$startId])) {
                // We already have been going this route once
                continue;
            }
            $cached = $this->cacheReverse[$startId][$routeFrom] ?? null;
            if ($cached) {
                $sum += $cached;
                continue;
            }
            $visitedLegs[$startId][] = $routeFrom;
            $newPath = $path; $newPath[] = $routeFrom;
            $nowSum = $this->findPathSumReverse($routeFrom, $searchId, $visitedLegs, $newPath);
            $this->cacheReverse[$startId][$routeFrom] = $nowSum;
            $sum += $nowSum;
        }

        return $sum;
    }


    function findPathSum($startId, $searchId, $visitedLegs = [], $path = [])
    {
        if (empty($visitedLegs)) {
            // Its a first start we reset the cache
            $this->cache = [];
        }
        if ($startId == $searchId) {
            $this->printPath($path);
            return 1;
        }

        // Out, но мы не это сейчас ищем
        if ($startId == $this->outTop) {
            return 0;
        }

        $sum = 0;

        if (!isset($this->tops[$startId])) {

            var_dump($startId);
            die("NO ROUTES");
        }
        foreach ($this->tops[$startId] as $routeTo) {
            if (isset($visitedLegs[$startId]) && in_array($routeTo, $visitedLegs[$startId])) {
                // We already have been going this route once
                continue;
            }
            $cached = $this->cache[$startId][$routeTo] ?? null;
            if ($cached) {
                $sum += $cached;
                continue;
            }
            $visitedLegs[$startId][] = $routeTo;
            $newPath = $path; $newPath[] = $routeTo;
            $nowSum = $this->findPathSum($routeTo, $searchId, $visitedLegs, $newPath);
            $this->cache[$startId][$routeTo] = $nowSum;
            $sum += $nowSum;
        }

        return $sum;
    }

    function code() {

        $this->debug($this->getStringFromArrayWithKeys($this->tops), "Tops", true);
        $this->debug($this->getStringFromArrayWithKeys($this->names), "Names", true);
//        $this->debug($this->youTop, 'YouTOP', true);
        $this->debug($this->outTop, 'OutTOP', true);

        echo "\r\n";

//        $sumSD = $this->findPathSum($this->svrTop, $this->dacTop, [], [$this->svrTop]);
//        $this->debug($sumSD, '$sumSD', true);
            $sumSD = $this->findPathSumReverse($this->dacTop, $this->svrTop, [], [$this->dacTop]);
            $this->debug($sumSD, '$sumSDReverse', true);

        echo "\r\n";
        $sumFD = $this->findPathSum($this->fftTop, $this->dacTop, [], [$this->fftTop]);
        $this->debug($sumFD, '$sumFD', true);
//            $sumFD = $this->findPathSumReverse($this->dacTop, $this->fftTop, [], [$this->dacTop]);
//            $this->debug($sumFD, '$sumFDReverse', true);

        echo "\r\n";
        $sumDF = $this->findPathSum($this->dacTop, $this->fftTop, [], [$this->dacTop]);
        $this->debug($sumDF, '$sumDF', true);
            $sumDF = $this->findPathSumReverse($this->fftTop, $this->dacTop, [], [$this->fftTop]);
            $this->debug($sumDF, '$sumDFReverse', true);

        echo "\r\n";
        $sumDO = $this->findPathSum($this->dacTop, $this->outTop, [], [$this->dacTop]);
        $this->debug($sumDO, '$sumDO', true);
//            $sumDO = $this->findPathSumReverse($this->outTop, $this->dacTop, [], [$this->outTop]);
//            $this->debug($sumDO, '$sumDOReverse', true);

        echo "\r\n";
//        $sumSF = $this->findPathSum($this->svrTop, $this->fftTop,  [], [$this->svrTop]);
//        $this->debug($sumSF, '$sumSF', true);
            $sumSF = $this->findPathSumReverse($this->fftTop, $this->svrTop, [], [$this->fftTop]);
            $this->debug($sumSF, '$sumSFReverse', true);

die();
//
        $sumFO = $this->findPathSum($this->fftTop, $this->outTop);
        $this->debug($sumFO, '$sumFO', true);

        $sum1 = $sumSD * $sumDF * $sumFO;
        $sum2 = $sumSD * $sumFD * $sumDO;

        return $sum2+$sum1;
    }


    function run()
    {
        // Main Execution
        $filePath = "data/25-11-1.example"; $this->isMatrixPrint = true; $this->isDebug = true;
        $filePath = "data/25-11-1.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
//        $filePath = "data/25-11-2.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
//        $filePath = "data/25-11-3.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";
    }


}


ini_set("memory_limit", '32000M');
ini_set("max_execution_time", '60000');
ini_set("max_input_nesting_level", '10000');
$Def = new Def();
$Def->run();

