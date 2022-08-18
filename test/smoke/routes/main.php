<?php

use Equit\Contracts\Router;
use Equit\View;
use Equit\Threads\Thread;

/** @var Router $router */

$router->registerGet("/", function(): View {
	return new View("view", ["foo" => "Home page",]);
});

$router->registerGet("/thread", function(): View {
    $thread1 = new Thread();
    $thread2 = new Thread();
    $thread1->start(function() {
        sleep(1);
    });

    $thread2->start(function() {
        usleep(100000);
    });

    $finished = Thread::waitForOneOf([$thread1, $thread2]);

    if (in_array($thread2, $finished)) {
        $foo = "Thread 2";
    } else {
        $foo = "Thread 1";
    }

    if (2 != count($finished)) {
        $delay = microtime(true);
        $thread1->wait();
        $thread2->wait();
        $delay = (microtime(true) - $delay) / 1000.0;
    } else {
        $delay = 0.0;
    }

	return new View("view", compact("foo", "delay"));
});

$router->registerGet("/bar", function(): View {
	return new View("view", ["foo" => "Bar page",]);
});
