<?php

namespace TimurFlush\Queue;

use Phalcon\Di;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\DiInterface;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;

final class Worker implements EventsAwareInterface, InjectionAwareInterface
{
    /**
     * @var null|\Phalcon\Events\ManagerInterface
     */
    protected $_eventsManager = null;

    /**
     * @var null|\Phalcon\DiInterface
     */
    protected $_dependencyInjector = null;

    /**
     * @const string
     */
    const STATUS_SUCCESS = 'success';

    /**
     * @const string
     */
    const STATUS_FAILED = 'failed';


    protected $paused = false;

    /**
     * @var bool
     */
    protected $needQuit = false;

    /**
     * @var int
     */
    protected $_memoryLimit = 128;

    /**
     * @var int
     */
    protected $_jobsHandled = 0;

    /**
     * @var int
     */
    protected $_stopAfterHandledJobs = 100;

    /**
     * Worker constructor.
     *
     * @param DiInterface|null $dependencyInjector
     * @throws Exception
     */
    public function __construct(\Phalcon\DiInterface $dependencyInjector = null)
    {
        if ($dependencyInjector === null) {
            $dependencyInjector = Di::getDefault();
        }

        if ($dependencyInjector === null) {
            throw new Exception('A dependency injector container is required to obtain the services related to the queue worker.');
        }

        $this->_dependencyInjector = $dependencyInjector;

        $eventsManager = $this->_dependencyInjector->getShared('eventsManager');
        if ($eventsManager === null) {
            throw new Exception('An events manager is required to obtain the services related to the Job.');
        }

        $this->_eventsManager = $eventsManager;

        return $this;
    }

    /**
     *
     * @param ManagerInterface $eventsManager
     * @return Worker
     */
    public function setEventsManager(ManagerInterface $eventsManager): Worker
    {
        $this->_eventsManager = $eventsManager;
        return $this;
    }

    /**
     *
     * @return ManagerInterface
     */
    public function getEventsManager()
    {
        return $this->_eventsManager;
    }

    /**
     *
     * @param DiInterface $dependencyInjector
     * @return Worker
     */
    public function setDI(\Phalcon\DiInterface $dependencyInjector): Worker
    {
        $this->_dependencyInjector = $dependencyInjector;
        return $this;
    }

    /**
     *
     * @return \Phalcon\DiInterface
     */
    public function getDI()
    {
        return $this->_dependencyInjector;
    }

    /**
     *
     * @param int $attempts
     * @return Worker
     */
    public function setStopAfterHandledJobs(int $attempts = 100): Worker
    {
        $this->_stopAfterHandledJobs = $attempts;
        return $this;
    }

    /**
     *
     * @return int
     */
    public function getStopAfterHandledJobs(): int
    {
        return $this->_stopAfterHandledJobs;
    }

    /**
     *
     * @param int $megabytes
     * @return Worker
     */
    public function setMemoryLimit(int $megabytes = 128): Worker
    {
        $this->_memoryLimit = $megabytes;
        return $this;
    }

    /**
     *
     * @return int
     */
    public function getMemoryLimit()
    {
        return $this->_memoryLimit;
    }

    /**
     *
     * @return Worker
     */
    protected function incrementJobsHandled(): Worker
    {
        $this->_jobsHandled++;
        return $this;
    }

    /**
     * Processes the queue.
     *
     * @param $class
     * @param string|null $connectionService
     * @throws Exception
     */
    public function processing($class, string $connectionService = null): void
    {
        if (!$this->isCli()) {
            throw new Exception('The processing method cannot be called in a non-cli environment');
        }

        if ($this->isSupportPcntlSignals()) {
            $this->listenPcntlSignals();
        }

        $eventsManager = $this->getEventsManager();

        /**
         * @var $job Job
         */
        $firstJob = $this->validateJob($class, $connectionService);

        $autoPush = $firstJob->getAutoPush();
        if ($autoPush !== false) {
            if ($firstJob->getTotalJobsInQueue() == 0) {
                if (!$firstJob->send()) {
                    throw new Exception(
                        sprintf(
                            'Failed to send the job %s in queue %s by auto-push.',
                            $firstJob->getJobName(),
                            $firstJob->getFullQueueName()
                        )
                    );
                }
            }

        }

        if ($eventsManager->fire('jobWorker:beforeHandlingQueue', $firstJob, null, true) !== false) {
            while (true) {
                while($this->paused) {
                    $this->sleep(1);
                }

                $job = $firstJob->getNextJob();

                if ($this->isJob($job)) {

                    if ($eventsManager->fire('jobWorker:beforeHandlingJob', $job, null, true) !== false) {

                        if ($this->isSupportPcntlSignals()) {
                            $this->registerTimeoutHandler($job);
                        }

                        while (true) {
                            if ($job->getAttempts() > 1) {
                                $eventsManager->fire('jobWorker:afterTrying', $job);
                            }

                            $eventsManager->fire('jobWorker:startJobHandling', $job);

                            $time = -microtime(true);

                            $handle = $job->handle();

                            $time += microtime(true);

                            $eventsManager->fire(
                                'jobWorker:endJobHandling',
                                $job,
                                [
                                    'time' => $time,
                                    'status' => ($handle) ? self::STATUS_SUCCESS : self::STATUS_FAILED
                                ]
                            );

                            $job->incrementAttempt();

                            if ($handle) {
                                if ($autoPush !== false) {
                                    $job->release($autoPush);
                                    break;
                                }
                            } else {
                                if (!$job->isExceededAttempts()) {
                                    if ($eventsManager->fire('jobWorker:beforeTrying', $job, null, true) !== false) {
                                        $this->sleep(
                                            $job->getAttemptDelay()
                                        );
                                        continue;
                                    }
                                }
                            }

                            $job->delete();
                            break;
                        }

                        $this->incrementJobsHandled();
                        if ($this->isSupportPcntlSignals()) {
                            $this->resetTimeoutHandler();
                        }
                        $eventsManager->fire('jobWorker:afterHandlingJob', $job);
                    }
                }

                $this->stopIfNecessary();
                $this->sleep(1); //for economy resources of the server.
            }
        }
    }

