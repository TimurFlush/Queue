<?php

namespace TimurFlush\Queue;

interface MessageInterface
{
    /**
     * Sets message type
     *
     * @param string type
     */
    public function setType(string $type);

    /**
     * Returns message type
     *
     * @return string
     */
    public function getType();

    /**
     * Sets verbose message
     *
     * @param string message
     */
    public function setMessage(string $message);

    /**
     * Returns verbose message
     *
     * @return string
     */
    public function getMessage();

    /**
     * Sets field name related to message
     *
     * @param string field
     */
    public function setField(string $field);

    /**
     * Returns field name related to message
     *
     * @return string
     */
    public function getField();

    /**
     * Magic __toString method returns verbose message
     */
    public function __toString(): string;

    /**
     * Magic __set_state helps to recover messages from serialization
     *
     * @param array $message
     */
    public static function __set_state(array $message): MessageInterface;
}
