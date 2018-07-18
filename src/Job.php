<?php

namespace TimurFlush\Queue;

use Phalcon\Di;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\DiInterface;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Text;
use Phalcon\ValidationInterface;

abstract class Job implements JobInterface, InjectionAwareInterface, EventsAwareInterface, ConnectionAwareInterface
{
    /**
     * @var \Phalcon\DiInterface
     */
    protected $_dependencyInjector = null;

    /**
     * @var \Phalcon\Queue\Beanstalk
     */
    protected $_connectionService = null;

    /**
     * @var string
     */
    protected $_queueName = 'default';

    /**
     * @var string
     */
    protected $_queuePrefix = '';

    /**
     * @var int
     */
    protected $_attempts = 0;

    /**
     * @var int
     */
    protected $_ttr = 60;

    /**
     * @var int
     */
    protected $_delay = 0;

    /**
     * @var int
     */
    protected $_priority = 100;

    /**
     * @var string
     */
    protected $_jobName = null;

    /**
     * @var array
     */
    protected $_messages = [];

    /**
     * @var int
     */
    protected $_jobId = null;

    /**
     * @var bool
     */
    protected $_autoPush = false;

    /**
     * @var \Phalcon\Events\ManagerInterface
     */
    protected $_eventsManager = null;

    /**
     * @var int
     */
    protected $_autoPushEverySeconds = 0;

    /**
     * @var int
     */
    protected $_maxAttemptsToDelete = 1;

    /**
     * @var int
     */
    protected $_attemptDelay = 0;

    /**
     * @var bool
     */
    protected $_released = false;

    /**
     * @var bool
     */
    protected $_deleted = false;

    /**
     * @var null|string
     */
    protected $_operationMade = null;

    /**
     * @const int
     */
    const OP_SEND = 1;

    /**
     * @const int
     */
    const OP_DELETE = 2;

    /**
     * @const int
     */
    const OP_RELEASE = 3;

    /**
     * Job constructor.
     *
     * @param \Phalcon\DiInterface $dependencyInjector Custom dependency injector.
     * @param string $connectionService Name of connection service in dependency injector.
     * @throws Exception
     */
    final public function __construct(DiInterface $dependencyInjector = null, string $connectionService = null)
    {
        if ($dependencyInjector === null) {
            $dependencyInjector = Di::getDefault();
        }

        if ($dependencyInjector === null) {
            throw new Exception('A dependency injector container is required to obtain the services related to the Job.');
        }

        $this->_dependencyInjector = $dependencyInjector;

        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }

        if ($connectionService === null) {
            $connectionService = $dependencyInjector->getShared('queue');
        } else {
            $connectionService = $dependencyInjector->getShared($connectionService);
        }

        if (!($connectionService instanceof AdapterInterface)) {
            throw new Exception("The injected connection service is not valid");
        }

        $this->_connectionService = $connectionService;

        $eventsManager = $dependencyInjector->getShared('eventsManager');
        if (!($eventsManager instanceof ManagerInterface)) {
            throw new Exception('An events manager is required to obtain the services related to the Job.');
        }

        $this->_eventsManager = $eventsManager;

