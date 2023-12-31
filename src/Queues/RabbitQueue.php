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

class RabbitQueue implements QueueContract
{
    private const DefaultPort = 5672;

    private string $name;

    private Connection $connection;

    private Channel $channel;

    public function __construct(string $queueName, string $host, string $user, string $password, int $port = self::DefaultPort)
    {
        $this->name = $queueName;
        $this->connection = new Connection($host, $port, $user, $password);
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($queueName);
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

    public function get(int $n = 1): array
    {
        assert(0 < $n, new LogicException("Expected positive number of messages to fetch, found {$n}"));
        $messages = [];

        while (0 < $n) {
            try {
                $ampqMessage = $this->channel->basic_get($this->name());
            } catch (AMQPTimeoutException) {
                Log::info("Timeout fetching messages from queue {$this->name()}");
                break;
            } catch (AMQPRuntimeException $err) {
                throw new QueueException("Exception fetching messages from queue {$this->name()}: {$err->getMessage()}", previous: $err);
            }

            if (!$ampqMessage) {
                break;
            }

            --$n;
            $messages[] = (new Message($ampqMessage->getBody()))->withProperties($ampqMessage->get_properties());
        }

        return $messages;
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