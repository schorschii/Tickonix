<?php

session_name('TICKONIX_SESSID_'.md5(__DIR__));
session_start();

if(!isset($_SESSION['login'])) {
	redirectToLogin();
}
if(!isset($_SESSION['installation']) || $_SESSION['installation'] != dirname(__FILE__)) {
	error_log('auth error: installation not matching '.dirname(__FILE__));
	redirectToLogin();
}

function redirectToLogin() {
	header('Location: login.php');
	die();
}
