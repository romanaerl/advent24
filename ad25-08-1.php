<?php
class Def
{
    protected $matrix = [];

    protected $wiresCnt = 0;

    protected $boxes = [];


    protected $boxesWeights = [];

    protected $weights = [];
    protected $weightsAll = [];

    protected $connections = [];

    protected $clusters = [];

    protected $largestClusters2Multiply = 3;

    function addWeight($id1, $id2, $weight)
    {
        $this->boxesWeights[$id1][$id2] = $weight;
        $this->boxesWeights[$id2][$id1] = $weight;

        if (!isset($this->weights[$weight])) $this->weights[$weight] = [];
        $this->weights[$weight][] = [$id1, $id2];

        if (!isset($this->weightsAll[$weight])) $this->weightsAll[$weight] = [];
        $this->weightsAll[$weight][] = [$id1, $id2];
        $this->weightsAll[$weight][] = [$id2, $id1];
    }

    function getId(array $dims)
    {
        return implode(',', $dims);
    }

    function readInput($filePath)
    {

        $lines = file($filePath);
        foreach ($lines as $line) {
            $id = trim($line);
            $dims = explode(',', $id);
            $this->boxes[$id] = $dims;
        }

        foreach ($this->boxes as $id1 => $dim1) {
            foreach ($this->boxes as $id2 => $dim2) {
                if ($id1 == $id2) continue;
                if (empty($this->boxesWeights[$id1][$id2])) {
                    $weight = $this->calcWeight($dim1, $dim2);
//                    var_dump([$weight, $dim1, $dim2]);
                    $this->addWeight($id1, $id2, $weight);
                }
            }
        }

        ksort($this->weights);
    }

    function calcWeight($dim1, $dim2)
    {
//        $sum = 0;
//        for ($i = 0; $i < 3 ; $i ++) {
//            $sum += abs($dim1[$i] - $dim2[$i]);
//        }
//        return $sum;

        $res =  sqrt(
            pow($dim2[0] - $dim1[0], 2) +
            pow($dim2[1] - $dim1[1], 2) +
            pow($dim2[2] - $dim1[2], 2)
        );

        return floor($res * 100000);
    }


    function printMatrix($mat)
    {
        echo "\r\n Matrix: \r\n\r\n";

        foreach ($this->weights as $weight => $pairs) {
            foreach ($pairs as $pair) {
                echo "$weight => $pair[0] : $pair[1] \r\n";
            }
        }

        echo "\r\n\r\n";

        echo "\r\n";
    }

    function mergeClusters()
    {
        foreach ($this->clusters as $cid1 => &$cluster1) {
            foreach ($this->clusters as $cid2 => $cluster2) {
                if ($cid1 == $cid2) continue;
                if (!empty(array_intersect($cluster1['ids'], $cluster2['ids']))) {
//                    var_dump($cid1, $cid2);
//                    var_dump($cluster1['ids'], $cluster2['ids']);
//                    var_dump(array_intersect($cluster1['ids'], $cluster2['ids']));
                    $cluster1['weight'] += $cluster2['weight'];
                    $cluster1['ids'] = array_unique(array_merge($cluster1['ids'], $cluster2['ids']));
                    $cluster1['size'] = count($cluster1['ids']);
                    var_dump($cluster1['ids']);
                    var_dump($cluster1['size']);
                    unset($this->clusters[$cid2]);
                    return true;
                }
            }
        }
        return false;
    }

    function put2Cluster($id1, $id2, $weight)
    {
        $added = false;
        foreach ($this->clusters as $cId => &$cluster) {
            if (in_array($id1, $cluster['ids']) && in_array($id2, $cluster['ids'])) {
                //AlreadyHere
                $added = true;
            } else if (in_array($id1, $cluster['ids'])) {
                // Just Left is here
                $cluster['ids'][] = $id2;
                $cluster['size'] = count($cluster['ids']);
                $added = true;
            } else if (in_array($id2, $cluster['ids'])) {
                // Just Right is here
                $cluster['ids'][] = $id1;
                $cluster['size'] = count($cluster['ids']);
                $added = true;
            }
            if ($added) {
                $cluster['weight'] += $weight;
//                $this->clusters[$cId] = $cluster;
                break;
            }
        }

        if (!$added) {
            $this->clusters[] = [
                'ids' => [$id1, $id2],
                'weight' => $weight,
                'size' => 2,
            ];
        }

        while ($this->mergeClusters()) {
            var_dump(count($this->clusters), 'CLUSTERS');
            var_dump(count($this->boxes), 'BOXES');
            if (count($this->clusters) === 1) {
                var_dump($this->clusters, "LAST CLUSTERD");
                $checkCluster = reset($this->clusters);
                var_dump( count($checkCluster['ids']), "NODES IN CLUSTER");
                if (count($checkCluster['ids']) === count($this->boxes)) {
                    $xyz1 = explode($id1);
                    $xyz2 = explode($id2);
                    var_dump($xyz1[0] * $xyz2[0]);
                    die();
                }
            }
        }

        if (count($this->clusters) === 1) {
            var_dump($this->clusters, "LAST CLUSTERD");
            $checkCluster = reset($this->clusters);
            var_dump( count($checkCluster['ids']), "NODES IN CLUSTER");
            if (count($checkCluster['ids']) === count($this->boxes)) {
                $xyz1 = explode(',', $id1);
                $xyz2 = explode(',', $id2);
                var_dump($xyz1[0] * $xyz2[0]);
                die();
            }
        }

    }

    function calcN()
    {
        $i = 0;
        foreach ($this->weights as $weight => $pairs) {
            foreach ($pairs as $pair) {
                if ($i >= $this->wiresCnt) {
//                    break 2;
                }
                $this->put2Cluster($pair[0], $pair[1], $weight);
                $i++;
            }
        }
        var_dump($i, 'CYCLES');
    }

    function code() {
//        $this->printMatrix($this->boxes);

        $this->calcN();

//        usort($this->clusters, function ($a, $b) {
//            return $a['size'] <=> $b['size']; // spaceship оператор
//        });


//        echo "=================================================\r\n";
//        var_dump($this->clusters);
//        echo "-----------------------------------\r\n";
//        var_dump($this->clusters);
//        echo "=================================================\r\n";


        $sizes = array_column($this->clusters, 'size');
        array_multisort($sizes, SORT_DESC, $this->clusters);

        var_dump($this->clusters);

        $sum = 1;
//        for ($i = 0; $i < $this->largestClusters2Multiply; $i++) {
//            $val = array_shift($this->clusters);
////            var_dump((int)$val['size']);
//            $sum = (int)$val['size'] * $sum;
//            echo $i . '   ' . $val['size'] . " \r\n";
//        }


        return $sum;

    }

    function run()
    {
        // Main Execution
        $filePath = "data/25-08-1.example"; $this->wiresCnt = 10000000000000000;
        $filePath = "data/25-08-1.inp"; $this->wiresCnt = 10000000000000000;
//        $filePath = "data/25-08-2.inp";
        $this->readInput($filePath);

        echo "Result " . json_encode($this->code()) ."\r\n";

    }

}


ini_set("memory_limit", '5120M');
ini_set("max_execution_time", '60000');
ini_set("max_input_nesting_level", '10000');
$Def = new Def();
$Def->run();

