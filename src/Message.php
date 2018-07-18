<?php

namespace TimurFlush\Queue;

class Message implements MessageInterface
{

    protected $_type;

    protected $_message;

    protected $_field;

    protected $_job;

    protected $_code;

    /**
     * Phalcon\Mvc\Model\Message constructor
     *
     * @param string $message
     * @param string|array $field
     * @param string $type
     * @param JobInterface $job
     * @param int|null $code
     */
    public function __construct(string $message, $field = null, $type = null, $job = null, int $code = null)
    {
        $this->_message = $message;
        $this->_field = $field;
        $this->_type = $type;
        $this->_code = $code;

        if ($job instanceof JobInterface) {
            $this->_job = $job;
        }
    }

    /**
     * Sets message type
     *
     * @param string $type
     * @return Message
     */
    public function setType(string $type): Message
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * Returns message type
     */
    public function getType(): string
    {
        return $this->_type;
    }

    /**
     * Sets verbose message
     *
     * @param string $message
     * @return Message
     */
    public function setMessage(string $message): Message
    {
        $this->_message = $message;
        return $this;
    }

    /**
     * Returns verbose message
     */
    public function getMessage(): string
    {
        return $this->_message;
    }

    /**
     * Sets field name related to message
     *
     * @param string $field
     * @return Message
     */
    public function setField(string $field): Message
    {
        $this->_field = $field;
        return $this;
    }

    /**
     * Returns field name related to message
     */
    public function getField()
    {
        return $this->_field;
    }

    /**
     * Set the model who generates the message
     *
     * @param JobInterface $job
     * @return Message
     */
    public function setJob(JobInterface $job): Message
    {
        $this->_job = $job;
        return $this;
    }

    /**
     * Sets code for the message
     *
     * @param int $code
     * @return Message
     */
    public function setCode(int $code) : Message
    {
        $this->_code = $code;
        return $this;
    }

    /**
     * Returns the model that produced the message
     */
    public function getJob(): JobInterface
    {
        return $this->_job;
    }

    /**
     * Returns the message code
     */
    public function getCode(): int
    {
        return $this->_code;
    }

    /**
     * Magic __toString method returns verbose message
     */
    public function __toString(): string
    {
        return $this->_message;
    }

    /**
     * Magic __set_state helps to re-build messages variable exporting
     *
     * @param array $message
     * @return Message
     */
    public static function __set_state(array $message): MessageInterface
    {
        return new self($message["_message"], $message["_field"], $message["_type"], $message["_code"]);
    }
}
