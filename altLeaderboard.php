<?php
class def
{
    const SECONDS_BEFORE_REDOWNLOAD = 15*60;
    const SESSION_ADV_ID = "53616c7465645f5fb8d9d6e0704d61132ea33b5f037ff2ce52c41f6d36ce40c0ddaa4351669b3fe97f63ede47c6c8c195b3f785dda737ec5f3f0b8a4d63d0fab";
//    const SESSION_ADV_ID = "53616c7465645f5fcd5ba18ffe73974188391c842b866cacf96f59766c7888a1775756d7145bae996430c4fd5846ae86bba0d326a6cb9be01c50300c7a14ccc0";

    protected $mat = [];
    protected $memberData = [];
    protected $allStars = [];
    protected $firstStarsTsByMember = [];

    protected $memberScoreByDay = [];
    protected $membersTotalScore = [];

    protected $dayResultsByTs = [];

    protected $startYear = 2015;
    public $endYear = 2024;

    protected $buffer = [];
    protected $customFilename;
    protected $allLinesByDay = [];


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

    function reDownload()
    {
        $lastUpdated = $this->getLastUpdatedTs();
        if ((time() - $lastUpdated) > self::SECONDS_BEFORE_REDOWNLOAD) {
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

                    $memberName = $this->memberData[$mid]['name'];
                    $time = date("H:i:s", $speed);
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
                $midName = $this->memberData[$mid]['name'];
                $stars = $this->memberData[$mid]['stars'];
                $this->printLine("<b>#$i ($score) =====> $midName\n ($stars*)</b>");
                $i++;
            }
        }
        krsort($this->allLinesByDay);
        foreach ($this->allLinesByDay as $day => $lines) {
            $this->printLine("<h4>Day $day:</h4>");
            foreach ($lines as $line) {
                $this->printLine($line);
            }
        }
    }

    function readArray($filename)
    {
        $maxStars = 0;
        $this->mat = $mat = json_decode(trim(file_get_contents($filename)), true);
        foreach ($mat['members'] as $mId => $member) {
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
        for ($i = $this->startYear; $i <= $this->endYear; $i++) {
            $years[] = (int)$i;
        }

        return $years;
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
