<?php

/** @var Router $router */

use Equit\Contracts\Router;
use Equit\Request;
use Equit\Facades\Session;
use Equit\View;

$router->registerGet("/session", function(Request $request): View {
    function formatTime($time): string
    {
        if (is_int($time)) {
            $time = new DateTime("@{$time}");
        }

        return $time->format("H:i:s");
    }

    $data = [
        "previousRandom" => Session::get("random-number", "undefined"),
        "createdAt" => formatTime(Session::createdAt()),
        "lastUsedAt" => formatTime(Session::lastUsedAt()),
        "idGeneratedAt" => formatTime(Session::handler()->idGeneratedAt()),
        "idExpiresAt" => formatTime(Session::handler()->idGeneratedAt() + Session::sessionIdRegenerationPeriod()),
        "sessionExpiresAt" => formatTime(Session::lastUsedAt() + Session::sessionIdleTimeoutPeriod()),
        "now" => formatTime(time()),
    ];

    Session::set("random-number", mt_rand(0,100));
    $data["currentRandom"] = Session::get("random-number");
    Session::set("session-id", Session::id());
    return new View("session", $data);
});

$router->registerGet("/prefixed-session", function(Request $request): View {
    $session = Session::prefixed("foo.");
    $session->set([
        "bar" => "bar",
        "baz" => "baz",
        "fizz" => "fizz",
        "buzz" => "buzz",
        "quux" => "quux",
    ]);

    $session["flox"] = "flux";

    $extracted = $session->extract(["fizz", "buzz",]);
    return new View("prefixed-session", compact("extracted"));
});
