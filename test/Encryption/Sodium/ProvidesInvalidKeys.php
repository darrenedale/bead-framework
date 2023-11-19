<?php

declare(strict_types=1);

namespace BeadTests\Encryption\Sodium;

/** Trait used by several tests to provide invalid keys for Sodium encryption/decryption. */
trait ProvidesInvalidKeys
{
    public static function invalidKeys(): iterable
    {
        yield "empty" => [""];
        yield "marginally too short" => ["some-insecure-key-insecure-some"];
        yield "marginally too long" => ["-some-insecure-key-insecure-some-"];
    }
}
