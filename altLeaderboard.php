<?php
class def
{
    const SECONDS_BEFORE_REDOWNLOAD = 15*60;

    protected $mat = [];
    protected $memberData = [];
    protected $allStars = [];
    protected $firstStarsTsByMember = [];

    protected $memberScoreByDay = [];
    protected $membersTotalScore = [];

    protected $dayResultsByTs = [];

    protected $buffer = [];
    protected $customFilename;
    protected $allLinesByDay = [];

    function getFilename()
    {
        $filename = "data/altLeaderboard.json";
        if ($this->customFilename && file_exists($this->customFilename)) {
            $filename = $this->customFilename;
        }
        return $filename;
    }

    function code()
    {
        $this->readArray($this->getFilename());
        $this->processStars();
        $this->processScores();
    }

    function setCustomFilename($filename)
    {
        $this->customFilename = $filename;
        return $this;
    }

    function reDownload()
    {
        $lastUpdated = $this->getLastUpdatedTs();
        if ((time() - $lastUpdated) > self::SECONDS_BEFORE_REDOWNLOAD) {
            $opts = array('http' => array('header'=> "Cookie: session=53616c7465645f5fcd5ba18ffe73974188391c842b866cacf96f59766c7888a1775756d7145bae996430c4fd5846ae86bba0d326a6cb9be01c50300c7a14ccc0 \r\n"));
            $context = stream_context_create($opts);
            $json = file_get_contents("https://adventofcode.com/2024/leaderboard/private/view/1328271.json", false, $context);
            file_put_contents($this->customFilename, $json);
        }
    }

    function getLastUpdatedTs()
    {
        $filename = $this->getFilename();
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
                    $this->allLinesByDay[$day][] = "$memberName +$scoreToAdd  (t2 solved $time after t1 on $timeOn)";
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
                $this->printLine("<h3>#$i ($score) =====> $midName\n</h3>");
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
            $this->memberData[$mId] = [
                'name' => $member['name'],
            ];
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

    function run()
    {
//        addTimer("TOTAL");

        $this->code();

//        showTimers();
    }
}