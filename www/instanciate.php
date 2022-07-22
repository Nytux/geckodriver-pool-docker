<?php

require_once( __DIR__.'/includes/SeleniumPool.php' );
require_once( __DIR__.'/includes/SeleniumInstance.php' );
require_once( __DIR__.'/includes/SeleniumTools.php' );
require_once( __DIR__.'/includes/storages/SeleniumStorageDisk.php' );

$storage = new SeleniumStorageDisk('/var/www/sessions');

$n = getenv("GECKOPOOL_N_INSTANCES");
$n = intval($n ?: 4);

$start = 4444;

$ports = [];
for ( $i = 0 ; $i < $n ; $i++ ) {
    $ports[] = $i+$start;
}

$pool = new SeleniumPool( 
    $storage, 
    $ports,
    function ( $row ) {
        file_put_contents( 'php://stdout', $row."\n" );
    }
);

$pool->setAcquireDeadTime(
    intval(getenv("GECKOPOOL_ACQUIRE_DEADTIME") ?: 60)
);

$pool->setMaxFailures(
    intval(getenv("GECKOPOOL_MAX_FAILURES") ?: 1)
);

