<?php
declare(strict_types=1);

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'trodeo');
define('DB_USER',    getenv('DB_USER')    ?: 'trodeo');
define('DB_PASS',    getenv('DB_PASS')    ?: 'Access$1');
define('DB_CHARSET', 'utf8mb4');
