<?php
class def
{
    const SECONDS_BEFORE_REDOWNLOAD = 15*60;
    const SESSION_ADV_ID="53616c7465645f5fd78c2aa0da89568939d54028ee8cedc0073dfd0c81599688815bba910a51e0541e859c95b0661aa48b608e031e7c7813d8859cef6105086a";
    protected $mat = [];
    protected $memberData = [];
    protected $allStars = [];
    protected $firstStarsTsByMember = [];

    protected $memberScoreByDay = [];
    protected $membersTotalScore = [];

    protected $dayResultsByTs = [];

    protected $startYear = 2015;
    public $endYear = 2025;

    protected $buffer = [];
    protected $customFilename;
    protected $allLinesByDay = [];

    protected $usersWhoSolveAiOnly = [
        '2025' => [
            'Valeriy Kobzar',
            'Alexey Rybak',
        ],
        '2024' => [
            'Alexey Rybak',
        ],
    ];


    function getFilename(int $year)
    {
        $filename = "data/altLeaderboard{YEAR}.json";
        $filename = str_replace("{YEAR}", trim((string) $year), $filename);

        if ($this->customFilename && file_exists($this->customFilename)) {
            $filename = $this->customFilename;
        }
        return $filename;
    }

    function code()
    {
        $this->readArray($this->getFilename($this->getYear()));
        $this->processStars();
        $this->processScores();
    }

    function setCustomFilename($filename)
    {
        $this->customFilename = $filename;
        $this->customFilename = str_replace("{YEAR}", $this->getYear(), $this->customFilename);
        return $this;
    }

    function getSecondsBeforeDownload() {

        if (@$_GET['ILoveThisLeaderboardVeryMuch'] == 1) {
            return 10;
        }

        return self::SECONDS_BEFORE_REDOWNLOAD;
    }

    function reDownload()
    {
        $lastUpdated = $this->getLastUpdatedTs();
        if ((time() - $lastUpdated) > $this->getSecondsBeforeDownload()) {
            $opts = array('http' => array('header'=> "Cookie: session=" . self::SESSION_ADV_ID . " \r\n"));
            $context = stream_context_create($opts);
            $json = file_get_contents("https://adventofcode.com/" . $this->getYear() . "/leaderboard/private/view/1328271.json", false, $context);
            file_put_contents($this->customFilename, $json);
        }
    }

    function getLastUpdatedTs()
    {
        $filename = $this->getFilename($this->getYear());
        return filemtime($filename);
    }

    function printLine($line)
    {
        if (php_sapi_name() == 'cli') {
            print($line . "\n");
        }
        $this->buffer[] = $line;
        return $this;
    }

    function getBuffer()
    {
        return $this->buffer;
    }

    function processStars()
    {
        foreach ($this->allStars as $ts => $star) {
            if ($star["starNumber"] == 1) {
                $this->firstStarsTsByMember[$star['mId']][$star['day']] = $ts;
            } else if ($star["starNumber"] == 2) {
                $day = $star['day'];
                $mId = $star['mId'];
                $this->dayResultsByTs[$day] ?? $this->dayResultsByTs[$day] = [];
                $starSpeed =  $ts - $this->firstStarsTsByMember[$star['mId']][$star['day']];
                $this->dayResultsByTs[$day][$starSpeed] ?? $this->dayResultsByTs[$day][$starSpeed] = [];
                $this->dayResultsByTs[$day][$starSpeed][] = [$mId, $ts];
            }
        }
    }

    function getScoreForDay($day)
    {
        if (empty($this->memberScoreByDay[$day])) {
            $this->memberScoreByDay[$day] = count($this->memberData);
        }
        return $this->memberScoreByDay[$day]--;
    }