        if ($this->_jobName === null) {
            $array = explode('\\', get_class($this));
            $this->_jobName = $array[count($array) - 1];
        }
    }

    /**
     *
     * @param DiInterface $dependencyInjector
     */
    public function setDI(DiInterface $dependencyInjector)
    {
        $this->_dependencyInjector = $dependencyInjector;
    }

    /**
     *
     * @return DiInterface
     */
    public function getDI()
    {
        return $this->_dependencyInjector;
    }

    /**
     *
     * @param ManagerInterface $eventsManager
     * @return JobInterface
     */
    public function setEventsManager(ManagerInterface $eventsManager): JobInterface
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
     * Sets the connection adapter.
     *
     * @param AdapterInterface $adapter
     * @return JobInterface
     */
    public function setConnection(AdapterInterface $adapter)
    {
        $this->_connectionService = $adapter;
        return $this;
    }

    /**
     * Returns the connection adapter.
     *
     * @return AdapterInterface
     */
    public function getConnection(): AdapterInterface
    {
        return $this->_connectionService;
    }

    /**
     * Determines whether the job is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->_deleted;
    }

    /**
     * Determines whether the task exists(existed).
     *
     * @return bool
     */
    public function isExists(): bool
    {
        return is_int($this->_jobId) && $this->_jobId > 0;
    }

    /**
     * Determines whether the job is released.
     *
     * @return bool
     */
    public function isReleased(): bool
    {
        return $this->_released;
    }

    /**
     * Defines the execution environment.
     *
     * @return bool
     */
    public function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * Returns the current number jobs in queue.
     *
     * @return int
     */
    public function getTotalJobsInQueue(): int
    {
        return $this
            ->getConnection()
            ->getTotalJobsInQueue($this->getFullQueueName());
    }

    /**
     * Returns the job from the queue.
     *
     * @return null|JobInterface
     * @throws Exception
     */
    public function getNextJob()
    {
        if (!$this->isCli()) {
            throw new Exception('The processing method cannot be called in a non-cli environment');
        }

        return $this
            ->getConnection()
            ->getNextJob($this->getFullQueueName());
    }

    /**
     * Remove the job from the queue.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $this->_operationMade = self::OP_DELETE;
        if ($this->fireEventCancel('beforeDelete') === false) {
            $this->_cancelOperation();
            return false;
        } else if ($this->isDeleted()) {
            $this->appendMessage(
                new Message(
                    'The job has already been deleted.',
                    null,
                    null,
                    $this
                )
            );
            $this->_cancelOperation();
            return false;
        }

        $delete = $this
            ->getConnection()
            ->delete($this->getJobId());

        if ($delete !== false) {
            $this->_deleted = true;
            $this->fireEvent('afterDelete');
        } else {
            $this->_cancelOperation();
            return false;
        }

        return $delete;
    }

    /**
     * Returns the type of the latest operation performed by the Job.
     * Returns one of the OP_* class constants
     *
     * @return int
     */
    public function getOperationMade(): int
    {
        return $this->_operationMade;
    }

    /**
     * Sets the Type of the last operation performed by the job.
     * Sets one of the constants of the OP class_*
     *
     * @param int $operation
     * @return JobInterface
     * @throws Exception
     */
    public function setOperationMade(int $operation): JobInterface
    {
        switch ($operation) {
            case self::OP_SEND:
            case self::OP_DELETE:
            case self::OP_RELEASE:
                $this->_operationMade = $operation;
                return $this;
                break;
            default:
                throw new Exception(
                    sprintf('Unknown operation: %d' . $operation)
                );
        }
    }

    /**
     * Sends an event if the operation is canceled.
     *
     * @return void
     */
    protected function _cancelOperation()
    {
        switch ($this->_operationMade) {
            case self::OP_SEND:
                $this->fireEvent('notSent');
                break;
            case self::OP_DELETE:
                $this->fireEvent('notDeleted');
                break;
            case self::OP_RELEASE:
                $this->fireEvent('notReleased');
                break;
        }
    }

    /**
     * Sets the delay before the next attempt.
     *
     * @param $seconds
     * @return JobInterface
     * @throws Exception
     */
    public function setAttemptDelay($seconds): JobInterface
    {
        if (!is_numeric($seconds)) {
            throw new Exception('The seconds argument passed is not a number.');
        }

        $this->_attemptDelay = $seconds;
        return $this;
    }

    /**
     * Returns the delay before the next attempt.
     *
     * @return int
     */
    public function getAttemptDelay(): int
    {
        return $this->_attemptDelay;
    }

    /**
     * Sleep method determines which properties of the current object must be serialized to process the queue in console mode.
     *
     * @return array
     */
    public function __sleep()
    {
        $properties = (new \ReflectionObject($this))
            ->getProperties();

        return array_values(
            array_filter(
                array_map(
                    function (/**@var \ReflectionProperty $property */$property) {
                        if (!$property->isStatic()) {
                            $propertyName = $property->getName();
                            if (!is_callable($this->{$propertyName}) && !is_object($this->{$propertyName})) {
                                $property->setAccessible(true);
                                return $property->getName();
                            }
                        }

                        return null;
                    },
                    $properties
                )
            )
        );
    }

    /**
     * An auxiliary method for the unserialize operation.
     *
     * @throws Exception
     */
    public function __wakeup()
    {
        /**
         * Obtain the default DI
         */
        $dependencyInjector = Di::getDefault();
        if (!($dependencyInjector instanceof \Phalcon\DiInterface)) {
            throw new Exception('A dependency injector container is required to obtain the services related to the Job.');
        }
        $this->_dependencyInjector = $dependencyInjector;

        /**
         * Obtain the default queue
         */
        $connectionService = $this->_dependencyInjector->getShared('queue');
        if (!($connectionService instanceof AdapterInterface)) {
            throw new Exception("The injected service 'queue' is not valid");
        }
        $this->_connectionService = $connectionService;

        $eventsManager = $this->_dependencyInjector->getShared('eventsManager');
        if (!($eventsManager instanceof ManagerInterface)) {
            throw new Exception('An events manager is required to obtain the services related to the Job.');
        }
        $this->_eventsManager = $eventsManager;
    }

    /**
     * Returns array of messages
     *
     * @param mixed $filter
     * @return array
     */
    public function getMessages($filter = null): array
    {
        if (is_string($filter) && !empty($filter)) {
            $filtered = [];
            foreach ($this->_messages as $message) {
                if ($message->getField() == $filter) {
                    $filtered[] = $message;
                }
            }
            return $filtered;
        }

        return $this->_messages;
    }

    /**
     * Appends a customized message on the validation process
     *
     * @param MessageInterface $message
     * @return Job
     */
    public function appendMessage(MessageInterface $message): JobInterface
    {
        $this->_messages[] = $message;
        return $this;
    }

    /**
     * Sets the auto-push.
     *
     * @param bool $status
     * @param int $everySeconds
     * @return JobInterface
     */
    public function setAutoPush(bool $status, int $everySeconds): JobInterface
    {
        $this->_autoPush = $status;
        $this->_autoPushEverySeconds = $everySeconds;
        return $this;
    }

    /**
     * Returns the number of seconds if auto-push is enabled.
     * Otherwise returns false.
     *
     *
     * @return bool|int
     */
    public function getAutoPush()
    {
        return ($this->_autoPush === true) ? $this->_autoPushEverySeconds : false;
    }

    /**
     * Sets the job name.
     *
     * @param string $name
     * @return JobInterface
     */
    public function setJobName(string $name): JobInterface
    {
        $this->_jobName = $name;
        return $this;
    }

    /**
     * Returns the job name.
     *
     * @return string
     */
    public function getJobName(): string
    {
        return $this->_jobName;
    }

    /**
     * Sets the priority.
     *
     * @param int $priority
     * @return JobInterface
     */
    public function setPriority(int $priority): JobInterface
    {
        $this->_priority = $priority;
        return $this;
    }

    /**
     * Returns the priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->_priority;
    }

    /**
     * Sets the delay before run.
     *
     * @param int $seconds
     * @return JobInterface
     */
    public function setDelay(int $seconds): JobInterface
    {
        $this->_delay = $seconds;
        return $this;
    }

    /**
     * Returns the delay before run.
     *
     * @return int
     */
    public function getDelay(): int
    {
        return $this->_delay;
    }

    /**
     * Sets the time to run.
     *
     * @param int $seconds
     * @return JobInterface
     * @throws Exception
     */
    public function setTtr(int $seconds): JobInterface
    {
        if ($seconds < 0) {
            throw new Exception('The execution time cannot be less than zero.');
        }

        $this->_ttr = $seconds;
        return $this;
    }

    /**
     * Returns the time to run.
     *
     * @return int
     */
    public function getTtr(): int
    {
        return $this->_ttr;
    }

    /**
     * Sets the queue prefix.
     *
     * @param string $prefix
     * @return JobInterface
     */
    public function setQueuePrefix(string $prefix): JobInterface
    {
        $this->_queuePrefix = $prefix;
        return $this;
    }

    /**
     * Sets the queue name.
     *
     * @param string $name
     * @return JobInterface
     */
    public function setQueueName(string $name): JobInterface
    {
        $this->_queueName = $name;
        return $this;
    }

    /**
     * Returns the queue prefix.
     *
     * @return string
     */
    public function getQueuePrefix(): string
    {
        return $this->_queuePrefix;
    }

    /**
     * Returns the queue name.
     *
     * @return string
     */
    public function getQueueName(): string
    {
        return $this->_queueName;
    }

    /**
     * Returns the full queue name.
     *
     * @return string
     */
    public function getFullQueueName(): string
    {
        return $this->getQueuePrefix() . $this->getQueueName() . (($this->getAutoPush()) ? $this->getJobName() : '');
    }

    /**
     * Send the job in queue.
     *
     * @param array|null $options
     * @return bool
     */
    public function send(array $options = null): bool
    {
        $this->_operationMade = self::OP_SEND;
        if ($this->fireEventCancel('beforeSend') === false) {
            $this->_cancelOperation();
            return false;
        } else if ($this->isExists()) {
            $this->appendMessage(
                new Message(
                    'You can not send an existing (existed) task to the queue.',
                    null,
                    null,
                    $this
                )
            );
            $this->_cancelOperation();
            return false;
        }

        if ($this->_preSend() !== false) {
            $queueName = $this->getFullQueueName();
            $jobId = $this
                ->getConnection()
                ->send(
                    $this,
                    $queueName,
                    [
                        'ttr' => (isset($options['ttr']) && is_int($options['ttr']))
                            ? $options['ttr']
                            : $this->getTtr(),
                        'delay' => (isset($options['delay']) && is_int($options['delay']))
                            ? $options['delay']
                            : $this->getDelay(),
                        'priority' => (isset($options['priority']) && is_int($options['priority']))
                            ? $options['priority']
                            : $this->getPriority(),
                    ]
                );

            if ($jobId !== false) {
                $this->setJobId($jobId);
            } else {
                $this->_cancelOperation();
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Defines the exhaustion of attempts to handle the job before you delete it.
     *
     * @return bool
     */
    public function isExceededAttempts(): bool
    {
        return $this->_maxAttemptsToDelete < $this->_attempts;
    }

    /**
     * Increases the number of attempts per unit.
     *
     * @return JobInterface
     */
    public function incrementAttempt(): JobInterface
    {
        $this->_attempts++;
        return $this;
    }

    /**
     * Returns the current attempts.
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->_attempts;
    }

    /**
     * Sets the maximum attempts to delete.
     *
     * @param int $attempts
     * @return JobInterface
     * @throws Exception
     */
    public function setMaxAttemptsToDelete(int $attempts = 1): JobInterface
    {
        if ($attempts < 1) {
            throw new Exception('The number of attempts to delete cannot be less than one.');
        }
        $this->_maxAttemptsToDelete = $attempts;
        return $this;
    }

    /**
     * Returns the maximum attempts to delete.
     *
     * @return int
     */
    public function getMaxAttemptsToDelete(): int
    {
        return $this->_maxAttemptsToDelete;
    }

    /**
     * Returns the job id.
     *
     * @return mixed
     */
    public function getJobId()
    {
        return $this->_jobId;
    }

    /**
     * Sets the job id.
     *
     * @param int $id
     * @return JobInterface
     */
    public function setJobId(int $id): JobInterface
    {
        /**
         * Protect from assholes.
         */
        if (is_int($this->_jobId)) {
            return $this;
        }

        $this->_jobId = $id;
        return $this;
    }

    /**
     * Returns the job to the queue.
     *
     * @param int|null $delay
     * @param int|null $priority
     * @return bool
     */
    public function release(int $delay = null, int $priority = null): bool
    {
        $this->_operationMade = self::OP_RELEASE;
        if ($this->fireEventCancel('beforeRelease') === false || $this->isDeleted()) {
            $this->_cancelOperation();
            return false;
        } else if ($this->isDeleted()) {
            $this->appendMessage(
                new Message(
                    'The job has already been deleted.',
                    null,
                    null,
                    $this
                )
            );
            $this->_cancelOperation();
            return false;
        }

        if ($delay === null) {
            $delay = $this->getDelay();
        }
        if ($priority === null) {
            $priority = $this->getPriority();
        }

        $release = $this->getConnection()
            ->release(
                $this->getJobId(),
                $priority,
                $delay
            );

        if ($release) {
            $this->_released = true;
            $this->fireEvent('afterReleased');
        } else {
            $this->_cancelOperation();
        }

        return $release;
    }

    /**
     * Fires an event, implicitly calls behaviors and listeners in the events manager are notified
     *
     * @param string $eventName
     * @return null|bool
     */
    public function fireEvent(string $eventName)
    {
        /**
         * Check if there is a method with the same name of the event
         */
        if (method_exists($this, $eventName)) {
            $this->{$eventName}();
        }

        /**
        * Send a notification to the events manager
        */
        return $this->_eventsManager->fire('job:' . $eventName, $this);
    }

    /**
     * Fires an event, implicitly calls behaviors and listeners in the events manager are notified
     * This method stops if one of the callbacks/listeners returns boolean false
     *
     * @param string $eventName
     * @return bool
     */
    public function fireEventCancel(string $eventName)
    {
        /**
         * Check if there is a method with the same name of the event
         */
        if (method_exists($this, $eventName)) {
            if ($this->{$eventName}() === false) {
                return false;
            }
        }

        /**
         * Dispatch events to the global events manager
         */
        $status = $this->_eventsManager->fire('job:' . $eventName, $this);
        if ($status === false) {
            return false;
        }

        return true;
    }

    /**
     * Fires an event, implicitly calls behaviors and listeners in the events manager are notified
     * This method stops if one of the callbacks/listeners returns boolean false
     *
     * @return bool
     */
    protected function _preSend(): bool
    {
        if ($this->fireEventCancel('beforeValidationOnSend') === false) {
            return false;
        }

        $validation = null;
        if (method_exists($this, 'validation')) {
            $validation = $this->validation();
        }

        if ($this->fireEventCancel('validation') === false) {
            return false;
        }

        if (is_object($validation) && $validation instanceof ValidationInterface) {
            $properties = (new \ReflectionObject($this))
                ->getProperties();

            $data = [];
            foreach ($properties as $property) {
                if (!$property->isDefault()) {
                    continue;
                }
                $data[$property->getName()] = $property->getValue($this);
            }

            $messages = $validation->validate($data);
            if (count($messages)) {
                foreach ($messages as $message) {
                    $this->appendMessage(
                        new Message(
                            $message->getMessage(),
                            $message->getField(),
                            $message->getType(),
                            $this,
                            $message->getCode()
                        )
                    );
                }
                $this->fireEvent('onValidationFails');
                $this->_cancelOperation();
                return false;
            }
        } else if ($validation === false) {
            return false;
        }

        if ($this->fireEventCancel('afterValidationOnSend') === false) {
            return false;
        }

        if ($this->fireEventCancel('beforeSend') === false) {
            return false;
        }

        return true;
    }

    /**
     * Determines a failure of the validation.
     *
     * @return bool
     */
    public function validationHasFailed(): bool
    {
        $messages = $this->_messages;
        if (is_array($messages)) {
            return count($messages) > 0;
        }

        return false;
    }

    /**
     *
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __set($name, $value)
    {
        $jobName = get_class($this);

        if (isset($this->{$name})) {
            $setterName = 'set' . Text::camelize($name);

            if (method_exists($jobName, $setterName)) {
                $this->{$setterName}($value);
                return $value;
            }
        }

        trigger_error("Property '" . $name . "' does not have a setter.", E_USER_ERROR);
    }

    /**
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        $jobName = get_class($this);

        if (isset($this->{$name})) {
            $getterName = 'get' . Text::camelize($name);

            if (method_exists($jobName, $getterName)) {
                return $this->{$getterName}();
            }
        }

        trigger_error("Access to undefined property " . $jobName . "::" . $name, E_USER_ERROR);
    }
}
