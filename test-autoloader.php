<?php
// Test file to check if our autoloader works
require_once __DIR__ . '/optimus-courier.php';

if (class_exists('OptimusCourier\\Dependencies\\setasign\\Fpdi\\Fpdi')) {
    echo "SUCCESS: FPDI class loaded successfully\n";
} else {
    echo "ERROR: FPDI class could not be loaded\n";
}

if (class_exists('OptimusCourier_FPDF')) {
    echo "SUCCESS: FPDF class loaded successfully\n";
} else {
    echo "ERROR: FPDF class could not be loaded\n";
}
