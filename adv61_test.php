<?php

class def {


    function run()
    {
        for ($i = 1; $i <= 5; ++$i) {

            $pid = pcntl_fork();



            if (!$pid) {

                sleep(1);

                print "In child $i\n";

                exit($i);

            }

        }



        while (pcntl_waitpid(0, $status) != -1) {

            $status = pcntl_wexitstatus($status);

            echo "Child $status completed\n";

        }

    }
}


?>