<?php

require_once __DIR__ . "/../../../src/autoload.php";

use Equit\Threads\ParallelExecutor as Executor;
use Equit\Threads\Thread;

$executor = new Executor();
$thread1 = new Thread($executor);
$thread2 = new Thread($executor);

$thread1->start(function() {
    sleep(5);
    echo "thread 1 finishing\n";
});

$thread2->start(function() {
    sleep(1);
    echo "thread 2 finishing\n";
});

$thread2->wait();
echo "Thread 2 finished. at " . (new DateTime())->format("Y-m-d H:i:s") . "\n";

$thread1->wait();
echo "Thread 1 finished. at " . (new DateTime())->format("Y-m-d H:i:s") . "\n";
return 0;
