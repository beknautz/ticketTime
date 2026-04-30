<?php
// Diagnostic test — DELETE AFTER USE
echo "Step 1: PHP running, version " . PHP_VERSION . "<br>";
flush();

echo "Step 2: Loading config...<br>";
flush();
require_once dirname(__DIR__) . '/config/config.php';
echo "Step 3: Config OK, BASE_PATH=" . BASE_PATH . "<br>";
flush();

echo "Step 4: Auth check skipped — testing includes only<br>";
flush();

echo "Step 5: Instantiating Event model...<br>";
flush();
$em = new Event();
echo "Step 6: Event model OK<br>";
flush();

echo "Step 7: getById(3)...<br>";
flush();
$event = $em->getById(3);
echo "Step 8: getById OK, result=" . ($event ? "found" : "not found") . "<br>";
flush();

echo "Step 9: Including admin-header...<br>";
flush();
$pageTitle = 'Diagnostic';
require_once __DIR__ . '/includes/admin-header.php';

echo "<p>Step 10: All includes OK. The crash is in the form HTML itself.</p>";
