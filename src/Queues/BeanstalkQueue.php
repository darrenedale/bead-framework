<?php

declare(strict_types=1);

namespace Bead\Queues;

use Bead\Contracts\Queues\Message as MessageContract;
use Bead\Contracts\Queues\Queue as QueueContract;
use Bead\Exceptions\QueueException;
use InvalidArgumentException;
use LogicException;
use Pheanstalk\Exception\DeadlineSoonException;
use Pheanstalk\Exception\ExpectedCrlfException;
use Pheanstalk\Exception\JobBuriedException;
use Pheanstalk\Exception\JobNotFoundException;
use Pheanstalk\Exception\JobTooBigException;
use Pheanstalk\Exception\MalformedResponseException;
use Pheanstalk\Exception\ServerDrainingException;
use Pheanstalk\Exception\UnsupportedResponseException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\TubeName;
use Throwable;

/**
 * Implementation of the Queue contract for beanstalkd queues.
 */
class BeanstalkQueue implements QueueContract
{
    private const DefaultPort = 11300;

    private TubeName $tube;

    private Pheanstalk $client;

    /**
     *
     * @param string $queueName
     * @param string $host
     * @param int $port
     *
     * @throws QueueException If it's not possible to connect to Beanstalk with the host and credentials provided, or if
     * the named queue cannot be connected to.
     */
    public function __construct(string $queueName, string $host, int $port = self::DefaultPort)
    {
        assert(self::isAvailable(), new LogicException("Beanstalk client library is not installed."));

        try {
            $this->tube = new TubeName($queueName);
            $this->client = Pheanstalk::create($host, $port);
        } catch (InvalidArgumentException $err) {
            throw new QueueException("Expected valid beanstalkd queue name, found \"{$queueName}\"", previous: $err);
        } catch (Throwable $err) {
            throw new QueueException("Unable to connect to beanstalkd on \"{$host}:{$port}\": {$err->getMessage()}", previous: $err);
        }

        try {
            $this->client->watch($this->tube);
        } catch (MalformedResponseException|UnsupportedResponseException $err) {
            throw new QueueException("Invalid or unexpected response from beanstalkd on \"{$host}:{$port}\" when watching \"{$queueName}\": {$err->getMessage()}", previous: $err);
        }

        try {
            $this->client->useTube($this->tube);
        } catch (MalformedResponseException|UnsupportedResponseException $err) {
            throw new QueueException("Invalid or unexpected response from beanstalkd on \"{$host}:{$port}\" when preparing for publication to \"{$queueName}\": {$err->getMessage()}", previous: $err);
        }
    }

    /**
     * Determine whether Pheanstalk client library is installed.
     *
     * Without this library, BeanstalkQueues cannot be used.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists(Pheanstalk::class);
    }

    /** Fetch the queue name. */
    public function name(): string
    {
        return (string) $this->tube;
    }

    /**
     * Helper to fetch messages from the queue.
     *
     * @param bool $remove Whether to also remove the retrieved messages from the queue.
     *
     * @return BeanstalkMessage|null The retrieved messages.
     *
     * @throws LogicException if the number of messages to fetch is < 1.
     * @throws QueueException if the underlying AMQP library throws.
     */
    final protected function fetch(bool $remove = true): ?BeanstalkMessage
    {
        try {
            $pheanstalkMessage = $this->client->reserveWithTimeout(0);

            if (!$pheanstalkMessage) {
                return null;
            }

            if ($remove) {
                $this->client->delete($pheanstalkMessage);
            }
        } catch (DeadlineSoonException|MalformedResponseException|UnsupportedResponseException $err) {
            throw new QueueException("Exception fetching messages from queue {$this->name()}: {$err->getMessage()}", previous: $err);
        }

        return new BeanstalkMessage($pheanstalkMessage->getId(), $pheanstalkMessage->getData());
    }

    /** Retrieve one or more messages from the queue without removing them from it. */
    public function peek(): ?BeanstalkMessage
    {
        return $this->fetch(false);
    }

    /** Retrieve and remove one or more messages from the queue. */
    public function get(): ?BeanstalkMessage
    {
        return $this->fetch();
    }

    /**
     * Delete a message from the queue.
     *
     * @param BeanstalkMessage $message The message retrieved from the queue to delete.
     *
     * @throws QueueException if the message cannot be deleted.
     */
    public function delete(MessageContract $message): void
    {
        if (!$message instanceof BeanstalkMessage) {
            throw new QueueException("Expected message from beanstalkd queue, found " . $message::class);
        }

        try {
            $this->client->delete(new JobId($message->id()));
        } catch (JobNotFoundException|UnsupportedResponseException $err) {
            throw new QueueException("Exception deleting message {$message->id()} from the \"{$this->name()}\" queue: {$err->getMessage()}", previous: $err);
        }
    }

    /**
     * Release a locked message peeked from the queue.
     *
     * @param BeanstalkMessage $message The message retrieved from the queue to release.
     *
     * @throws QueueException if the message cannot be deleted.
     */
    public function release(MessageContract $message): void
    {
        if (!$message instanceof BeanstalkMessage) {
            throw new QueueException("Expected message from beanstalkd queue, found " . $message::class);
        }

        try {
            $this->client->release(new JobId($message->id()));
        } catch (JobNotFoundException|UnsupportedResponseException $err) {
            throw new QueueException("Exception releasing message {$message->id()} from the \"{$this->name()}\" queue: {$err->getMessage()}", previous: $err);
        }
    }

    /** Place a message on the queue. */
    public function put(MessageContract $message): void
    {
        try {
            $this->client->put($message->payload());
        } catch (JobBuriedException|MalformedResponseException|ExpectedCrlfException|ServerDrainingException|JobTooBigException|MalformedResponseException|UnsupportedResponseException $err) {
            throw new QueueException("Unable to place message on queue \"{$this->name()}\": {$err->getMessage()}", previous: $err);
        }
    }
}
