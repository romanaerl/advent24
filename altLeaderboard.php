<?php
class def
{
    protected $mat = [];
    protected $memberData = [];
    protected $allStars = [];
    protected $firstStarsTsByMember = [];

    protected $memberScoreByDay = [];
    protected $membersTotalScore = [];

    protected $dayResultsByTs = [];

    function code()
    {
        $this->readArray("data/altLeaderboard.json");
        $this->processStars();
        $this->processScores();
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
                    print("day $day ==> $scoreToAdd goes to $memberName (t2 solved $time after t1 on $timeOn)\n");
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
                print("   Place #$i with Total Score $score goes to =====> $midName\n");
                $i++;
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
        addTimer("TOTAL");

        $this->code();

        showTimers();
    }
}