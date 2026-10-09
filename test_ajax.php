<?php session_start(); $_SESSION['user_id']=1; $_SESSION['user_role']='admin'; $_POST['action']='get_data'; require 'admin/ajax/manage_payouts.php';
