<?php

namespace TimurFlush\Queue;

interface ConnectionAwareInterface
{
    /**
     * Sets the connection adapter.
     *s
     * @param AdapterInterface $adapter
     * @return JobInterface
     */
    public function setConnection(AdapterInterface $adapter);

    /**
     * Returns the connection adapter.
     *
     * @return AdapterInterface
     */
    public function getConnection(): AdapterInterface;
}
