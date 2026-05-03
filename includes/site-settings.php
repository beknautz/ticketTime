<?php
declare(strict_types=1);

function getSiteSetting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $file  = STORAGE_PATH . '/settings.json';
        $cache = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    }
    return $cache[$key] ?? $default;
}

function setSiteSetting(string $key, $value): void
{
    $file     = STORAGE_PATH . '/settings.json';
    $settings = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $settings[$key] = $value;
    if (!is_dir(STORAGE_PATH)) {
        mkdir(STORAGE_PATH, 0755, true);
    }
    file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// Returns Bootstrap col-* classes for a given column count (1-4)
function eventColClass(int $cols = 3): string
{
    $map = [1 => 'col-12', 2 => 'col-sm-6', 4 => 'col-sm-6 col-lg-3'];
    return $map[$cols] ?? 'col-sm-6 col-lg-4';  // default 3 columns
}
