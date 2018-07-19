<?php

namespace TimurFlush\Queue\Tests\Jobs;

use Phalcon\Validation;

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

    public function someEventTrue()
    {
        return true;
    }

    public function someEventFalse()
    {
        return false;
    }

    public function validation()
    {
        $validation = new Validation();

        $validation->add(
            'id',
            new Validation\Validator\PresenceOf([
                'message' => 'ID cannot be empty'
            ])
        );

        return $validation;
    }

    public function handle(): bool
    {
        // TODO: Implement handle() method.
    }
}