<?php


// @todo: put these into proper packages and use composer
// require('./vendor/autoload.php');
require('../magedbgen/DbGen.php');
require('../mageinterfacegen/InterfaceGen.php');
require('MageGen.php');

if (php_sapi_name() != 'cli') {
    die("This is only supported from the command-line.\n");
}

$moduleName = $vendor = $destination = null;

$skipConfirmation = false;

if (count($argv) > 2) {
    $arguments = array_slice($argv, 2);
    foreach ($arguments as $argument) {
        if ($argument == '--no-verify') {
            $skipConfirmation = true;
            continue;
        }
        if (preg_match('#\-\-module\-name\=(.+)#i', $argument, $match)) {
            $moduleName = $match[1];
            continue;
        }
        if (preg_match('#\-\-vendor\=(.+)#i', $argument, $match)) {
            $vendor = $match[1];
            continue;
        }
        if (preg_match('#\-\-destination\=(.+)#i', $argument, $match)) {
            $destination = $match[1];
        }
    }
}

echo "File to process: ";
if (!empty($argv[1])) {
    $filePath = $argv[1];
    echo $filePath . "\n";
} else {
    $handle   = fopen("php://stdin", "r");
    $filePath = trim(fgets($handle));
    fclose($handle);
}

echo "Module name: ";
if (!empty($moduleName)) {
    echo $moduleName . "\n";
} else {
    $handle     = fopen("php://stdin", "r");
    $moduleName = trim(fgets($handle));
    fclose($handle);
}

echo "Vendor name: ";
if (!empty($vendor)) {
    echo $vendor . "\n";
} else {
    $handle = fopen("php://stdin", "r");
    $vendor = trim(fgets($handle));
    fclose($handle);
}

try {
    $mageGen = new MageGen\MageGen($filePath, $vendor, $moduleName, $destination);
} catch (Exception $e) {
    die("[Failed] " . $e->getMessage() . "\n");
}

echo 'New files will be generated in ' . $mageGen->getDestination() . "\n";
if (!$skipConfirmation) {
    echo 'Type YES to continue: ';
    $handle = fopen("php://stdin", "r");
    $input  = strtolower(trim(fgets($handle)));
    fclose($handle);
    if ($input !== 'yes' && $input !== 'y') {
        die("Yes not entered. Exiting.\n");
    }
}

echo "Beginning...\n";

try {
    $mageGen->beginRun();
} catch (Exception $e) {
    die("[Failed] " . $e->getMessage() . "\n");
}

echo "Done.\n\n";
