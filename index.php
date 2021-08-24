<?php
require_once(__DIR__ . '/vendor/autoload.php');


use Michael\AmazonSellerApi\SynccentricSubstitute;

try {
    
    if (count($argv) < 2)
        throw new Exception('Expecting 1 argument: input file');
    
    $process = new SynccentricSubstitute($argv);
    $process->run();     
    
} catch (Exception $e) {
    echo 'SynccentricSubstitute: '.$e->getMessage();
}

exit();