<?php

namespace Bead\Helpers\Iterable;

/**
 * Flatten an array to a single dimension.
 *
 * Any items in the provided array that are themselves arrays have their items added to the return array. This is done
 * recursively such that the final result is a single array that contains all the items of all arrays contained within.
 *
 * An example. Given
 *
 * ~~~
 * [ 10, 20, 30, [ 33, [ 36, 37, 38], 39 ], 40, 50 ]
 * ~~~
 *
 * The result will be
 *
 * ~~~
 * [ 10, 20, 30, 33, 36, 37, 38, 39, 40, 50 ]
 * ~~~
 *
 * @param iterable $arr The iterable to flatten. It will be fully traversed from start to finish, so for one-time
 * iterables (e.g. Generator instances) ensure it's not been traversed already and that you won't want to iterate it
 * after calling `flatten()`;
 *
 * @return array The flattened array of items in the provided iterable.
 */
function flatten(iterable $collection): array
{
    $flat = [];

    foreach ($collection as $item) {
        if (is_iterable($item)) {
            $flat = [...$flat, ...flatten($item)];
        } else {
            $flat[] = $item;
        }
    }

    return $flat;
}

/**
 * Convert an iterable to an array.
 *
 * The iterable will be fully traversed and its items returned in the same sequence in an array. If the iterable is
 * already an array it is returned as-is, without traversal.
 *
 * @param iterable $collection The iterable to convert.
 *
 * @return array The collection rendered to an array.
 */
function toArray(iterable $collection): array
{
    if (is_array($collection)) {
        return $collection;
    }

    $ret = [];

    foreach ($collection as $item) {
        $ret[] = $item;
    }

    return $ret;
}

/**
 * PHP's built-in implode() function, but for all iterables, not just arrays.
 *
 * @param string $glue The glue to use to join items in the iterable.
 * @param iterable $collection The iterable to implode.
 *
 * @return string The imploded iterable.
 */
function implode(string $glue, iterable $collection): string
{
    $first = true;

    return reduce($collection, function ($item, $reduction) use ($glue, & $first): string {
        if ($first) {
            $first = false;
            return (string) $item;
        }

        return "{$reduction}{$glue}{$item}";
    }) ?? "";
}

/**
 * Implode an iterable into a grammatically correct list in a `string`.
 *
 * This function turns an array of strings into text suitable for insertion into ordinary prose (such as error
 * messages). Given the array `["Red", "Black", "Green"]` it will return the string `"Red, Black and Green"`. If the
 * array contains no items an empty string is returned; if it contains one item, the string representation of that item
 * is returned.
 *
 * The default glue is `", "`; the default last glue is `" and "`. Spaces must be provided in the glue if they are
 * required, this will not automatically be done. To support other languages and other meanings, provide the appropriate
 * glue (e.g. `" "` and `" or "`).
 *
 * @param $collection iterable<string> The iterable to implode.
 * @param $glue string The glue to use between list elements.
 * @param $lastGlue string The glue to use between the penultimate and last list elements.
 *
 * @return string the imploded array.
 */
function grammaticalImplode(iterable $collection, string $glue = ", ", string $lastGlue = " and "): string
{
    $collection = toArray($collection);
    $nItems = count($collection);

    if (0 == $nItems) {
        return "";
    }

    if (1 == $nItems) {
        return "{$collection[0]}";
    }

    $lastItem = array_pop($collection);
    return implode($glue, $collection) . "{$lastGlue}{$lastItem}";
}

/**
 * Transform the entries in an iterable using a function.
 *
 * The function is applied to each entry in the iterable. The item is modified in-place - the iterable will contain the
 * transformed items after the call. The iterable is also returned. For iterables that don't actually store their items
 * (e.g. Generator instances), this function is of little use - extract the items to a storage-oriented iterable - such
 * as an array - first.
 *
 * Note that Iterator instances cannot be used since they are not traversable by reference.
 *
 * @param iterable & $collection The collection to transform.
 * @param callable $fn The function to use to transform the entries.
 *
 * @return iterable The transformed iterable.
 */
function & transform(iterable & $collection, callable $fn): iterable
{
    foreach ($collection as & $item) {
        $item = $fn($item);
    }

    return $collection;
}

/**
 * Map the items in an iterable, using a trasnforamtion function.
 *
 * Similar to transform, except this is not in-place, and yields each transformed item in sequence.
 *
 * @param iterable $collection The collection from which to map items.
 * @param callable $fn The transformation function to use on each item.
 *
 * @return iterable The mapped items.
 */
