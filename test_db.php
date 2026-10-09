<?php require 'config/functions.php'; $db = db(); $q = $db->query('DESCRIBE orders')->fetchAll(PDO::FETCH_ASSOC); print_r($q);
