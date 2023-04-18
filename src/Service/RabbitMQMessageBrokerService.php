<?php

namespace App\Service;

use Exception;
use PhpAmqpLib\Channel\AbstractChannel;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class RabbitMQMessageBrokerService implements MessageBrokerServiceInterface
{
    /**
     * @var AMQPStreamConnection
     */
    private AMQPStreamConnection $connection;
    /**
     * @var AbstractChannel|AMQPChannel
     */
    private AbstractChannel|AMQPChannel $channel;

    /**
     * @param AMQPStreamConnection $connection
     */
    public function __construct(AMQPStreamConnection $connection)
    {
        try {
            $this->connection = $connection;
            $this->channel = $this->connection->channel();
        } catch (Exception $e) {
            throw new RuntimeException("Error connecting to RabbitMQ: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string $queue
     * @param string $message
     * @return void
     */
    public function send(string $queue, string $message): void
    {
        $this->channel->queue_declare($queue, false, false, false, false);

        $msg = new AMQPMessage($message);
        $this->channel->basic_publish($msg, '', $queue);
    }

    /**
     * @param string $queue
     * @param callable $callback
     * @return void
     */
    public function receive(string $queue, callable $callback): void
    {
        $this->channel->queue_declare($queue, false, false, false, false);
        //todo check
        $stopLoop = false;

        $this->channel->basic_consume(
            $queue,
            '',
            false,
            true,
            false,
            false,
            function ($msg) use ($callback, &$stopLoop) {
                $callback($msg);
                $stopLoop = true;
            }
        );

        while (count($this->channel->callbacks) && !$stopLoop) {
            $this->channel->wait(null, false, 30);
        }
    }

    /**
     * @throws Exception
     */
    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}