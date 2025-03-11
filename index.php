<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head>

<link rel="stylesheet" href="scores.css?<?php echo base64_encode(time()); ?>">

<body><?php
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


$customFilename = "data/downloadedAdventJson{YEAR}.json";
$Def = new def();
$Def->setCustomFilename($customFilename);

$lastUpdated = $Def->getLastUpdatedTs();
$lastUpdated = date("Y-m-d H:i:s", $lastUpdated);

$Def->reDownload();

$year_str = "<a href = '?year={YEAR}'>{BLD}{YEAR}{/BLD}</a> ";

$curYear = $Def->getYear();
$years_str = "";
foreach ($Def->getValidYears() as $year) {
    $year_one = $year_str;
    if ($year == $curYear) {
        $year_one = str_replace("{BLD}", "<b>", $year_one);
        $year_one = str_replace("{/BLD}", "</b>", $year_one);
    } else {
        $year_one = "";
    }
    $year_one = str_replace("{YEAR}", trim((string)$year), $year_one);
    $years_str .= $year_one;
}

echo "<h4 width='60%'>YEARS: {$years_str} </h4><br/><br/>";
echo "<h2 width='60%'>YEAR " . $Def->getYear() . "</h2><br/><br/>";
echo "<i>Last Updated: $lastUpdated (updates once in 15m)</i><br/><br/>";

$Def->run();

foreach ($Def->getBuffer() as $line)
{
    echo $line . "<br/>";
}

?>
<script src="scores.js?<?php echo base64_encode(time()); ?>"></script>
</body></html>