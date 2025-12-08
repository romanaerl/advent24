<?php
class Def
{
    private $numbers = [];
    private $ranges = [];
    private $original_ranges = [];

    function readInput($filePath)
    {

        ini_set("memory_limit", '5120M');
        $lines = file($filePath);

        foreach ($lines as $line) {
            if (!strlen(trim($line))) {
                continue;
            }
            $s = explode('-', trim($line));
            if (empty($s[1])) {
                $this->numbers[] = (int)$s[0];
            } else {
                $this->ranges[] = [(int)$s[0], (int)$s[1]];
            }
        }

//        $this->original_ranges = $this->ranges;


    }

    function find_n_merge($new_range, $excludeRangeId)
    {
        $found = false;

        foreach ($this->ranges as $rangeId => $range) {
            if ($rangeId == $excludeRangeId) {
                // exclude self
                continue;
            }
            if ($new_range[0] >= $range[0] && $new_range[0] <= $range[1]) {
                $found = true;
            }
            if ($new_range[1] <= $range[1] && $new_range[1] >= $range[0]) {
                $found = true;
            }
            if ($new_range[0] <= $range[0] && $new_range[1] >= $range[1]) {
                $found = true;
            }
            if ($range[0] <= $new_range[0] && $range[1] >= $new_range[1]) {
                $found = true;
            }


            if ($found) {
                $res = [];
                $res[0] = min($range[0], $new_range[0]);
                $res[1] = max($range[1], $new_range[1]);
//                if ($range[0] == $res[0] && $range[1] == $res[1]) {
//                    // Already processed
//                    $found = false;
//                    continue;
//                }
                $this->ranges[$rangeId] = $res;
                return true;
            }
        }

        return false;
    }

    function runMerges() {

        $totalMerges = 0;
//        foreach ($this->original_ranges as $key=>$unknown) {
//            if ($this->find_n_merge($unknown, -1)) {
////                return 1;
//                $totalMerges++;
//            }
//        }

        foreach ($this->ranges as $key => $value) {
            if ($key == 0) continue; // exclude self
            if ($this->find_n_merge($value, $key)) {
                unset($this->ranges[$key]); // remove self
                return $totalMerges + 1;
            }
        }
        return $totalMerges;
    }


    function code() {
        $ranges_ids = [];
        $new_ranges  =[];
        $ranges_affected_cnt = [];

        foreach ($this->numbers as $one) {
            foreach ($this->ranges as $rangeI => $range) {
                if ($one >= $range[0] && $one <= $range[1]) {
                    // runs for EACH found range
                    $ranges_ids[$rangeI] = ($ranges_ids[$rangeI] ?? 0) + 1;
                }
            }
        }

//        // Clean ranges, put aside originals
//        foreach ($this->ranges as $rangeI => $range) {
//            if (empty($ranges_ids[$rangeI])) {
//                $this->original_ranges[] = $range;
//                unset($this->ranges[$rangeI]);
//            }
//        }


//
//
//        // clean, leaving only affected ranges
//        foreach ($this->ranges as $key=>$one) {
//            if (empty($ranges_ids[$key])) {
//                unset($this->ranges[$key]);
//            }
//        }


        while ($this->runMerges()) {
            continue;
        }

//        var_dump($this->ranges);
//
        usort($this->ranges, function($a, $b) {
            return $a[0] <=> $b[0]; // compare first elements
        });

        var_dump($this->ranges);

        $count = 0;
        foreach ($this->ranges as $one) {
            $count += $one[1] - $one[0] + 1;
        }

        return $count;
    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-05-1.example";
        $filePath = "data/25-05-1.inp";
//        $filePath = "data/25-05-2.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";


//        var_dump(PHP_INT_MAX);
//        var_dump(PHP_INT_SIZE);

    }

}


$Def = new Def();
$Def->run();

