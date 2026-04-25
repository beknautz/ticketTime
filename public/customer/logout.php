<?php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
customerLogout();
flashMessage('success', 'You have been signed out.');
redirect(SITE_URL . '/public/index.php');
