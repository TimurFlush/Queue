<?php

namespace TimurFlush\Queue\Adapter;

use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use TimurFlush\Queue\Adapter;
use TimurFlush\Queue\AdapterInterface;
use TimurFlush\Queue\Exception;
use TimurFlush\Queue\JobInterface;
use Pheanstalk\Exception\ServerException;

class Beanstalk extends Adapter implements AdapterInterface
{
    /**
     * @var \Pheanstalk\Pheanstalk
     */
    protected $_connection = null;

    /**
     * Connection options
     * @var array
     */
    protected $_parameters = null;

    /**
     * Beanstalk constructor.
     * @param array $parameters
     */
    public function __construct(array $parameters = [])
    {
        if (!isset($parameters['host'])) {
            $parameters['host'] = '127.0.0.1';
        }
        if (!isset($parameters['port'])) {
            $parameters['port'] = 11300;
        }
        if (!isset($parameters['persistent'])) {
            $parameters['persistent'] = false;
        }

        $this->_parameters = $parameters;
        $this->_connection = new Pheanstalk(
            $parameters['host'],
            $parameters['port'],
            10,
            $parameters['persistent']
        );
    }

    public function connect()
    {
        return true;
    }

    /**
     *
     * @param mixed $data Data to serialize.
     * @param string $queue Queue name.
     * @param array $options Options.
     * @return int
     */
    public function send($data, string $queue, array $options = [])
    {
        $this->_connection->useTube($queue);
        $put = $this->put($data, $options);
        return $put->getId();
    }

    /**
     *
     * @param string $name
     * @return bool
     */
    public function chooseQueue(string $name): bool
    {
        $this->_connection->useTube($name);
        return true;
    }

    /**
     *
     * @param int $jobId
     * @param int|null $priority
     * @return bool
     */
    public function bury(int $jobId, int $priority = null)
    {
        $priority = (isset($priority) && $priority >= 0)
            ? $priority
            : $this->getPriority();

        $this->_connection->bury(new \Pheanstalk\Job($jobId, ''), $priority);
        return true;
    }

    /**
     *
     * @param int $jobId
     * @return bool
     */
    public function kickJob(int $jobId)
    {
        $this->_connection->kickJob(new \Pheanstalk\Job($jobId, ''));
        return true;
    }

    /**
     *
     * @param string $name
     * @return bool
     */
    public function watchQueue(string $name): bool
    {
        $this->_connection->watch($name);
        return true;
    }

    /**
     * Returns the job to the queue.
     *
     * @param int $jobId
     * @param int|null $priority
     * @param int|null $delay
     * @return bool
     */
    public function release(int $jobId, int $priority = null, int $delay = null): bool
    {
        if ($priority === null) {
            $priority = $this->getPriority();
        }
        if ($delay === null) {
            $delay = $this->getDelay();
        }

        $this->_connection->release(new \Pheanstalk\Job($jobId, ''), $priority, $delay);
        return true;
    }


    /**
     * Reserves the task.
     *
     * @param int|null $timeout
     * @return \Pheanstalk\Job|false
     */
    public function reserve(int $timeout = null)
    {
        $reserve = $this->_connection->reserve($timeout);
        return $reserve;
    }

    /**
     * Returns the job from the queue.
     *
     * @param string $queue
     * @return null|JobInterface
     * @throws Exception
     */
    public function getNextJob(string $queue)
    {
        $this->watchQueue($queue);

        $reserve = $this->reserve(0);
        if ($reserve === false) {
            return null;
        } else {
            $job = unserialize($reserve->getData());
            if ($job instanceof JobInterface) {
                $job->setJobId($reserve->getId());
                $job->setConnection($this);
            } else {
                $this->delete($reserve->getId());
                throw new Exception('Reserved job is not object JobInterface.');
            }

            return $job;
        }
    }

    /**
     * Removes a task with id $jobId.
     *
     * @param int $jobId
     * @return bool
     */
    public function delete(int $jobId): bool
    {
        $this->_connection->delete(new \Pheanstalk\Job($jobId, ''));
        return true;
    }

    /**
     * Returns the statistics for a queue $queue.
     *
     * @param string $queue
     * @return array
     */
    public function statsTube(string $queue)
    {
        $statsTube = $this->_connection->statsTube($queue);
        return $statsTube;
    }

    /**
     * Specifies the current number of jobs in the queue $queue.
     *
     * @param string $queue
     * @return int
     */
    public function getTotalJobsInQueue(string $queue): int
    {
        try {
            $stats = $this->statsTube($queue);
        } catch (ServerException $exception) {
            $stats = new \stdClass();
            $stats->total_jobs = 0;
        }
        return (int)$stats->total_jobs;
    }

    /**
     * Closes the connection to the beanstalk server.
     */
    public function disconnect(): bool
    {
        return true;
    }

    /**
     * Create a new job.
     *
     * @param $data
     * @param array $options
     * @return Job
     */
    public function put($data, array $options = [])
    {
        $priority = (isset($options['priority']) && $options['priority'] >= 0)
            ? $options['priority']
            : $this->getPriority();

        $delay = (isset($options['delay']) && $options['delay'] >= 0)
            ? $options['delay']
            : $this->getDelay();

        $ttr = (isset($options['ttr']) && $options['ttr'] >= 0)
            ? $options['ttr']
            : $this->getTimeToRun();

        $data = serialize($data);
        $put = $this->_connection->put($data, $priority, $delay, $ttr);
        return new Job($put, $data);
    }
}
