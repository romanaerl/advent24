<?php
class def
{
    protected $matrix = [];
    protected $initPosX = 0;
    protected $initPosY = 0;
    protected $initDirection = 0;
    protected $posX = 0;
    protected $posY = 0;
    protected $direction = 0; // 0,1,2,3 = up, right, down, left
    protected $visited = [];
    protected $obstacles = [];
    protected $visualMatrix = [];


    function run()
    {
//        $this->readArray("data/61.example");
        $this->readArray("data/61.inp");
        $this->visualMatrix = $this->matrix;
        $cnt = $this->walkNcount();






        rslog("========================================= SECOND RUN");
//        die();

        $matrixBackup = $this->matrix;
        // removing initial position
        $visited = $this->visited;
//        rslog($visited, '$visited');
        $count = 0;
        $i = 0;
        foreach ($visited as $x => $val) {
            foreach ($val as $y => $dirs) {
                $i++;
                foreach ($dirs as $dir) {
//                    rslog($dir, '$dir');
//                    $this->visualMatrix[$x][$y] = str_replace(['0','1','2','3'], ['u','r','d','l'], $dir);
                    $this->visited = [];
                    $this->matrix = $matrixBackup;
                    $this->posX = $this->initPosX;
                    $this->posY = $this->initPosY;
                    $this->direction = $this->initDirection;
                    // obstacle
                    [$obstRes, $obstX, $obstY] = $this->putObstacle($x,$y,$dir);
                    if ($obstRes) {
                        rslog("$x $y $dir $i $cnt", '"$x $y $dir OBSTACLE [current/total]"');
                        if (true === $this->walkNcount(true)) {
                            $this->obstacles[$obstX][$obstY] = 1;
                            rslog("LOOPED", '"LOOPED"');
                            $count++;
                        }
                    }
                }
            }
//            break;
        }

//        rslog($this->obstacles, '$this->obstacles');
        foreach ($this->obstacles as $x => $val) {
            foreach ($val as $y => $v) {
                $this->visualMatrix[$x][$y] = 'O';
            }
        }

        $this->visualMatrix[$this->initPosX][$this->initPosY] = '^';
        foreach ($this->visualMatrix as $x) {
            $line = '';
            foreach ($x as $y) {
                $y = str_replace([0,1], ['.', '#'], (string)$y);
                $line .= $y;
            }
            echo $line . "\r\n";
        }

        rslog($cnt, '$count RESULT1');
        rslog($count, '$count RESULT2');

    }

    function putObstacle($x, $y, $dir) {
        if ($dir == 0) {
            $x--;
        } elseif ($dir == 1) {
            $y++;
        } elseif ($dir == 2) {
            $x++;
        } elseif ($dir == 3) {
            $y--;
        }
        if (!empty($this->obstacles[$x][$y])) {
            return false;
        }
        $this->matrix[$x][$y] = 1;
        return [true, $x, $y];
    }


    function walkNcount($returnTRUEIfLooped = false) {
        $count = 1; // we already took one space
        while(true) {
            [$result, $isLooped] = $this->goForward();
            if ($result) {
                if (empty($this->visited[$this->posX][$this->posY])) {
                    $count++;
                    $this->visited[$this->posX][$this->posY][] = $this->direction;
//                    rslog("$this->posX $this->posY $this->direction", '"$this->posX $this->posY $this->direction VISITED"');
                } else {
                    if (!in_array($this->direction, $this->visited[$this->posX][$this->posY])) {
                        $this->visited[$this->posX][$this->posY][] = $this->direction;
//                        rslog("$this->posX $this->posY $this->direction", '"$this->posX $this->posY $this->direction VISITED"');
                    }
                }
                if ($returnTRUEIfLooped && $isLooped) {
                    return true;
                } else if ($isLooped) {
                    return $count;
                }
            } elseif ($result === false) {
                // change direction
                $this->direction++;
//                rslog($this->direction, '$this->direction');
                if ($this->direction > 3) $this->direction = 0;
            } elseif (is_null($result)) {
//                rslog("BREAK", '"BREAK"');
                break;
            }
        }
        return $count;
    }

    function goForward() {
        $newPosX = $this->posX;
        $newPosY = $this->posY;
        $size = count($this->matrix);
        switch ($this->direction) {
            case 0:
                $newPosX--;
                break;
            case 1:
                $newPosY++;
                break;
            case 2:
                $newPosX++;
                break;
            case 3:
                $newPosY--;
                break;
        }
//        rslog($this->posX, '$this->posX');
//        rslog($this->posY, '$this->posY');
//        rslog($newPosX, '$newPosX');
//        rslog($newPosY, '$newPosY');
//        rslog($this->direction, '$this->direction');
        if ($newPosX < 0 || $newPosY < 0 || $newPosX >= $size || $newPosY >= $size) {
            // out of boundaries
            return [null, false];
        }
        if ($this->matrix[$newPosX][$newPosY] == 0) {
            $this->posX = $newPosX;
            $this->posY = $newPosY;
            $isLooped = $this->isLooped();
//            rslog($isLooped, '$isLooped');
            return [true, $isLooped];
        }

        return [false, false];
    }

    function isLooped()
    {
        foreach ($this->visited as $x => $val) {
            foreach ($val as $y => $val2) {
                foreach ($val2 as $dir) {
                    if (
                        $x == $this->posX
                        && $y == $this->posY
                        && $dir == $this->direction
                    ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function readArray($filename)
    {
        $file = file($filename);
        $x = 0;
        $y = 0;
        foreach ($file as $one) {
            $vars = str_split($one);
            foreach ($vars as $var) {
                if ($var == "^") {
                    $this->posX = $this->initPosX = $x;
                    $this->posY = $this->initPosY = $y;
                    $this->visited[$x][$y][]=$this->direction;
                }
                $this->matrix[$x][$y] = ($var == "#") ? 1 : 0;
                $y++;
            }
            $y = 0;
            $x++;
        }
    }
}