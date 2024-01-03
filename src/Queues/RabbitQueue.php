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
        assert(self::isAvailable(), new LogicException("RabbitMQ client library is not installed."));
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

    /** Ensure the RabbitMQ resources are closed when destroyed. */
    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * Determine whether RabbitMQ client library is installed.
     *
     * Without this library, RabbitQueues cannot be used.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists(Connection::class);
    }

    /** Fetch the queue name. */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Helper to fetch messages from the queue.
     *
     * @param bool $remove Whether to also remove the retrieved messages from the queue.
     *
     * @return RabbitMessage|null The retrieved messages.
     *
     * @throws LogicException if the number of messages to fetch is < 1.
     * @throws QueueException if the underlying AMQP library throws.
     */
    final protected function fetch(bool $remove = true): ?RabbitMessage
    {
        try {
            $ampqMessage = $this->channel->basic_get($this->name());

            if (!$ampqMessage) {
                return null;
            }

            if ($remove) {
                $ampqMessage->ack();
            }
        } catch (AMQPTimeoutException) {
            Log::info("Timeout fetching messages from queue {$this->name()}");
            return null;
        } catch (AMQPRuntimeException $err) {
            throw new QueueException("Exception fetching messages from queue {$this->name()}: {$err->getMessage()}", previous: $err);
        }

        return (new RabbitMessage($ampqMessage->getDeliveryTag(), $this->channel->getChannelId(), $ampqMessage->getBody()))->withProperties($ampqMessage->get_properties());
    }

    /** Retrieve one or more messages from the queue without removing them from it. */
    public function peek(): ?RabbitMessage
    {
        return $this->fetch(false);
    }

    /** Retrieve and remove one or more messages from the queue. */
    public function get(): ?RabbitMessage
    {
        return $this->fetch();
    }

    /**
     * Release a message back to the queue.
     *
     * Messages peeked from the queue can be released back to it. Messages retrieved using get() are destructively
     * retrieved and can't be released back to the queue.
     *
     * @param RabbitMessage $message The message retrieved from the queue to release back to the queue.
     *
     * @throws QueueException if the message was not retrieved from this queue, or cannot be released.
     */
    public function release(MessageContract $message): void
    {
        if (!$message instanceof RabbitMessage) {
            throw new QueueException("Expected message from RabbitMQ queue, found " . $message::class);
        }

        if (null !== $message->channelId() && null !== $this->channel->getChannelId() && $message->channelId() !== $this->channel->getChannelId()) {
            throw new QueueException("Expected message from RabbitMQ queue #{$this->channel->getChannelId()} \"{$this->name()}\" to release, found message from queue #{$message->channelId()}");
        }

        try {
            $this->channel->basic_reject($message->id(), true);
        } catch (AMQPRuntimeException $err) {
            throw new QueueException("Exception releasing message {$message->id()} from the \"{$this->name()}\" queue: {$err->getMessage()}", previous: $err);
        }
    }

    /**
     * Delete a message from the queue.
     *
     * @param RabbitMessage $message The message retrieved from the queue to delete.
     *
     * @throws QueueException if the message was not retrieved from this queue, or cannot be deleted.
     */
    public function delete(MessageContract $message): void
    {
        if (!$message instanceof RabbitMessage) {
            throw new QueueException("Expected message from RabbitMQ queue, found " . $message::class);
        }

        if (null !== $message->channelId() && null !== $this->channel->getChannelId() && $message->channelId() !== $this->channel->getChannelId()) {
            throw new QueueException("Expected message from RabbitMQ queue #{$this->channel->getChannelId()} \"{$this->name()}\" to delete, found message from queue #{$message->channelId()}");
        }

        try {
            $this->channel->basic_ack($message->id());
        } catch (AMQPRuntimeException $err) {
            throw new QueueException("Exception deleting message {$message->id()} from the \"{$this->name()}\" queue: {$err->getMessage()}", previous: $err);
        }
    }

    /** Place a message on the queue. */
    public function put(MessageContract $message): void
    {
        $ampqMessage = new AMQPMessage(
            $message->payload(),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,]
        );

        try {
            $this->channel->basic_publish($ampqMessage, '', $this->name());
        } catch (AMQPRuntimeException $err) {
            throw new QueueException("Unable to place message on queue \"{$this->name()}\": {$err->getMessage()}", previous: $err);
        }
    }
}
