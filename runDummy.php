<?php
class DummyScript
{
    private static $argv = [];

    public static function run($argv)
    {
        self::$argv = $argv;
        self::init();
        if (!isset($argv[1])) {
            echo "should add function/file name to run\r\n";
        } elseif (file_exists($argv[1] . ".php")) {
            include $argv[1] . ".php";
            $Def = new def();
            $Def->run();
        } elseif (method_exists('DummyScript', $argv[1])) {
            call_user_func(array('DummyScript', $argv[1]), $argv);
        } else {
            echo "should add function/file name to run\r\n";
        }
    }

    public static function init() {}

    private static function getArgument($index = 1, $default = false)
    {
        $index++;
        return isset(self::$argv[$index]) ? self::$argv[$index] : $default;
    }

    public static function demo()
    {
        $s = "XYYYYXYYYYYYX";
        $are = [];
        preg_match_all("|YYYY|mis", $s, $are);
        rslog($are, '$are');
        rslog("DEMO", '"DEMO"');
    }
}

$hrTimers = [];

function addTimer($name, $unique = false)
{
    global $hrTimers;
    $ms = hrtime(true);
    if ($unique) {
        $hrTimers[$name] ?? $hrTimers[$name] = '';
    }
    $hrTimers[] = [$ms,$name];
}

function showTimers()
{
    global $hrTimers;

    foreach ($hrTimers as $timer) {

    }
}

function rslog($var = '', $comment = '') {
    echo "$comment :";
    var_dump($var);
}

if (!defined('RS_NO_RUN_DUMMY')) {
    DummyScript::run($argv);
}
