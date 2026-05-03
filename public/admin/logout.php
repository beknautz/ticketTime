<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
adminLogout();
redirect(SITE_URL . '/admin/login.php');
