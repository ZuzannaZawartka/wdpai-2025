<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

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

        $sportsCatalog = MockRepository::sportsCatalog();
        $sportsGrid = array_map(function($sport) {
            return [
                'id' => $sport['id'],
                'name' => $sport['name'],
                'icon' => $sport['icon']
            ];
        }, array_values($sportsCatalog));

        if (!empty($selectedSports)) {
            $valid = array_column($sportsGrid, 'name');
            $selectedSports = array_values(array_intersect($selectedSports, $valid));
        }
        
        $selectedSportIds = [];
        foreach ($selectedSports as $sportName) {
            foreach ($sportsCatalog as $id => $sport) {
                if ($sport['name'] === $sportName) {
                    $selectedSportIds[] = $id;
                    break;
                }
            }
        }

        $allLevels = MockRepository::levels();
        $validLevels = array_merge(['Any'], array_values($allLevels));
        if (!in_array($selectedLevel, $validLevels, true)) {
            $selectedLevel = 'Any';
        }

        $levelForFilter = $selectedLevel !== 'Any' ? $selectedLevel : null;
        
        // Admin sees all events without filtering
        if ($this->isAdmin()) {
            $matches = $this->getAllEventsForAdmin($selectedSportIds, $levelForFilter, $center, $radiusKm);
        } else {
            $matches = MockRepository::sportsMatches(null, $selectedSportIds, $levelForFilter, $center, $radiusKm);
        }

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

    private function getAllEventsForAdmin(array $selectedSports = [], ?string $level = null, ?array $center = null, ?float $radiusKm = null): array {
        $catalog = MockRepository::sportsCatalog();
        $levels = MockRepository::levels();
        $events = MockRepository::events();
        
        // Filter only by user selections, not by full/past status
        $filtered = array_filter($events, function($ev) use ($selectedSports, $level, $center, $radiusKm) {
            if (!empty($selectedSports)) {
                $sid = $ev['sportId'] ?? null;
                if (!$sid || !in_array($sid, $selectedSports, true)) { return false; }
            }

            if ($level !== null) {
                $lid = $ev['levelId'] ?? null;
                $levels = MockRepository::levels();
                $lname = $lid && isset($levels[$lid]) ? $levels[$lid] : null;
                if ($lname !== $level) { return false; }
            }

            if ($center && $radiusKm !== null) {
                $coords = $ev['coords'] ?? '';
                if (!is_string($coords) || $coords === '') { return false; }
                $parts = array_map('trim', explode(',', $coords));
                if (count($parts) !== 2) { return false; }
                $lat2 = (float)$parts[0];
                $lng2 = (float)$parts[1];
                $dist = $this->distanceKm((float)$center[0], (float)$center[1], $lat2, $lng2);
                if (!is_finite($dist) || $dist > $radiusKm) { return false; }
            }
            return true;
        });
        
        // Sort by date descending (newest first)
        usort($filtered, function($a, $b) {
            $dateA = strtotime($a['isoDate'] ?? '');
            $dateB = strtotime($b['isoDate'] ?? '');
            return $dateB <=> $dateA; // DESC order
        });
        
        $colors = $this->getLevelColors();
        $participants = MockRepository::eventParticipants();
        $uid = $this->getCurrentUserId();
        $now = time();
        
        return array_map(function($ev) use ($levels, $colors, $participants, $uid, $now) {
            $current = count($participants[$ev['id']] ?? []);
            $eventTime = strtotime($ev['isoDate'] ?? '');
            $isPast = $eventTime && $eventTime < $now;

            if ($ev['maxPlayers'] === null) {
                $playersText = $ev['minNeeded'] . '+ Players';
            } elseif ($ev['minNeeded'] === $ev['maxPlayers']) {
                $playersText = $ev['minNeeded'] . ' Players';
            } else {
                $playersText = $current . '/' . $ev['minNeeded'] . '-' . $ev['maxPlayers'] . ' Players';
            }
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'desc' => $ev['desc'],
                'players' => $playersText,
                'level' => $levels[$ev['levelId']],
                'levelColor' => $colors[$ev['levelId']],
                'imageUrl' => $ev['imageUrl'],
                'isUserParticipant' => MockRepository::isUserParticipant($uid, $ev['id']),
                'isFull' => MockRepository::isEventFull($ev['id']),
                'isPast' => $isPast
            ];
        }, array_values($filtered));
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
}
