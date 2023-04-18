<?php

namespace App\Service;

interface MessageBrokerServiceInterface
{
    /**
     * @param string $queue
     * @param string $message
     * @return void
     */
    public function send(string $queue, string $message): void;

    /**
     * @param string $queue
     * @param callable $callback
     * @return void
     */
    public function receive(string $queue, callable $callback): void;
}
