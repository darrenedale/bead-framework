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
     * Non-destructively retrieve a message from the queue.
     *
     * The message will remain on the queue. Depending on the implementation, it may be locked for a period of time. To
     * unlock the message for other receivers, pass it to release(). Consumers should aim to release() or delete() a
     * peeked message as soon as possible.
     *
     * @return ?Message This will be empty if there are no messages on the queue.
     * @throws QueueException If the queue cannot be queried for messages.
     */
    public function peek(): ?Message;

    /**
     * Destructively retrieve a message from the queue.
     *
     * The message at the head of the queue will be retrieved and deleted from the queue, before being returned.
     *
     * @return ?Message This will be empty if there are no messages on the queue.
     * @throws QueueException If the queue cannot be queried for messages.
     */
    public function get(): ?Message;

    /**
     * Post a message to the queue.
     *
     * @param Message $message The message to send to the queue.
     * @throws QueueException If the message can't be sent to the queue.
     */
    public function put(Message $message): void;

    /**
     * Release a peeked message for other receivers.
     *
     * Depending on the implementation, peek() may lock a message while it remains on the queue (for a period of time)
     * preventing other receivers from fetching it. Passing it to this method will make it available again.
     *
     * Implementations that don't lock messages peeked from the queue should implement this method as a no-op.
     *
     * @param Message $message
     * @throws QueueException If the message can't be released on the queue.
     */
    public function release(Message $message): void;

    /**
     * Remove a message from the queue.
     *
     * The message to delete must have been peeked from the queue.
     *
     * @param Message $message
     * @throws QueueException If the message can't be removed from the queue.
     */
    public function delete(Message $message): void;
//
//    public function schedule(Message $message, int $delay): void;
}
