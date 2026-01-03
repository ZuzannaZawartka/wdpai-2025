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
        $matches = MockRepository::sportsMatches(null, $selectedSportIds, $levelForFilter, $center, $radiusKm);

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
