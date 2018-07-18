<?php

namespace TimurFlush\Queue\Adapter;

use TimurFlush\Queue\Adapter;
use TimurFlush\Queue\AdapterInterface;

class Blackhole extends Adapter implements AdapterInterface
{
    public function chooseQueue(string $name): bool
    {
        return true;
    }

    public function getTotalJobsInQueue(string $queue): int
    {
        return 0;
    }

    public function connect()
    {
        return;
    }

    public function delete(int $jobId): bool
    {
        return true;
    }

    public function disconnect(): bool
    {
        return true;
    }

    public function getNextJob(string $queue)
    {
        return null;
    }

    public function release(int $jobId, int $priority = null, int $delay = null): bool
    {
        return true;
    }

    public function watchQueue(string $name): bool
    {
        return true;
    }

    public function send($data, string $queue, array $options = [])
    {
        return 0;
    }
}
