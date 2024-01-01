<?php

declare(strict_types=1);

namespace Bead\Contracts\Queues;

use Bead\Exceptions\QueueException;
use LogicException;

interface Queue
{
    /** The name of the queue. */
    public function name(): string;

    /**
     * @return Message[] This will be empty if there are no messages on the queue.
     * @throws QueueException If the queue cannot be queried for messages.
     * @throws LogicException If the number of messages is less than 1.
     */
    public function peek(int $n = 1): array;

    /**
     * @return Message[] This will be empty if there are no messages on the queue.
     * @throws QueueException If the queue cannot be queried for messages.
     * @throws LogicException If the number of messages is less than 1.
     */
    public function get(int $n = 1): array;

    /**
     * @param Message $message The message to send to the queue.
     * @throws QueueException If the message can't be sent to the queue.
     */
    public function put(Message $message): void;

    /**
     * Remove a message from the queue.
     *
     * @param Message $message
     * @throws QueueException If the message can't be removed from the queue.
     */
    public function delete(Message $message): void;
//
//    public function schedule(Message $message, int $delay): void;
}
