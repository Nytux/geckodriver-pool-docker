<?php


require( __DIR__.'/../instanciate.php' );

$pool->setHeadless(true);
$pool->autoTests();
