<?php

namespace TimurFlush\Queue\Adapter;

use TimurFlush\Queue\Adapter;
use TimurFlush\Queue\AdapterInterface;
use TimurFlush\Queue\Exception;
use TimurFlush\Queue\Job;
use TimurFlush\Queue\JobInterface;

class Beanstalk extends Adapter implements AdapterInterface
{
    /**
     * @var resource Connection resource.
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
    }

    /**
     * Connects to the server.
     *
     * @return resource
     * @throws Exception
     */
    public function connect()
    {
		$connection = $this->_connection;
		if (is_resource($connection)) {
            $this->disconnect();
		}

		$parameters = $this->_parameters;

		/**
         * Check if the connection must be persistent
         */
		if (isset($parameters['persistent']) && $parameters['persistent'] === true) {
            $connection = pfsockopen($parameters['host'], $parameters['port']);
		} else {
            $connection = fsockopen($parameters['host'], $parameters['port']);
		}

		if (!is_resource($connection)) {
            throw new Exception("Can't connect to Beanstalk server");
        }

		stream_set_timeout($connection, -1, null);

		return $this->_connection = $connection;
    }

    /**
     * Reads a packet from the socket. Prior to reading from the socket will
     * check for availability of the connection.
     *
     * @return string|bool
     * @throws Exception
     */
    public function read(int $length = 0)
    {
		$connection = $this->_connection;
		if (!is_resource($connection)) {
            $connection = $this->connect();
			if (!is_resource($connection)) {
			    return false;
            }
		}

		if ($length) {
		    if (feof($connection)) {
		        return false;
		    }

		    $data = rtrim(stream_get_line($connection, $length + 2), "\r\n");
		    if (stream_get_meta_data($connection)["timed_out"]) {
		        throw new Exception("Connection timed out");
		    }
		} else {
		    $data = stream_get_line($connection, 16384, "\r\n");
		}

        if ($data === "UNKNOWN_COMMAND") {
            throw new Exception("UNKNOWN_COMMAND");
        }

        if ($data === "JOB_TOO_BIG") {
            throw new Exception("JOB_TOO_BIG");
        }

        if ($data === "BAD_FORMAT") {
            throw new Exception("BAD_FORMAT");
        }

        if ($data === "OUT_OF_MEMORY") {
            throw new Exception("OUT_OF_MEMORY");
        }

        return $data;
    }

    /**
     * Writes data to the socket. Performs a connection if none is available
     * @throws Exception
     */
    public function write(string $data)
	{
        $connection = $this->_connection;
        if (!is_resource($connection)) {
            $connection = $this->connect();
            if (!is_resource($connection)) {
                return false;
            }
        }

        $packet = $data . "\r\n";
		return fwrite($connection, $packet, strlen($packet));
	}

    /**
     *
     * @param string $data
     * @param string $queue
     * @param array $options
     * @return bool|int
     * @throws Exception
     */
	public function send($data, string $queue, array $options = [])
    {
        $priority = (isset($options['priority']))
            ? $options['priority']
            : $this->getPriority();

        $delay = (isset($options['delay']))
            ? $options['delay']
            : $this->getDelay();

        $ttr = (isset($options['ttr']))
            ? $options['ttr']
            : $this->getTimeToRun();

        $this->chooseQueue($queue);
        
        $serializedData = serialize($data);
        var_dump($serializedData);
        $length = strlen($serializedData);

        $this->write(
            sprintf(
                "put %d %d %d %d\r\n%s",
                $priority,
                $delay,
                $ttr,
                $length,
                $serializedData
            )
        );

        $response = $this->readStatus();
        $status = $response[0];

        switch ($status) {
            case 'INSERTED':
            case 'BURIED':
                return (int)$response[1];
            case 'EXPECTED_CRLF':
            case 'JOB_TOO_BIG':
            case 'DRAINING':
                return false;
                break;
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
                break;
        }
    }

    /**
     *
     * @param string $name
     * @return bool
     * @throws Exception
     */
    public function chooseQueue(string $name): bool
    {
        $this->write(
            sprintf('use %s', $name)
        );

        $response = $this->readStatus();
        $status = $response[0];

        if ($status !== 'USING') {
            throw new Exception('Beanstalk returned an unexpected response.');
        }

        return true;
    }

    /**
     *
     * @param int $jobId
     * @param int|null $priority
     * @return bool
     * @throws Exception
     */
    public function bury(int $jobId, int $priority = null)
    {
        if ($priority === null) {
            $priority = $this->getPriority();
        }

        $this->write(
            sprintf(
                'bury %d %d',
                $jobId,
                $priority
            )
        );

        $response = $this->readStatus();
        $status = $response[0];

        switch ($status) {
            case 'BURIED':
                return true;
                break;
            case 'NOT_FOUND':
                return false;
                break;
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
                break;
        }
    }

