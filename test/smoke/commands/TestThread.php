<?php

namespace Equit\Test\Smoke\Commands;

use DateTime;
use Equit\ConsoleApplication;
use Equit\Threads\Thread;

class TestThread extends ConsoleApplication
{
    public function exec(): int
    {
        $thread1 = new Thread();
        $thread2 = new Thread();

        $thread1->start(function() {
            sleep(5);
        });

        $thread2->start(function() {
            sleep(1);
        });

        $thread2->wait();
        $this->line("Thread 2 finished. at " . (new DateTime())->format("Y-m-d H:i:s") . "\n");

        $thread1->wait();
        $this->line("Thread 1 finished. at " . (new DateTime())->format("Y-m-d H:i:s") . "\n");
        return 0;
    }
}