<?php

require_once 'AppController.php';

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

        $matches = [
            [
                'title' => 'Downtown Pickup Soccer',
                'datetime' => 'Sat, Oct 28 @ 10:00 AM',
                'desc' => 'Friendly 6-a-side game at the park.',
                'players' => '6/12 Players',
                'level' => 'Intermediate',
                'levelColor' => '#eab308',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAj8fVhxLcD3rkNj9ToX2J_1DJsVsezaqFwiLsEiFrS7yuUj0czI7hAVwgKvecmaVHHZV9yZslJGMYLOPiR-Vy3VlSa3Bq2HYJOv2bsHt88OYAasHaOSYSjPNy2NwCQdtWx0p2V9V8awj9eow28g8NtYwlaaBsnKvlfi6Pa2b6gWJajlG0eEmKLZlrrq--dihN7G0E1YUtywAff89iCoh7GTJKxGXwBnzmlzq4gsvqbzarDD6mkwK-xBnBQtOWHv8EcxT6ZPjitz3gr',
            ],
            [
                'title' => 'Evening Basketball Game',
                'datetime' => 'Mon, Oct 30 @ 7:00 PM',
                'desc' => 'Casual 5-on-5 at the community court.',
                'players' => '8/10 Players',
                'level' => 'Advanced',
                'levelColor' => '#ef4444',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBe16uKjdDqQTnUm_ykH5BsRdAX3yPBfYr8oCgTHijY112a3WvyJrNNM3aNcNMj4_SrCXSAMmpVnsEHLBm5aq_W86yHTcnA9MoOsDKv9iXJVOmYA2RfXyiUxZnqNoH98tIQ89VcCnzmI1SKPJ7GldAB2lP_JvgZaQ4EEVZvKX1pBZJg02w0YS1UO9Baci0ZSOdeZVfKZblMssZRRoET0FmyCaRMKjLtbsgldomY8BGnIJ6cWsm0uwcw0UAYr-Fr9qFSilLUGJsw_LZJ',
            ],
            [
                'title' => "Beginner's Tennis Rally",
                'datetime' => 'Tue, Oct 31 @ 3:00 PM',
                'desc' => 'Just for fun, no experience needed!',
                'players' => '2/4 Players',
                'level' => 'Beginner',
                'levelColor' => '#22c55e',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDAliQuWSJnNz5fQaD5yjYqnRBMUFVHIRDC-qHsGhPqZFqRcE-81pIQjf7sogA2jCk0ZkC7zxdBjLZKjhBrWIK3GSIqmIqJy45XnxpFAJ6skwDiwTaN1P9uQP2xrruO3nnNLm5leS8WnyZa6tvd-OX2DmVCKvdX0EbI37gYqPT-DeIteI-xkdsdarWvCX_JylvvLNlYXtMj6mt042sRtEWOA-vBzIxI_ngcFSLOqJW6q2yzTHmqZD4IcJkP10jFlXDNfQIHQZ1gusyJ',
            ],
        ];

        $this->render('sports', [
            'pageTitle' => 'SportMatch - Sports',
            'activeNav' => 'sports',
            'selectedSports' => $selectedSports,
            'sportsGrid' => $sportsGrid,
            'matches' => $matches,
        ]);
    }
}
