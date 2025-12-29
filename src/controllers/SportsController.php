<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class SportsController extends AppController {

    public function index(): void
    {
        $selectedSports = ['Soccer', 'Basketball'];

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

        $matches = MockRepository::sportsMatches();

        $this->render('sports', [
            'pageTitle' => 'SportMatch - Sports',
            'activeNav' => 'sports',
            'selectedSports' => $selectedSports,
            'sportsGrid' => $sportsGrid,
            'matches' => $matches,
        ]);
    }
}
