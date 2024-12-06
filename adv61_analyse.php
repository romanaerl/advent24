<?php
class def
{
    function run()
    {
        $fcorrect = file("data/adv61-correct.log");
        $fincorrect = file("data/adv61.log");
        rslog("DATA in the Correct Log which is not presented in the Incorrect Log");
        foreach ($fcorrect as $oneLine) {
            if (!in_array($oneLine, $fincorrect)) {
                rslog($oneLine);
            }
        }
        rslog("DATA in the INCorrect Log which is not presented in the Correct Log");
        foreach ($fincorrect as $oneLine) {
            if (!in_array($oneLine, $fcorrect)) {
                rslog($oneLine);
            }
        }
    }
}