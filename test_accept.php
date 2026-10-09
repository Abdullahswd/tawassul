<?php
session_start();
$_POST = ['action' => 'accept_assignment', 'order_id' => 1];
$_SESSION['academic_id'] = 1;
require 'academics/ajax/handler.php';
