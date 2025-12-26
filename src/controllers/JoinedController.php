<?php

require_once 'AppController.php';

class JoinedController extends AppController {

    public function index(): void
    {
        // Demo data; replace with actual joined events from DB later
        $joinedMatches = [
            [
                'title' => 'Downtown Pickup Soccer',
                'datetime' => 'Sat, Oct 28 @ 10:00 AM',
                'desc' => 'Friendly 6-a-side game at the park.',
                'players' => '7/12 Players',
                'level' => 'Intermediate',
                'levelColor' => '#eab308',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAj8fVhxLcD3rkNj9ToX2J_1DJsVsezaqFwiLsEiFrS7yuUj0czI7hAVwgKvecmaVHHZV9yZslJGMYLOPiR-Vy3VlSa3Bq2HYJOv2bsHt88OYAasHaOSYSjPNy2NwCQdtWx0p2V9V8awj9eow28g8NtYwlaaBsnKvlfi6Pa2b6gWJajlG0eEmKLZlrrq--dihN7G0E1YUtywAff89iCoh7GTJKxGXwBnzmlzq4gsvqbzarDD6mkwK-xBnBQtOWHv8EcxT6ZPjitz3gr',
            ],
            [
                'title' => 'Evening Basketball Game',
                'datetime' => 'Mon, Oct 30 @ 7:00 PM',
                'desc' => 'Casual 5-on-5 at the community court.',
                'players' => '9/10 Players (Full)',
                'level' => 'Advanced',
                'levelColor' => '#ef4444',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBe16uKjdDqQTnUm_ykH5BsRdAX3yPBfYr8oCgTHijY112a3WvyJrNNM3aNcNMj4_SrCXSAMmpVnsEHLBm5aq_W86yHTcnA9MoOsDKv9iXJVOmYA2RfXyiUxZnqNoH98tIQ89VcCnzmI1SKPJ7GldAB2lP_JvgZaQ4EEVZvKX1pBZJg02w0YS1UO9Baci0ZSOdeZVfKZblMssZRRoET0FmyCaRMKjLtbsgldomY8BGnIJ6cWsm0uwcw0UAYr-Fr9qFSilLUGJsw_LZJ',
            ],
        ];

        $this->render('joined', [
            'pageTitle' => 'SportMatch - Joined Events',
            'activeNav' => 'joined',
            'joinedMatches' => $joinedMatches,
        ]);
    }
}