    /**
     * Resets the timeout handler.
     *
     * @return void
     */
    protected function resetTimeoutHandler()
    {
        pcntl_signal(SIGALRM, function () {});
        pcntl_alarm(0);
    }

    /**
     * Registers the timeout handler.
     *
     * @param JobInterface|null $job
     * @return void
     */
    protected function registerTimeoutHandler(?JobInterface $job = null): void
    {
        pcntl_signal(SIGALRM, function () {
            $this->kill(true, 1);
        });

        pcntl_alarm(
            max(($job) ? $job->getTtr() : 0, 0)
        );
    }

    /**
     * Kills the current process.
     *
     * @param bool $posix
     * @param int $status
     * @return void
     */
    protected function kill(bool $posix = true, int $status = null): void
    {
        $this->_eventsManager->fire('jobWorker:stop', $this);

        if (extension_loaded('posix') && $posix) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Determine if extension pcntl are supported.
     *
     * @return bool
     */
    protected function isSupportPcntlSignals()
    {
        return extension_loaded('pcntl');
    }


    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenPcntlSignals(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function () {
            $this->needQuit = true;
        });

        pcntl_signal(SIGUSR2, function () {
            $this->paused = true;
        });

        pcntl_signal(SIGCONT, function () {
            $this->paused = false;
        });
    }

    /**
     *
     * @return bool
     */
    protected function stopIfNecessary(): void
    {
        if ($this->needQuit === true) {
            $this->kill(true);
        }

        if ($this->isMemoryExceeded()) {
            $this->kill(false, 12);
        }

        if ($this->_jobsHandled >= $this->_stopAfterHandledJobs) {
            $this->kill(false);
        }
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @return bool
     */
    protected function isMemoryExceeded()
    {
        return (memory_get_usage() / 1024 / 1024) >= $this->_memoryLimit;
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param int|double $seconds
     * @return void
     */
    protected function sleep($seconds)
    {
        if ($seconds == 0) {
            return;
        } else if ($seconds >= 1) {
            sleep($seconds);
        } else {
            usleep($seconds * 1000000);
        }
    }

    /**
     * Check the class or the object $job belonging to JobInterface interface and returns an object.
     *
     * @param string|JobInterface $class String of class or object instance of JobInterface.
     * @param null|string $connectionService The name of the connection service in the container if you want to specify a name other than queue.
     * @return JobInterface
     * @throws Exception
     */
    protected function validateJob($class, string $connectionService = null): JobInterface
    {
        if ($connectionService === null) {
            $connectionService = $this->_dependencyInjector->getShared('queue');
        } else {
            $connectionService = $this->_dependencyInjector->getShared($connectionService);
        }

        if ($connectionService === null) {
            throw new Exception('No connection service found for the work of the worker.');
        } else if (!$this->isConnectionService($connectionService)) {
            throw new Exception('Invalid connection service provided.');
        }

        switch (true) {
            case is_object($class):

                if (!$this->isJob($class)) {
                    throw new Exception('The passed object is not the successor of the JobInterface interface.');
                }

                return $class;
                break;
            case is_string($class):

                if (!class_exists($class)) {
                    throw new Exception(
                        sprintf(
                            'The class name argument contains an unknown class: %s.',
                            $class)
                    );
                }

                /**
                 * @var $job JobInterface
                 */
                $job = new $class($this->_dependencyInjector, $connectionService);
                if (!$this->isJob($job)) {
                    throw new Exception('The apple class is not the successor of the JobInterface.');
                }

                return $job;
                break;
            default:
                throw new Exception('The class name must be an object or a class name.');
        }
    }

    /**
     * Determines whether the $job inherits the AdapterInterface.
     *
     * @param $service
     * @return bool
     */
    protected function isConnectionService($service): bool
    {
        return $service instanceof AdapterInterface;
    }

    /**
     * Determines whether the $job inherits the JobInterface.
     *
     * @param $job
     * @return bool
     */
    protected function isJob($job): bool
    {
        return $job instanceof JobInterface;
    }

    /**
     * Defines the execution environment.
     *
     * @return bool
     */
    protected function isCli()
    {
        return php_sapi_name() === 'cli';
    }
}