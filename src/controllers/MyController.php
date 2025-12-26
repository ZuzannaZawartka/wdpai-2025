<?php

require_once 'AppController.php';

class MyController extends AppController {

    public function index(): void
    {
        // Demo data; replace with DB-backed events the user created
        $myEvents = [
            [
                'title' => 'Downtown Pickup Soccer',
                'datetime' => 'Sat, Oct 28 @ 10:00 AM',
                'players' => '6/12 Players',
                'level' => 'Intermediate',
                'levelColor' => '#eab308',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAj8fVhxLcD3rkNj9ToX2J_1DJsVsezaqFwiLsEiFrS7yuUj0czI7hAVwgKvecmaVHHZV9yZslJGMYLOPiR-Vy3VlSa3Bq2HYJOv2bsHt88OYAasHaOSYSjPNy2NwCQdtWx0p2V9V8awj9eow28g8NtYwlaaBsnKvlfi6Pa2b6gWJajlG0eEmKLZlrrq--dihN7G0E1YUtywAff89iCoh7GTJKxGXwBnzmlzq4gsvqbzarDD6mkwK-xBnBQtOWHv8EcxT6ZPjitz3gr',
            ],
            [
                'title' => "Beginner's Tennis Rally",
                'datetime' => 'Tue, Oct 31 @ 3:00 PM',
                'players' => '2/4 Players',
                'level' => 'Beginner',
                'levelColor' => '#22c55e',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDAliQuWSJnNz5fQaD5yjYqnRBMUFVHIRDC-qHsGhPqZFqRcE-81pIQjf7sogA2jCk0ZkC7zxdBjLZKjhBrWIK3GSIqmIqJy45XnxpFAJ6skwDiwTaN1P9uQP2xrruO3nnNLm5leS8WnyZa6tvd-OX2DmVCKvdX0EbI37gYqPT-DeIteI-xkdsdarWvCX_JylvvLNlYXtMj6mt042sRtEWOA-vBzIxI_ngcFSLOqJW6q2yzTHmqZD4IcJkP10jFlXDNfQIHQZ1gusyJ',
            ],
        ];

        $this->render('my', [
            'pageTitle' => 'SportMatch - My Events',
            'activeNav' => 'my',
            'myEvents' => $myEvents,
        ]);
    }
}
