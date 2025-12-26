<?php

require_once 'AppController.php';

class UserController extends AppController {

    public function details($id) {
        $this->render("user",['id'=>$id]);
    }

    public function profile() {
        $this->render("profile", [
            'pageTitle' => 'SportMatch - Profile',
            'activeNav' => 'profile',
            'user' => [
                'firstName' => '',
                'lastName' => '',
                'email' => '',
                'location' => '',
                'sports' => [],
                'avatar' => '/public/images/avatar-placeholder.png'
            ]
        ]);
    }
}