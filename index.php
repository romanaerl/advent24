<?php
include "altLeaderboard.php";

echo "<h1>AdventOfCode'24 Custom Leaderboard</h1><br/>";

echo <<<EOF
This is a version of a leaderboard which counts only a time
between receiving first and second star, which only compares time
spend on a task number two, but therefore is not connected to 
the time participants started their first task. </br></br>
EOF;


$customFilename = "data/downloadedAdventJson.json";
$Def = new def();
$Def->setCustomFilename($customFilename);

$lastUpdated = $Def->getLastUpdatedTs();
$lastUpdated = date("Y-m-d H:i:s", $lastUpdated);

$Def->reDownload();

echo "<b>Last Updated: $lastUpdated</b><br/>";

$Def->run();

foreach ($Def->getBuffer() as $line)
{
    echo $line . "<br/>";
}

?>