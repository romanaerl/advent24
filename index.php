<?php
include "altLeaderboard.php";

echo "<h1>AdventOfCode'24 Custom Leaderboard</h1><br/>";

echo <<<EOF
<div width = 60%>This is a version of a leaderboard which counts only a time
between receiving first and second star (only compares time
spend on a task number two in any certain day). <br/>
The leaderboard therefore is not connected to the time 
participants started their first task, which makes it possible 
to get places even if you can not start at midnight EST. </br></br>
</div>
EOF;


$customFilename = "data/downloadedAdventJson.json";
$Def = new def();
$Def->setCustomFilename($customFilename);

$lastUpdated = $Def->getLastUpdatedTs();
$lastUpdated = date("Y-m-d H:i:s", $lastUpdated);

$Def->reDownload();

echo "<i>Last Updated: $lastUpdated (updates once in 15m)</i><br/><br/>";

$Def->run();

foreach ($Def->getBuffer() as $line)
{
    echo $line . "<br/>";
}

?>