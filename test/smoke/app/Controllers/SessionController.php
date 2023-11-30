<?php
declare(strict_types=1);

namespace BeadTests\smoke\app\Controllers;

use Bead\Request;
use Bead\Facades\Session;
use Bead\View;
use DateTime;

class SessionController
{
    private static function formatTime(int|DateTime $time): string
    {
        if (is_int($time)) {
            $time = new DateTime("@{$time}");
        }

        return $time->format("H:i:s");
    }

    public function showDetails(Request $request): View
    {
        $data = [
            "previousRandom" => Session::get("random-number", "undefined"),
            "createdAt" => self::formatTime(Session::createdAt()),
            "lastUsedAt" => self::formatTime(Session::lastUsedAt()),
            "idGeneratedAt" => self::formatTime(Session::handler()->idGeneratedAt()),
            "idExpiresAt" => self::formatTime(Session::handler()->idGeneratedAt() + Session::sessionIdRegenerationPeriod()),
            "sessionExpiresAt" => self::formatTime(Session::lastUsedAt() + Session::sessionIdleTimeoutPeriod()),
            "now" => self::formatTime(time()),
        ];

        Session::set("random-number", mt_rand(0, 100));
        $data["currentRandom"] = Session::get("random-number");
        Session::set("session-id", Session::id());

        $data["session"] = [];

        foreach (Session::all() as $key => $value) {
            if (is_string($value)) {
                $data["session"][$key] = $value;
            }
        }

        return new View("session", $data);
    }

    public function prefixedSession(): View
    {
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
    }

    public function refreshTransientData(Request $request): View
    {
        Session::refreshTransientData();
        return $this->showDetails($request);

    }

    private static function randomWord(): string
    {
        static $words = [
            "hand", "hovercraft", "eel", "cupboard", "trash",
            "foot", "Novocaine","put", "DiCaprio", "knives",
            "Nome", "impregnability", "fwd", "Argentina", "Graves",
            "spearmint", "exhibit", "garland", "tease", "bathe",
            "whiten", "woodcut", "refundable", "Broadways", "refuse",
            "diligence", "enlightened", "growl", "mestizo", "manifolding",
        ];

        return $words[rand(0, count($words) - 1)];
    }

    public function addTransientData(Request $request, int $count = 0): view
    {
        if (0 === $count) {
            $count = rand(1, 5);
        }

        while (0 < $count) {
            $key = self::randomWord();
            $value = self::randomWord();
            Session::transientSet($key, $value);
            --$count;
        }

        Session::commit();
        return $this->showDetails($request);
    }

    public function set(Request $request): View
    {
        Session::set("some data", self::randomWord());
        return $this->showDetails($request);
    }
}
