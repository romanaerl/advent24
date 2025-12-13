<?php
class Def
{
    protected $isDebug = true;
    protected $isMatrixPrint = true;
    protected $startTs = 0;

    protected $figures = [];
    protected $spaces = [];

    function readInput($filePath)
    {
        $lines = file($filePath);
        $figureStarted = false;
        $figureTmp = [
            'w' => 0,
            'h' => 0,
            'lines' => [],
            'space' => 0,
        ];
        $newFigure = [];
        foreach ($lines as $line) {
            // Empty line
            if (strlen(trim($line)) == 0) {
                if ($figureStarted) {
                    $figureStarted = false;
                    $this->figures[] = $newFigure;
                }
                continue;
            };

            $res = [];
            if (preg_match("|^\d:|", $line, $res)) {
                // figure headers
                $newFigure = $figureTmp;
                $figureStarted = true;
            } else if (preg_match('|^[\.#]+|', $line, $res)) {
                // figure lines
                $l = str_split(str_replace(['#', '.'], [1, 0], trim($line)));
                $l = array_map('intval', $l);
                $spacesTaken = array_sum($l);
                if ($spacesTaken) $newFigure['h']++;
                if ($newFigure['w'] < $spacesTaken) {
                    $newFigure['w'] = $spacesTaken;
                }
                $newFigure['space'] += $spacesTaken;
                $newFigure['lines'][] = $l;
            } else if (preg_match("|^(?<w>\d+)x(?<h>\d+):\s(?<values>[\d\s]+)|", $line, $res)) {
                // spaces and amount of figures
                $stArr = explode(' ', $res['values']);
                $stArr = array_map('intval', $stArr);
                $this->spaces[] = [
                    'w' => (int)$res['w'],
                    'h' => (int)$res['h'],
                    'size' => (int)$res['h'] * (int)$res['v'],
                    'figures' => $stArr,
                ];
            }
        }
    }

    function debug($val, $desc = '', $isSimple = true, $force = false)
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

    function code() {

        var_dump($this->figures);
        var_dump($this->spaces);


        $allSpacesIds = array_keys($this->spaces);

        // Filters coming one by one
        $allSpacesIds = $this->filterObviousNos($allSpacesIds);

        $this->debug(count($this->spaces), "Total spaces provided");
        $this->debug(count($allSpacesIds), "Total spaces survived the filtering");

        return count($allSpacesIds);
    }


    function figuresWillNotFitTheAreaBySpace($id)
    {
        $this->debug($id, "Checking", true);
        $space = $this->spaces[$id];

        if (($space['w'] < 3) || ($space['h'] < 3)) {
            // Figures will not fit even in one row
            return true;
        }

        $spaceArea = $space['w'] * $space['h'];
        $this->debug("Area {$space['w']}x{$space['h']}=$spaceArea", '', true);
        $figAreaSum = 0;
        foreach ($space['figures'] as $figId => $figCount) {
            $figAreaSum += $this->figures[$figId]['space'] * $figCount;
        }

        $this->debug("SpaceArea: $spaceArea, figuresArea: $figAreaSum", 'Checking spaceId: '. $id, true);
        $this->debug($figAreaSum, "All figures areas summarised", true);
        if ($spaceArea < $figAreaSum) {
            // Obviously there is not enough space for these figures
            return true;
        }

        // otherwise they fit (possibly)
        return false;
    }

    function filterObviousNos($allSpacesIds)
    {
        foreach ($allSpacesIds as $spaceKey => $spaceId) {

            // Will not fit the area even if no spaces will be left
            if ($this->figuresWillNotFitTheAreaBySpace($spaceId)) {
                $this->debug($spaceId, "This area is filtered");
                unset($allSpacesIds[$spaceKey]);
                continue;
            }
            $this->debug($spaceId, "This area is NOT filtered");


        }
        return $allSpacesIds;
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-12-1.example"; $this->isMatrixPrint = true; $this->isDebug = true;
        $filePath = "data/25-12-1.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
//        $filePath = "data/25-12-2.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
//        $filePath = "data/25-12-3.inp"; $this->isMatrixPrint = true; $this->isDebug = true;
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";
    }


}


ini_set("memory_limit", '32000M');
ini_set("max_execution_time", '60000');
ini_set("max_input_nesting_level", '10000');
$Def = new Def();
$Def->run();

