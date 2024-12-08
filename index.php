<?php
include "altLeaderboard.php";

echo "<h1>AdventOfCode'24 Custom Leaderboard</h1>";

$Def = new def();
$Def->run();

foreach ($Def->getBuffer() as $line)
{
    echo $line . "<br/>";
}

?>