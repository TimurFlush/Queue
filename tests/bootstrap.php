<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$di = new \Phalcon\Di\FactoryDefault();

$di->setShared('queue', function(){
    return new \TimurFlush\Queue\Adapter\Blackhole();
});
$di->setShared('eventsManager', function(){
    $eventsManager = new \Phalcon\Events\Manager();

    return $eventsManager;
});