function map(iterable $collection, callable $fn): iterable
{
    foreach ($collection as $item) {
        yield $fn($item);
    }
}

/**
 * Reduce an iterable to a single value by successive application of a function.
 *
 * The function receives each item in the iterable, along with the current reduced value. The reduced value is
 * updated to the return value of each call, and the final return value is the final reduced value.
 *
 * @template T
 *
 * @param iterable $collection The collection to accumulate
 * @param callable $fn The function to perform the reduction.
 * @param T $init The initial value to start the reduction. Defaults to 0.
 *
 * @return T|null
 */
function reduce(iterable $collection, callable $fn, $init = null)
{
    $reduction = $init;

    foreach ($collection as $item) {
        $reduction = $fn($item, $reduction);
    }

    return $reduction;
}

/**
 * Calculate the accumulation of an iterable.
 *
 * The function receives each item in the iterable, along with the current accumulated value. You can provide a
 * callable to do the accumulation; if not, arithmetic addition is used.
 *
 * @template T
 *
 * @param iterable $collection The collection to accumulate.
 * @param callable|null $accumulator An optional function to perform the accumulation. Defaults to arithmetic addition.
 * @param T $init The initial value to start the accumulation. Defaults to 0.
 *
 * @return T
 */
function accumulate(iterable $collection, ?callable $accumulator = null, $init = 0)
{
    static $add = null;

    if (!isset($accumulator)) {
        if (!isset($add)) {
            $add = fn ($a, $b): int => $a + $b;
        }

        $accumulator = $add;
    }

    $accumulation = $init;

    foreach ($collection as $item) {
        $accumulation = $accumulator($item, $accumulation);
    }

    return $accumulation;
}

/**
 * Determine whether all items in a collection satisfy a predicate.
 *
 * @param iterable $collection The collection to test.
 * @param callable $predicate The predicate to test the collection with.
 *
 * @return bool `true` if all the items satisfy the predicate, false otherwise.
 */
function all(iterable $collection, callable $predicate): bool
{
    foreach ($collection as $item) {
        if (!$predicate($item)) {
            return false;
        }
    }

    return true;
}

/**
 * Determine whether one or more items in a collection satisfy a predicate.
 *
 * @param iterable $collection The collection to test.
 * @param callable $predicate The predicate to test the collection with.
 *
 * @return bool `true` if some of the items satisfy the predicate, false otherwise.
 */
function some(iterable $collection, callable $predicate): bool
{
    foreach ($collection as $item) {
        if ($predicate($item)) {
            return true;
        }
    }

    return false;
}

/**
 * Determine whether no items in a collection satisfy a predicate.
 *
 * @param iterable $collection The collection to test.
 * @param callable $predicate The predicate to test the collection with.
 *
 * @return bool `true` if none of the items satisfy the predicate, false otherwise.
 */
function none(iterable $collection, callable $predicate): bool
{
    foreach ($collection as $item) {
        if ($predicate($item)) {
            return false;
        }
    }

    return true;
}

/**
 * Determine whether one iterable is a subset of another.
 *
 * A subset is composed exclusively of items that also exist in the (super)set.
 *
 * @param iterable $collection The putative subset.
 * @param iterable $set The set it must be a subset of. This must be a repeatable iterable (not a Generator instance,
 * for example).
 *
 * @return bool `true` if it's a subset, `false` if not.
 */
function isSubsetOf(iterable $collection, iterable $set): bool
{
    return all($collection, function ($item) use ($set): bool {
        foreach ($set as $setItem) {
            if ($item === $setItem) {
                return true;
            }
        }

        return false;
    });
}

/**
 * Recursively count the items in an iterable.
 *
 * If any element of the passed iterable is itself an iterable, that iterable is recursively counted and its count is
 * added to the result. One upshot of this is that any item that is an empty iterable will contribute 0 to the final
 * count of the array. This is in contrast to count(), which will see an item that is an empty iterable as a single item
 * and add 1 to the count, just like any other item in the iterable.
 *
 * @param $collection iterable The iterable to count.
 *
 * @return int The recursive count.
 */
function recursiveCount(iterable $collection): int
{
    $count = 0;

    foreach ($collection as $item) {
        if (is_iterable($item)) {
            $count += recursiveCount($item);
        } else {
            ++$count;
        }
    }

    return $count;
}
