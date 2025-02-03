<?php
class Def
{
    protected $registers = [];
    protected $program = [];

    function readInput($filename)
    {
        $lines = file_get_contents($filename);

        $matches = [];
        preg_match_all("|Register\s([ABC]+)\:\s(\d+)|", $lines, $matches);
        foreach ($matches[1] as $key => $value) {
            $this->registers[$value] = $matches[2][$key];
        }

        $matches = [];
        preg_match_all("|Program: ([,\d]+)|", $lines, $matches);
        $this->program = explode(',', $matches[1][0]);
        array_walk($this->program, function (&$value, $key) { $value = (int)$value;});
    }

    function run()
    {
        // Main Execution
        $filePath = "data/17-1.example";
        $this->readInput($filePath);
        rslog($this->registers, '$this->registers');
    }
}

