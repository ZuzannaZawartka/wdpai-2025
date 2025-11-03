<?php

require_once 'AppController.php';

class UserController extends AppController {

    public function details($id) {
        $this->render("user",['id'=>$id]);
    }
}