    function processScores()
    {
        foreach ($this->dayResultsByTs as $day => $dayResults) {
            ksort($dayResults);
            foreach ($dayResults as $speed => $mids) {
                foreach ($mids as $mid) {
                    $ts = $mid[1];
                    $mid = $mid[0];
                    $scoreToAdd = $this->getScoreForDay($day);
                    $this->memberScoreByDay[$mid] ?? $this->memberScoreByDay[$mid] = [];
                    $this->memberScoreByDay[$mid][$day] = $scoreToAdd;
                    $this->membersTotalScore[$mid] ?? $this->membersTotalScore[$mid] = 0;
                    $this->membersTotalScore[$mid] += $scoreToAdd;

                    $memberName = $this->getPrintMemberName($mid);
                    $days = floor($speed / (60*60*24));
                    $time = "$days days " . date("H:i:s", $speed);
                    $timeOn = date("Y-m-d H:i:s", $ts);
                    $this->allLinesByDay[$day] ?? $this->allLinesByDay[$day] = [];
                    $place = count($this->allLinesByDay[$day]) + 1;
                    $this->allLinesByDay[$day][] = "#$place $memberName +$scoreToAdd  (t2 solved $time after t1 on $timeOn)";
                }
            }
        }

        $reverseScore = [];
        foreach($this->membersTotalScore as $mid => $score) {
            $reverseScore[$score] ?? $reverseScore[$score] = [];
            $reverseScore[$score][] = $mid;
        }

        krsort($reverseScore);

        $i = 1;
        foreach ($reverseScore as $score => $mids) {
            foreach ($mids as $mid) {
                $midName = $this->getPrintMemberName($mid);
                $stars = $this->memberData[$mid]['stars'];
                $this->printLine("<b>#$i ($score) =====> $midName\n ($stars*)</b>");
                $i++;
            }
        }
        krsort($this->allLinesByDay);
        foreach ($this->allLinesByDay as $day => $lines) {
            $this->printLine("<h4><a href='https://adventofcode.com/" . $this->getYear(). "/day/$day' target='_blank'>Day $day:</a></h4>");
            foreach ($lines as $line) {
                $this->printLine($line);
            }
        }
    }

    function getPrintMemberName($mId) {
        return $this->memberData[$mId]['name'] . ($this->isAiMember($this->memberData[$mId]['name']) ? ' [HAI]' : '');
    }

    function getIncludeAiMembers()
    {
        return isset($_GET['includeHAI']);
    }

    function isAiMember($memberName)
    {
        $md5 = md5($memberName);
        $arr = $this->usersWhoSolveAiOnly[$this->getYear()] ?? [];
        return in_array($md5, $arr);
    }


    function readArray($filename)
    {
        // Init array with md5
        foreach ($this->usersWhoSolveAiOnly as $year => $arr) {
            foreach($arr as $nameKey => $name) {
                $this->usersWhoSolveAiOnly[$year][$nameKey] = md5($name);
            }
        }
        $maxStars = 0;
        $this->mat = $mat = json_decode(trim(file_get_contents($filename)), true);
//        var_dump( json_encode(
//        $this->mat,
//        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
//    ), '$this->mat');
        foreach ($mat['members'] as $mId => $member) {
            if (!$this->getIncludeAiMembers() && $this->isAiMember($member['name'])) {
                continue;
            }
            $this->memberData[$mId] = $member;
            if ((int)$member['stars'] > $maxStars) $maxStars = (int)$member['stars'];
            foreach ($member['completion_day_level'] as $day => $stars) {
                $day = (int)$day;
                foreach ($stars as $starNumber => $starData) {
                    $starData['mId'] = $mId;
                    $starData['starNumber'] = (int)$starNumber;
                    $starData['day'] = $day;
                    $this->allStars[(int)$starData['get_star_ts']] = $starData;
                }
            }
        }
        ksort($this->allStars);
    }

    function getValidYears()
    {

        $years = [];
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        // Include current year only if December has started
        $endYear = ($currentMonth >= 12) ? $currentYear : $currentYear - 1;

        return range($this->startYear, $endYear);
    }

    function getYear()
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');
        $currentDay = (int)date('j');

        // If it's November 30th or later, allow the current year
        if ($currentMonth > 11 || ($currentMonth === 11 && $currentDay >= 30)) {
            $this->endYear = $currentYear;
        }

        if (isset($_GET['year']) && in_array((int)$_GET['year'], $this->getValidYears())) {
            $year = (int)$_GET['year'];
        } else {
            $year = $this->endYear;
        }

        return $year;
    }

    function run()
    {
        $this->code();
    }
}
