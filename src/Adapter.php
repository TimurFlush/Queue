<?php

namespace TimurFlush\Queue;

abstract class Adapter implements AdapterInterface
{
    /**
     * @var int
     */
    protected $_delay = 0;

    /**
     * @var int
     */
    protected $_priority = 100;

    /**
     * @var int
     */
    protected $_ttr = 60;

    /**
     * @var string
     */
    protected $_queue = 'default';

    public function getPriority(): int
    {
        return $this->_priority;
    }

    public function setPriority(int $number = 0): AdapterInterface
    {
        if ($number < 0) {
            throw new Exception('Priority cannot be less than zero.');
        }

        $this->_priority = $number;
        return $this;
    }

    public function getDelay(): int
    {
        return $this->_delay;
    }

    public function setDelay(int $seconds = 0): AdapterInterface
    {
        if ($seconds < 0) {
            throw new Exception('Delay cannot be less than zero.');
        }

        $this->_delay = $seconds;
        return $this;
    }

    public function getTimeToRun(): int
    {
        return $this->_ttr;
    }

    public function setTimeToRun(int $seconds = 60): AdapterInterface
    {
        if ($seconds < 0) {
            throw new Exception('Runtime cannot be less than zero.');
        }

        $this->_ttr = $seconds;
        return $this;
    }

    public function getQueue(): string
    {
        return $this->_queue;
    }

    public function setQueue(string $name = 'default'): AdapterInterface
    {
        if ($name === '') {
            throw new Exception('The queue name cannot be empty.');
        }

        $this->_queue = $name;
        return $this;
    }

    final public function __destruct()
    {
        register_shutdown_function(function () {
            call_user_func([$this, 'disconnect']);
        });
    }
}
