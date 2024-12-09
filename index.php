<?php
include "altLeaderboard.php";

echo "<h1>AdventOfCode'24 Custom Leaderboard</h1><br/>";

$customFilename = "data/downloadedAdventJson.json";
$Def = new def();
$Def->setCustomFilename($customFilename);

$lastUpdated = $Def->getLastUpdatedTs();
$lastUpdated = date("Y-m-d H:i:s", $lastUpdated);

$Def->reDownload();

echo "<h4>Last Updated: $lastUpdated</h4><br/>";

$Def->run();

foreach ($Def->getBuffer() as $line)
{
    echo $line . "<br/>";
}

?>