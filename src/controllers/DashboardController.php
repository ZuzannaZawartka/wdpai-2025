<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../repository/CardsRepository.php';
require_once __DIR__ . '/../repository/MockRepository.php';

class DashboardController extends AppController {

    private $cardRepository;

    public function __construct() {
        $this->cardRepository = new CardsRepository();
    }


    public function index(?int $id =null) {
        // Redirect admin to sports page
        if ($this->isAdmin()) {
            header('Location: /sports');
            exit();
        }
        
        $currentUserId = $this->getCurrentUserId();
        $upcomingEvents = MockRepository::upcomingEvents($currentUserId ?? null);
        $favouriteSports = MockRepository::favouriteSports($currentUserId ?? null);
        $suggestions = MockRepository::suggestions($currentUserId ?? null);

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