    /**
     *
     * @param int $jobId
     * @param int|null $priority
     * @return bool
     * @throws Exception
     */
    public function kickJob(int $jobId, int $priority = null)
    {
        if ($priority === null) {
            $priority = $this->getPriority();
        }

        $this->write(
            sprintf(
                'kick %d',
                $jobId,
                $priority
            )
        );

        $response = $this->readStatus();
        $status = $response[0];

        switch ($status) {
            case 'BURIED':
                return true;
                break;
            case 'NOT_FOUND':
                return false;
                break;
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
                break;
        }
    }

    /**
     *
     * @param string $name
     * @return bool
     * @throws Exception
     */
    public function watchQueue(string $name): bool
    {
        $this->write(
            sprintf('watch %s', $name)
        );

        $response = $this->readStatus();
        $status = $response[0];

        if ($status !== 'WATCHING') {
            throw new Exception('Beanstalk returned an unexpected response.');
        }

        return true;
    }

    /**
     * Returns the job to the queue.
     *
     * @param int $jobId
     * @param int|null $priority
     * @param int|null $delay
     * @return bool
     * @throws Exception
     */
    public function release(int $jobId, int $priority = null, int $delay = null): bool
    {
        if ($priority === null) {
            $priority = $this->getPriority();
        }
        if ($delay === null) {
            $delay = $this->getDelay();
        }

        $this->write(
            sprintf('release %d %d %d', $jobId, $priority, $delay)
        );

        $response = $this->readStatus();
        $status = $response[0];

        if ($status[0] !== 'RELEASED') {
            return false;
        }

        switch ($status) {
            case 'RELEASED':
                return true;
                break;
            case 'NOT_FOUND':
            case 'BURIED':
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
                break;
        }
    }

    /**
     *
     * @return array
     * @throws Exception
     */
    protected function readStatus(): array
	{
		$status = $this->read();
		if ($status === false) {
			return [
			    0 => '',
            ];
		}
        return explode(" ", $status);
    }

    /**
     * Reserves the task.
     *
     * @param int|null $timeout
     * @return bool|array
     * @throws Exception
     */
    public function reserve(int $timeout = null)
    {
        if ($timeout === null) {
            $this->write('reserve');
        } else {
            $this->write(
                sprintf(
                    'reserve-with-timeout %d',
                    $timeout
                )
            );
        }

        $response = $this->readStatus();
        $status = $response[0];

        if ($status !== 'RESERVED') {
            return false;
        }

        switch ($status) {
            case 'RESERVED':
                return [
                    'id' => $response[1],
                    'body' => unserialize($this->read($response[2]))
                ];
                break;
            case 'TIMED_OUT':
                return false;
                break;
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
                break;
        }
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
        if (!$this->watchQueue($queue)) {
            throw new Exception('Beanstalk returned an unexpected response.');
        }

        $reserve = $this->reserve(0);
        if ($reserve === false) {
            return null;
        } else {
            /**
             * @var $job Job
             */
            $job = $reserve['body'];

            $job->setJobId($reserve['id']);
            $job->setConnection($this);
            return $job;
        }
    }

    /**
     * Removes a task with id $jobId.
     *
     * @param int $jobId
     * @return bool
     * @throws Exception
     */
    public function delete(int $jobId): bool
    {
        $this->write(
            sprintf(
                'delete %d',
                $jobId
            )
        );

        $response = $this->readStatus();
        $status = $response[0];

        switch ($status) {
            case 'DELETED':
            case 'NOT_FOUND':
                return true;
                break;
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
        }
    }

    /**
     * Returns the statistics for a queue $queue.
     *
     * @param string $queue
     * @return bool|null|array
     * @throws Exception
     */
    public function statsTube(string $queue)
    {
        $this->write(
            sprintf(
                'stats-tube %s',
                $queue
            )
        );

        $response = $this->readYaml();
        $status = $response[0];

        switch ($status) {
            case 'OK':
                return $response[2];
                break;
            case 'NOT_FOUND':
                return null;
                break;
            default:
                throw new Exception('Beanstalk returned an unexpected response.');
                break;
        }
    }

    /**
     *
     * @return array
     * @throws Exception
     */
    protected function readYaml(): array
	{
		$response = $this->readStatus();

		$status = $response[0];

		if (count($response) > 1) {
		    $numberOfBytes = $response[1];

			$response = $this->read();

			$data = yaml_parse($response);
		} else {
		    $numberOfBytes = 0;
		    $data = [];
		}

        return [
            $status,
            $numberOfBytes,
            $data
        ];
    }

    /**
     * Specifies the current number of jobs in the queue $queue.
     *
     * @param string $queue
     * @return int
     * @throws Exception
     */
    public function getTotalJobsInQueue(string $queue): int
    {
        $stats = $this->statsTube($queue);
        if ($stats === false) {
            throw new Exception('Beanstalk returned an unexpected response.');
        }

        return ($stats === null) ? 0 : $stats['total-jobs'];
    }

    /**
     * Closes the connection to the beanstalk server.
     */
    public function disconnect(): bool
	{
		$connection = $this->_connection;
		if (!is_resource($connection)) {
			return false;
		}

        @fclose($connection);
        $this->_connection = null;

		return true;
	}

    /**
     * Simply closes the connection.
     */
    public function quit(): bool
	{
		$this->write("quit");
		return $this->disconnect();
	}
}