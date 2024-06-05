<?php

declare(strict_types=1);

namespace BeadTests\Email;

/** Assists testing headers by converting each one to an associative array that can tested using PHPUnit assertions. */
trait ConvertsHeadersToMaps
{
    /** Extract an array of headers to key-value pairs in an associative array. */
    private static function headersToAssociativeArray(array $headers): array
    {
        $arr = [];

        foreach ($headers as $header) {
            $arr[$header->name()] = $header->value();
        }

        return $arr;
    }
}
