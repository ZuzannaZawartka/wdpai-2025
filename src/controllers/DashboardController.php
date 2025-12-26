<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/CardsRepository.php';

class DashboardController extends AppController {

    private $cardRepository;

    public function __construct() {
        $this->cardRepository = new CardsRepository();
    }


    public function index(?int $id =null) {
        // Static demo data to be replaced with DB queries later
        $upcomingEvents = [
            [
                'title' => 'Soccer Game',
                'dateText' => 'Sat, Aug 10, 10:00 AM',
                'location' => 'Central Park',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCmy1gDuoaQ2m54IbrTok-N86x8u72EZg6LQ3qJwaunla_-e47P5owxKHeGKhZ0xP5Hn2pGMrreNUKzZ3omSHLAV2gFavO_wlfgwum4qiL7s6af1c2weF9FbNF0FbtXcLUXN33_tiZrYcG0OccgfqDBhFxCOxnQXhOwbaR40d8Od_JEGe2qawcNe0vcB4XyoLxybcdgN4TPNROZDBvL08zsbepu8en_-veyNyddbclkPiZ2JoNIyP32DXYsd1SDWsnxCgBBtsuH0iHN'
            ],
            [
                'title' => 'Basketball Pickup',
                'dateText' => 'Sun, Aug 11, 2:00 PM',
                'location' => 'City Arena',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuATJMSJMJqt5eU4JmCJzZzOW_IX3fUkoW8x71Ydl6rGfaUVy7HFVD1Gi_k--HsroBXxFGUq8pjuVI3G2ZQTSB7D--eG6AQ2BHGx4B_BR7gqCss-O6jjPzgs0DmYOgAYAL0WnluZE5txNzh6570GXjjer5IRapb2qtTfmCiPHOSF3Wi_mTQZN-9M-3RVnx-sR1Eh4GoJxs4g34I4Ls_bIidJ2fyTVwYqhN82KAVKRh4xSdio--0vVwbKDO779K74mmDRkovstP5CZMfp'
            ],
        ];

        $favouriteSports = [
            [ 'icon' => '🏃', 'name' => 'Running', 'nearbyText' => '12 events nearby' ],
            [ 'icon' => '🚴', 'name' => 'Cycling', 'nearbyText' => '8 events nearby' ],
            [ 'icon' => '🎾', 'name' => 'Tennis', 'nearbyText' => '15 events nearby' ],
        ];

        $suggestions = [
            [
                'title' => 'Weekly Running Club',
                'sport' => 'Running',
                'distanceText' => 'River Trail (2km away)',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDBN4upX-dD3omhsHhGASurq2bQv8P05U2al7p0_6T8GJyXb5vvHwuEKLE3Aaxx7VnVURvJVTJ4Q48_-sqClJDzrxi-nIv6sxsgqt1RslKEjWhqlVBzAoWogk_ucJPIEhvMzYlC1EGUkmVPlR1hB_uUH19RZ7asJL23s5cjan5wOrxbnTetcPbDtGB22HyRDk457AEzNom2w09rRj7wOnzVqp3tIaD2bIUTvQniNLyaDTBDQ9l2ijS1GC94aEGV6_-cQOPgWgejHCDk',
                'cta' => 'Join Match'
            ],
            [
                'title' => 'Mountain Biking',
                'sport' => 'Cycling',
                'distanceText' => 'Hillside Trails (5km away)',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAGCAqyYqW55OTcWiaX-4FMNzaR4kmyE7LETdG4imPjzKV1LqEy2y-2ss6bNhcaKGLv8F4nwsnQgtVnVS6Ilw-3MTsx25gYt3iWfG0fLhcwYLLRCVQPkyGcrMO7EUGLsrqDm8WyD3lQ0sej8vCpbCpIfjGQhJG7H4q5LhTk1yXRWtBZBi3ygcCpMXElit8RGUIULLKvIz5t6rAjtSouRQz9jwb5NAUyNnqsVmttyXAvIeFFBKwPgjMMKLCPyNb1QxZ-WD-Ft3xgI5SI',
                'cta' => 'Join Match'
            ],
            [
                'title' => 'Tennis Tournament',
                'sport' => 'Tennis',
                'distanceText' => 'Community Courts (1km away)',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOdQng9qujhWHOAXiXeGemeWZRiFWZLM6d-1bjEItXNzJR0DhrmEE3_ulCj4x5pIVliCBsKTuhCQpliQh4pAMqx6_Z-v7sc2KH_73rlEE9UUDPFVS03PWZB3xYNwjq-46u37VzCUCGbQuPVczvaHEgCU00M4QyMmHU0R3PeD8sV2scqSwdvoCQvct8G8-AM6CKpnKXxyv6YN4-KxwqyjEf-_J1F0AZI2cAykEm9CrnlqqM02Ub-lgo7RX1YuHNMPKP-MLGtlXRzQ5W',
                'cta' => 'Join Match'
            ],
        ];

        $this->render("dashboard",  [
            'activeNav' => 'dashboard',
            'pageTitle' => 'SportMatch Dashboard',
            'upcomingEvents' => $upcomingEvents,
            'favouriteSports' => $favouriteSports,
            'suggestions' => $suggestions
        ]);
    }

    public function search()  {

        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

        if ($contentType !== "application/json") {
            http_response_code(415);
            echo json_encode(["status"=> 415,"message" => "Content type must be: application/json"]);
            return;
        }

        if ($this->isPost() ) {
            http_response_code(405);
            echo json_encode(["status"=> 405,"message" => "Method not allowed"]);
            return;
        }
        header('Content-Type: application/json');
        http_response_code(200);

        $content = trim(file_get_contents("php://input"));
        $decoded = json_decode($content, true);



        $cards =$this->cardRepository->getCardsByTitle($decoded['search']);
        echo json_encode($cards);
    }

}