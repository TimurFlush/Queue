<?php

namespace TimurFlush\Queue\Tests\Jobs;

class TestJob extends \TimurFlush\Queue\Job
{
    protected $id;

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function handle(): bool
    {
        // TODO: Implement handle() method.
    }
}