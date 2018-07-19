<?php

namespace TimurFlush\Queue\Tests;

use Phalcon\Di;
use PHPUnit\Framework\TestCase;
use TimurFlush\Queue\Exception;
use TimurFlush\Queue\Message;
use TimurFlush\Queue\Tests\Jobs\TestJob;
use Mockery as m;

class JobOverloadingTest extends TestCase
{
    /**
     * @var \TimurFlush\Queue\Job
     */
    protected $job;

    public function setUp()/* The :void return type declaration that should be here would cause a BC issue */
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->job = new TestJob();
    }

    public function testSleep()
    {
        $job = &$this->job;

        $resultProperties = $job->__sleep();

        $properties = (new \ReflectionObject($job))
            ->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            if (!is_callable($property->getValue($job)) && !is_object($property->getValue($job))) {
                $this->assertTrue(
                    in_array($property->getName(), $resultProperties),
                    'Property does not exists in __sleep() return.'
                );
            }
        }
    }

    public function testWakeup()
    {
        $job = &$this->job;

        /*
         * For test.
         */
        $job->setTtr(123);
        $job->setDelay(123);

        $expectedProperties = [];
        $properties = (new \ReflectionObject($job))
            ->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            if (!is_callable($property->getValue($job)) && !is_object($property->getValue($job))) {
                $expectedProperties[$property->getName()] = $property->getValue($job);
            }
        }

        $jobSleep = serialize($job);
        $jobWakedup = unserialize($jobSleep);

        $resultProperties = [];
        $properties = (new \ReflectionObject($jobWakedup))
            ->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            if (!is_callable($property->getValue($jobWakedup)) && !is_object($property->getValue($jobWakedup))) {
                $resultProperties[$property->getName()] = $property->getValue($jobWakedup);
            }
        }

        foreach ($expectedProperties as $name => $value) {
            $this->assertTrue(
                array_key_exists($name, $resultProperties) && $resultProperties[$name] === $expectedProperties[$name],
                sprintf('The property named %s was not found in the awakened object.', $name)
            );
        }

    }

    public function testSetGet()
    {
        $job = &$this->job;

        $id = 5;
        $job->setId($id);

        $this->assertEquals($id, $job->getId(), '__set()/__get() is not working.');
    }

    public function tearDown()/* The :void return type declaration that should be here would cause a BC issue */
    {
        parent::tearDown(); // TODO: Change the autogenerated stub

        unset($this->job);
    }
}
