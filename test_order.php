<?php
require_once __DIR__ . '/config/functions.php';
$order = getOrderById(1);
print_r($order);
