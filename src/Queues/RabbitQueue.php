<?php

declare(strict_types=1);

namespace Bead\Queues;

use Bead\Contracts\Queues\Message as MessageContract;
use Bead\Contracts\Queues\Queue as QueueContract;
use Bead\Exceptions\QueueException;
use Bead\Facades\Log;
use LogicException;
use PhpAmqpLib\Channel\AMQPChannel as Channel;
use PhpAmqpLib\Connection\AMQPStreamConnection as Connection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Implementation of the Queue contract for RabbitMQ queues.
 *
 * To use this class to connect, the queue must:
 * - already exist (the named queue won't be created on-the-fly)
 * - be durable
 *
 * The queue will not be destroyed when the object is destroyed. In other words, this class is intended to connect to
 * pre-existing queues that outlive the application.
 */
class RabbitQueue implements QueueContract
{
    private const DefaultPort = 5672;

    private string $name;

    private Connection $connection;

    private Channel $channel;

    /**
     *
     * @param string $queueName
     * @param string $host
     * @param string $user
     * @param string $password
     * @param int $port
     *
     * @throws QueueException If it's not possible to connect to RabbitMQ with the host and credentials provided, or if
     * the named queue cannot be connected to.
     */
    public function __construct(string $queueName, string $host, string $user, string $password, int $port = self::DefaultPort)
    {
        $this->name = $queueName;

        try {
            $this->connection = new Connection($host, $port, $user, $password);
        } catch (Throwable $err) {
            throw new QueueException("Unable to connect to RabbitMQ on \"{$host}:{$port}\": {$err->getMessage()}", previous: $err);
        }

        $this->channel = $this->connection->channel();


        try {
            $this->channel->queue_declare($queueName, true, true, false, false, false);
        } catch (AMQPRuntimeException $err) {
            throw new QueueException("Exception connecting to RabbitMQ queue \"{$queueName}\": {$err->getMessage()}", previous: $err);
        }
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }

    public static function isAvailable(): bool
    {
        return class_exists(Connection::class);
    }

    public function name(): string
    {
        return $this->name;
    }

    final protected function fetch(int $n, bool $remove = true): array
    {
        assert(0 < $n, new LogicException("Expected positive number of messages to fetch, found {$n}"));
        $messages = [];

        while (0 < $n) {
            try {
                $ampqMessage = $this->channel->basic_get($this->name());

                if (!$ampqMessage) {
                    break;
                }

                if ($remove) {
                    $ampqMessage->ack();
                }
            } catch (AMQPTimeoutException) {
                Log::info("Timeout fetching messages from queue {$this->name()}");
                break;
            } catch (AMQPRuntimeException $err) {
                throw new QueueException("Exception fetching messages from queue {$this->name()}: {$err->getMessage()}", previous: $err);
            }

            --$n;
            $messages[] = (new RabbitMessage($ampqMessage->getDeliveryTag(), $ampqMessage->getBody()))->withProperties($ampqMessage->get_properties());
        }

        return $messages;
    }

    public function peek(int $n = 1): array
    {
        return $this->fetch($n, false);
    }

    public function get(int $n = 1): array
    {
        return $this->fetch($n);
    }

    public function delete(MessageContract $message): void
    {
        if (!$message instanceof RabbitMessage) {
            throw new QueueException("Expected message from RabbitMQ queue, found " . $message::class);
        }

        $this->channel->basic_ack($message->id());
    }

    public function put(MessageContract $message): void
    {
        $ampqMessage = new AMQPMessage(
            $message->payload(),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,]
        );

        try {
            $this->channel->basic_publish($ampqMessage, '', $this->name());
        } catch (AMQPRuntimeException $err) {
            throw new QueueException("Unable to place message on queue {$this->name()}: {$err->getMessage()}", previous: $err);
        }
    }
}