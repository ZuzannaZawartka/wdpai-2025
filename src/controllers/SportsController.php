<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/SportsRepository.php';
require_once __DIR__ . '/../repository/EventRepository.php';

class SportsController extends AppController {

    public function index(): void
    {
        // Accept preselected sports from query: ?sport=Tennis or ?sport[]=Tennis&sport[]=Running
        $selectedSports = [];
        if (isset($_GET['sport'])) {
            if (is_array($_GET['sport'])) {
                $selectedSports = array_map(function($v) { return trim((string)$v); }, $_GET['sport']);
            } elseif (is_string($_GET['sport'])) {
                // allow comma-separated list
                $selectedSports = array_map('trim', explode(',', $_GET['sport']));
            }
        }

        $selectedLevel = 'Any';
        if (isset($_GET['level']) && is_string($_GET['level'])) {
            $selectedLevel = trim($_GET['level']);
        }

        $selectedLoc = '';
        $center = null;
        $radiusKm = null;
        if (isset($_GET['loc']) && is_string($_GET['loc'])) {
            $selectedLoc = trim($_GET['loc']);
            if (preg_match('/^\s*(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $selectedLoc, $m)) {
                $lat = (float)$m[1];
                $lng = (float)$m[2];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    $center = [$lat, $lng];
                    if (isset($_GET['radius'])) {
                        $r = (float)$_GET['radius'];
                        if ($r > 0 && $r <= 1000) { $radiusKm = $r; }
                    }
                }
            }
        }

        $sportsRepo = new SportsRepository();
        $sportsFromDb = $sportsRepo->getAllSports();
        $sportsGrid = array_map(function($row) {
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'icon' => $this->sportIcon((string)$row['name'])
            ];
        }, $sportsFromDb);

        if (!empty($selectedSports)) {
            $valid = array_column($sportsGrid, 'name');
            $selectedSports = array_values(array_intersect($selectedSports, $valid));
        }
        
        $selectedSportIds = [];
        foreach ($selectedSports as $sportName) {
            foreach ($sportsGrid as $s) {
                if ($s['name'] === $sportName) { $selectedSportIds[] = (int)$s['id']; break; }
            }
        }

        $levels = $sportsRepo->getAllLevels();
        $levelMap = [];
        foreach ($levels as $l) { $levelMap[(int)$l['id']] = (string)$l['name']; }
        $validLevels = array_merge(['Any'], array_values($levelMap));
        if (!in_array($selectedLevel, $validLevels, true)) {
            $selectedLevel = 'Any';
        }

        $levelForFilter = $selectedLevel !== 'Any' ? $selectedLevel : null;
        
        $eventsRepo = new EventRepository();
        $rows = $this->isAdmin() ? $eventsRepo->getAllForListing(true) : $eventsRepo->getAllForListing(false);
        $now = time();
        $colors = $this->getLevelColors();

        $filtered = array_filter($rows, function($ev) use ($selectedSportIds, $levelForFilter, $center, $radiusKm, $eventsRepo) {
            if (!empty($selectedSportIds)) {
                $sid = (int)($ev['sport_id'] ?? 0);
                if ($sid === 0 || !in_array($sid, $selectedSportIds, true)) { return false; }
            }
            if ($levelForFilter !== null) {
                $lname = (string)($ev['level_name'] ?? '');
                if ($lname !== $levelForFilter) { return false; }
            }
            if ($center && $radiusKm !== null) {
                $lat2 = isset($ev['latitude']) ? (float)$ev['latitude'] : null;
                $lng2 = isset($ev['longitude']) ? (float)$ev['longitude'] : null;
                if ($lat2 === null || $lng2 === null) { return false; }
                $dist = $this->distanceKm((float)$center[0], (float)$center[1], $lat2, $lng2);
                if (!is_finite($dist) || $dist > $radiusKm) { return false; }
            }
            if (!$this->isAdmin()) {
                // For non-admin: filter out full events too
                $eid = (int)$ev['id'];
                if ($eventsRepo->isEventFull($eid)) { return false; }
            }
            return true;
        });

        // Map to view model
        $matches = array_map(function($ev) use ($levelMap, $colors, $now) {
            $current = (int)($ev['current_players'] ?? 0);
            $min = (int)($ev['min_needed'] ?? 0);
            $max = (int)($ev['max_players'] ?? 0);
            if ($max === 0) {
                $playersText = $min . '+ Players';
            } elseif ($min === $max) {
                $playersText = $min . ' Players';
            } else {
                $playersText = $current . '/' . $min . '-' . $max . ' Players';
            }
            $levelName = (string)($ev['level_name'] ?? 'Intermediate');
            // try id mapping color by reverse lookup
            $levelColor = '#eab308';
            foreach ($levelMap as $lid => $lname) {
                if ($lname === $levelName) { $levelColor = $colors[$lid] ?? '#eab308'; break; }
            }
            $ts = strtotime((string)$ev['start_time']);
            $isPast = $ts ? ($ts < $now) : false;
            return [
                'id' => (int)$ev['id'],
                'title' => (string)$ev['title'],
                'datetime' => $ts ? date('D, M j, g:i A', $ts) : 'TBD',
                'desc' => (string)($ev['description'] ?? ''),
                'players' => $playersText,
                'level' => $levelName,
                'levelColor' => $levelColor,
                'imageUrl' => (string)($ev['image_url'] ?? ''),
                'isPast' => $this->isAdmin() ? $isPast : false
            ];
        }, array_values($filtered));

        $this->render('sports', [
            'pageTitle' => 'SportMatch - Sports',
            'activeNav' => 'sports',
            'selectedSports' => $selectedSports,
            'sportsGrid' => $sportsGrid,
            'matches' => $matches,
            'selectedLevel' => $selectedLevel,
            'selectedLoc' => $selectedLoc,
            'radiusKm' => $radiusKm,
        ]);
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    private function getLevelColors(): array {
        return [
            1 => '#22c55e',
            2 => '#eab308',
            3 => '#ef4444',
        ];
    }

    private function sportIcon(string $name): string {
        switch ($name) {
            case 'Soccer': return '⚽';
            case 'Basketball': return '🏀';
            case 'Tennis': return '🎾';
            case 'Running': return '🏃';
            case 'Cycling': return '🚴';
            default: return '🏅';
        }
    }
}
