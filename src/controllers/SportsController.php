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

        // Level filter: Any | Beginner | Intermediate | Advanced
        $selectedLevel = 'Any';
        if (isset($_GET['level']) && is_string($_GET['level'])) {
            $selectedLevel = trim($_GET['level']);
        }

        // Location filter: loc="lat, lng", radius in km (int)
        $selectedLoc = '';
        $center = null; // [lat, lng]
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

        $sportsGrid = [
            ['icon' => 'sports_soccer', 'name' => 'Soccer'],
            ['icon' => 'sports_basketball', 'name' => 'Basketball'],
            ['icon' => 'sports_tennis', 'name' => 'Tennis'],
            ['icon' => 'directions_run', 'name' => 'Running'],
            ['icon' => 'directions_bike', 'name' => 'Cycling'],
            ['icon' => 'self_improvement', 'name' => 'Yoga'],
            ['icon' => 'sports_volleyball', 'name' => 'Volleyball'],
            ['icon' => 'fitness_center', 'name' => 'Gym'],
            ['icon' => 'more_horiz', 'name' => 'Other'],
        ];

        // Sanitize selected sports to valid names from the grid
        if (!empty($selectedSports)) {
            $valid = array_column($sportsGrid, 'name');
            $selectedSports = array_values(array_intersect($selectedSports, $valid));
        }

        // Sanitize level to known values
        $validLevels = ['Any','Beginner','Intermediate','Advanced'];
        if (!in_array($selectedLevel, $validLevels, true)) {
            $selectedLevel = 'Any';
        }

        $levelForFilter = $selectedLevel !== 'Any' ? $selectedLevel : null;
        $matches = MockRepository::sportsMatches(null, $selectedSports, $levelForFilter, $center, $radiusKm);

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
}
