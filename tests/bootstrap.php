<?php
/**
 * Find the auto loader file
 */
$locations = [
    __DIR__ . '/../',
    __DIR__ . '/../../',
    __DIR__ . '/../../../',
    __DIR__ . '/../../../../',
];


foreach ($locations as $location) {

    $file = $location . "vendor/autoload.php";

    if (file_exists($file)) {
        $loader = require_once $file;
        $loader->addPsr4('Voryx\\Tests\\', __DIR__);
        break;
    }
}
