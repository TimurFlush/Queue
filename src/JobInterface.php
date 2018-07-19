<?php

namespace TimurFlush\Queue;

interface JobInterface
{
    /**
     * Sets the maximum attempts to delete.
     *
     * @param int $attempts
     * @return JobInterface
     */
    public function setMaxAttemptsToDelete(int $attempts): JobInterface;

    /**
     * Returns the maximum attempts to delete.
     *
     * @return int
     */
    public function getMaxAttemptsToDelete(): int;

    /**
     * Sets the Type of the last operation performed by the job.
     * Sets one of the constants of the OP class_*
     *
     * @param int $operation
     * @return JobInterface
     */
    public function setOperationMade(int $operation): JobInterface;

    /**
     * Returns the type of the latest operation performed by the Job.
     * Returns one of the OP_* class constants
     *
     * @return int
     */
    public function getOperationMade(): int;

    /**
     * Returns the current attempts.
     *
     * @return int
     */
    public function getAttempts(): int;

    /**
     * Increases the number of attempts per unit.
     *
     * @return JobInterface
     */
    public function incrementAttempt(): JobInterface;

    /**
     * Returns the messages.
     *
     * @param null $filter
     * @return array
     */
    public function getMessages($filter = null): array;

    /**
     * Appends a customized message on the validation process
     *
     * @param MessageInterface $message
     * @return Job
     */
    public function appendMessage(MessageInterface $message): JobInterface;

    /**
     * Handle the job.
     *
     * @return bool
     */
    public function handle(): bool;

    /**
     * Send the job in queue.
     *
     * @param array|null $options
     * @return bool
     */
    public function send(array $options = null): bool;

    /**
     * Sets the queue name.
     *
     * @param string $name
     * @return JobInterface
     */
    public function setQueueName(string $name): JobInterface;

    /**
     * Returns the queue name.
     *
     * @return string
     */
    public function getQueueName(): string;

    /**
     * Sets the queue prefix.
     *
     * @param string $prefix
     * @return JobInterface
     */
    public function setQueuePrefix(string $prefix): JobInterface;

    /**
     * Returns the queue prefix.
     *
     * @return string
     */
    public function getQueuePrefix(): string;

    /**
     * Returns the full queue name.
     *
     * @return string
     */
    public function getFullQueueName(): string;

    /**
     * Sets the time to run.
     *
     * @param int $seconds
     * @return JobInterface
     */
    public function setTtr(int $seconds): JobInterface;

    /**
     * Returns the time to run.
     *
     * @return int
     */
    public function getTtr(): int;

    /**
     * Sets the delay before run.
     *
     * @param int $seconds
     * @return JobInterface
     */
    public function setDelay(int $seconds): JobInterface;

    /**
     * Returns the delay before run.
     *
     * @return int
     */
    public function getDelay(): int;

    /**
     * Sets the priority.
     *
     * @param int $priority
     * @return JobInterface
     */
    public function setPriority(int $priority): JobInterface;

    /**
     * Returns the priority.
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Sets the job name.
     *
     * @param string $name
     * @return JobInterface
     */
    public function setJobName(string $name): JobInterface;

    /**
     * Returns the job name.
     *
     * @return string
     */
    public function getJobName(): string;

    /**
     * Sets the auto-push.
     *
     * @param bool $status
     * @param int $everySeconds
     * @return JobInterface
     */
    public function setAutoPush(bool $status, int $everySeconds): JobInterface;

    /**
     * Returns the number of seconds if auto-push is enabled.
     * Otherwise returns false.
     *
     *
     * @return bool|int
     */
    public function getAutoPush();

    /**
     * Sets the job id.
     *
     * @param int $id
     * @return JobInterface
     */
    public function setJobId(int $id): JobInterface;

    /**
     * Returns the job id.
     *
     * @return mixed
     */
    public function getJobId();

    /**
     * Returns the job to the queue.
     *
     * @param int|null $delay
     * @param int|null $priority
     * @return bool
     */
    public function release(int $delay = null, int $priority = null): bool;

    /**
     * Remove the job from the queue.
     *
     * @return bool
     */
    public function delete(): bool;

    /**
     * Determines whether the job is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool;

    /**
     * Determines whether the job is released.
     *
     * @return bool
     */
    public function isReleased(): bool;

    /**
     * Defines the exhaustion of attempts to handle the job before you delete it.
     *
     * @return bool
     */
    public function isExceededAttempts(): bool;

    /**
     * Sets the delay before the next attempt.
     *
     * @param $seconds
     * @return JobInterface
     */
    public function setAttemptDelay($seconds): JobInterface;

    /**
     * Returns the delay before the next attempt.
     *
     * @return int
     */
    public function getAttemptDelay(): int;

    /**
     * Returns the job from the queue.
     *
     * @return null|JobInterface
     * @throws Exception
     */
    public function getNextJob();

    /**
     * Returns the current number jobs in queue.
     *
     * @return int
     */
    public function getTotalJobsInQueue(): int;

    /**
     * Determines a failure of the validation.
     *
     * @return bool
     */
    public function validationHasFailed(): bool;

    /**
     * Determines whether the task exists(existed).
     *
     * @return bool
     */
    public function isExists(): bool;
}
