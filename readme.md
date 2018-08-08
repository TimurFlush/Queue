[![Build Status](https://travis-ci.org/TimurFlush/Queue.svg?branch=master)](https://travis-ci.org/TimurFlush/Queue)
[![Coverage Status](https://coveralls.io/repos/github/TimurFlush/Queue/badge.svg?branch=master)](https://coveralls.io/github/TimurFlush/Queue?branch=master)
# Queue
Component provides a unified API across a variety of different queue services. 
Queues allow you to defer the processing of a time consuming task, such as 
sending an e-mail, until a later time, thus drastically speeding up the web 
requests to your application.

# Using

Note that the EventsManager service must be registered in the 
dependency container and must return the \Phalcon\Events\Manager
interface instance

In Dependency Injector:
```

use TimurFlush\Queue\Adapter\Beanstalk as BeanstalkQueue;

$di->setShared('queue', function() {
    /* By default, Beanstalk uses IP 127.0.0.1 and Port 11300, 
     * but you can easily override them by passing new values 
     * to the constructor.
     */
    return new BeanstalkQueue(
        [
            'host' => '127.0.0.1',
            'port' => '11300',
            //'persistent' => true, //if necessary  
        ]
    );
});
```



## Author
Timur Flush
## Requirements
PHP ^7.2.0

Phalcon ^3.4.0
## Version
1.0.0 Beta 1
## License
BSD-3-License