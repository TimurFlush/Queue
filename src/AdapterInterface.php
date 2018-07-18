<?php

namespace TimurFlush\Queue;

interface AdapterInterface
{
    public function connect();

    /**
     * Sets the time to rub.
     *
     * @param int $seconds
     * @return AdapterInterface
     */
    public function setTimeToRun(int $seconds = 60): AdapterInterface;

    /**
     * Returns the time to run.
     *
     * @return int
     */
    public function getTimeToRun(): int;

    /**
     * Sets the delay.
     *
     * @param int $seconds
     * @return AdapterInterface
     */
    public function setDelay(int $seconds = 0): AdapterInterface;

    /**
     * Returns the delay.
     *
     * @return int
     */
    public function getDelay(): int;

    /**
     * Sets the priority.
     *
     * @param int $number
     * @return AdapterInterface
     */
    public function setPriority(int $number = 0): AdapterInterface;

    /**
     * Returns the priority.
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Sets the queue name.
     *
     * @param string $name
     * @return AdapterInterface
     */
    public function setQueue(string $name = 'default'): AdapterInterface;

    /**
     * Returns the queue name.
     *
     * @return string
     */
    public function getQueue(): string;

    /**
     * Disconnect from the server.
     *
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * Sends the job $data to the queue $queueName.
     * If successful, returns the job ID.
     *
     * @param string $data
     * @param string $queue
     * @param array $options
     * @return bool|int
     */
    public function send($data, string $queue, array $options = []);

    /**
     * Returns the job to the queue.
     *
     * @param int $jobId
     * @param int|null $priority
     * @param int|null $delay
     * @return bool
     */
    public function release(int $jobId, int $priority = null, int $delay = null): bool;

    /**
     * Returns the job from the queue.
     *
     * @param string $queue
     * @return null|JobInterface
     */
    public function getNextJob(string $queue);

    /**
     * Removes a task with id $jobId.
     *
     * @param int $jobId
     * @return bool
     */
    public function delete(int $jobId): bool;

    /**
     * Specifies the current number of jobs in the queue $queue.
     *
     * @param string $queue
     * @return int
     */
    public function getTotalJobsInQueue(string $queue): int;

    /**
     * Uses a queue $name
     *
     * @param string $name
     * @return bool
     */
    public function chooseQueue(string $name): bool;

    /**
     * Watches a queue $name
     *
     * @param string $name
     * @return bool
     */
    public function watchQueue(string $name): bool;
}
