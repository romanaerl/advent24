<?php
class def
{
    protected $blocks = [];

    function code()
    {
        ini_set('memory_limit', '1024M');
//        $this->readArray("data/91.example");
//        $this->readArray("data/911.example");
        $this->readArray("data/91.inp");

        $tmpMat = $this->blocks;
        $this->printArray($this->blocks);

        $this->defrag();


        rslog("===> First recalc");
        $this->printArray($tmpMat);
        $checksum = $this->checksum();
        rslog($checksum, '$checksum');


        // Restore original
        $this->blocks = $tmpMat;
        $checksum2 = $this->checksum();
        rslog($checksum, '$checksum NEW');
        rslog($checksum2, '$checksum OLD');

        rslog("=======>>> SECOND CALC");

        $this->printArray($this->blocks);
        $checksum2 = $this->checksum();
        rslog($checksum2, '$checksum OLD');

        $this->defrag2();
        $this->printArray($this->blocks);
        $newblocks = [];
        foreach ($this->blocks as $block) {
            $newblocks[] = $block;
        }
        $this->blocks = $newblocks;
        $checksum2 = $this->checksum();
        rslog($checksum2, '$checksum NEW');
    }


    function checksum()
    {
        $sum = 0;
        $position_count = 0;
        foreach ($this->blocks as $position => $block)
        {
            if (isset($block['id'])) {
                for($i = 0; $i<$block['size']; $i++) {
//                    rslog($block['id']."*$position_count=" . $block['id'] * ($position_count), '$block[id] * ($position_count)');
                    $sum += $block['id'] * ($position_count);
                    $position_count++;
                }
            } else {
                for($i = 0; $i<$block['size']; $i++) {
                    $position_count++;
                }
            }
        }

        return $sum;
    }

    function findSpaceBlock($size, $maxidx)
    {
        foreach ($this->blocks as $idx => $block)
        {
            if ($idx >= $maxidx) {
                return null;
            }
            if (!isset($block['id']) && $block['size'] >= $size) {
                return [$idx, $block];
            }
        }

    }

    function getMaxBlockId()
    {
        $max = 0;
        foreach ($this->blocks as $one) {
            if ($one['size'] && isset($one['id']) && $one['id'] > $max)
            {
                $max = $one['id'];
            }
        }
        return $max;
    }

    function defrag2()
    {
        $maxId = $this->getMaxBlockId();
        while ($maxId >= 0) {
            $inserts = [];
            $reverseBlocks = array_reverse($this->blocks, true);
            foreach ($reverseBlocks as $revId => $revBlock) {
                if (isset($revBlock['id']) && $revBlock['id'] == $maxId) {
                    [$idx, $block] = $this->findSpaceBlock($revBlock['size'], $revId);
                    if (!is_null($idx)) {
                        $this->blocks[$idx] = $revBlock;
                        $newblock = ["size" => $block["size"] - $revBlock['size']];
                        if ($newblock['size']) {
                            $inserts[$idx][] = $newblock;
                        }
                        unset($this->blocks[$revId]['id']);
                    }
                }
            }

            $maxId--;

            if (!is_null($idx)) {
                $newblocks = [];
                foreach ($this->blocks as $key => $value) {
                    if ($value['size']) {
                        $newblocks[] = $value;
                    }
                    if (!empty($inserts[$key]))
                        foreach ($inserts[$key] as $one) {
                            $newblocks[] = $one;
                        }
                }

                $this->blocks = $newblocks;
            }
        }
    }

    function defrag()
    {
        $inserts = [];

        foreach ($this->blocks as $idx => $block) {
            if (isset($block['id'])) {
                if (!$block['size']) {
                    unset($this->blocks[$idx]);
                }
                $this->blocks[$idx]['processed'] = 1;
            } else {
                if (!$block['size']) {
                    continue;
                }
                $fullfilled = false;
                $iteration = 0;
                while (!$fullfilled) {

                    [$fileBlockIdx, $fileBlock] = $this->getLastFileBlocks();
//                    rslog($fileBlockIdx, '$fileBlockIdx ' . var_export($fileBlock, true));
                    if (is_null($fileBlockIdx)) {
                        break 2;
                    }

                    $newblock = ['processed' =>1];

                    $newblock['id'] = $fileBlock['id'];

                    if ($block['size'] >= $fileBlock['size']) {
                        $newblock['size'] = $fileBlock['size'];
                        // Our free block become smaller
                        $block['size'] -= $newblock['size'];
                        // fileBlock is Totally consumed - removing
                        unset($this->blocks[$fileBlockIdx]);
                        $this->increaseEndFreeSpaces($fileBlock['size']);
                        $fullfilled = !(bool)$block['size'];
                    } else {
                        $newblock['size'] = $block['size'];
                        $fileBlock['size'] -= $block['size'];
                        if ($fileBlock['size']) {
                            $this->blocks[$fileBlockIdx] = $fileBlock;
                        } else {
                            unset($this->blocks[$fileBlockIdx]);
                        }
                        $this->increaseEndFreeSpaces($block['size']);
                        $fullfilled = true;
                    }

                    if ($iteration) {
                        if ($newblock["size"]) {
                            $inserts[$idx][] = $newblock;
                        }
                    } else {
                        $this->blocks[$idx] = $newblock;
                    }
                    $iteration++;
                }
            }
        }

        // Merge inserts;
        $newblocks = [];
        foreach ($this->blocks as $idx => $block) {
            $newblocks[] = $block;
            if (!empty($inserts[$idx])) {
                foreach ($inserts[$idx] as $oneinsert) {
                    $newblocks[] = $oneinsert;
                }
            }
        }

        $this->blocks = $newblocks;
    }

    function increaseEndFreeSpaces($incBy)
    {
        $reverse = array_reverse($this->blocks, true);
        foreach ($reverse as $key => $value) {
            if (!isset($value['id'])) {
                $this->blocks[$key]['size'] += $incBy;
                return;
            }
        }
    }


    function getLastFileBlocks()
    {
        $position = 0;
        $reverse = array_reverse($this->blocks, true);
        foreach ($reverse as $key => $value) {
            if (isset($value['id'])) {
                if ($value['size'] == 0) {
                    unset($this->blocks[$key]);
                    continue;
                }
                if (isset($value['processed'])) {
                    return null;
                } else {
                    return [$key,$value];
                }
            }
            $position += $value['size'];
        }

        return null;
    }

    function printArray($mat)
    {
        rslog("Matrix:");
        $line = "";
        foreach ($mat as $x) {
//            rslog($x, '$x');
            if (isset($x['id'])) {
                $line .= "[{$x['id']}:{$x['size']}]";
            } else {
                if ($x['size'])
                $line .= str_repeat(".", $x['size']);
            }
        }
        print($line);

        $line2 = "";
        foreach ($mat as $x) {
            $line2 .= str_repeat(isset($x['id'])?'X':'.', $x['size']);
        }

        echo "\n";
    }

    function readArray($filename)
    {
        $file = file_get_contents($filename);
        $file_arr = str_split($file);
        $id = 0;
        foreach ($file_arr as $x => $y) {
            $is_block = !(bool)($x % 2);
            if ($is_block) {
                $this->blocks[] = [
                    'id' => $id, // only files have ids
                    'size' => (int)$y,
                ];
                $id++;
            } else {
                $this->blocks[] = [
                    'size' => (int)$y,
                ];
            }
        }
    }

    function run()
    {
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}