<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Mockery as m;

$di = new \Phalcon\Di\FactoryDefault();

$di->setShared('queue', function(){
    return m::mock(\TimurFlush\Queue\Adapter\Beanstalk::class);
});
$di->setShared('eventsManager', function(){
    $eventsManager = new \Phalcon\Events\Manager();

    return $eventsManager;
});

