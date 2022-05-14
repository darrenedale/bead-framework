<?php

/**
 * @file traversable.php
 * @author Darren Edale
 * @version 1.2.0
 * @date Jan 2018
 * @ingroup generic-includes
 *
 * @brief Polyfills and other useful functions to work with Traversable objects.
 *
 * These functions replciate and complement the array processing functions PHP provides.
 *
 * @package libequit
 */

namespace Equit\Traversable;

use Traversable;
use TypeError;

/**
 * Transform the entries in a traversable collection using a function.
 *
 * The function is applied to each entry in the Traversable collection. The item is modified in-place - the Traversable
 * will contain the transformed items after the call. The Traversable is also returned.
 *
 * @param array|\Traversable $collection The collection to transform.
 * @param callable $fn The function to use to transform the entries.
 *
 * @return \Traversable The transformed Traversable.
 */
function & transform(&$collection, callable $fn): Traversable
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));

    foreach ($collection as & $item) {
        $item = $fn($item);
    }

    return $collection;
}

/**
 * Reduce a Traversable collection by successive application of a function.
 *
 * The function receives each item in the traversable, along with the current reduced value. The reduced value is
 * updated to the return value of each call, and the final return value is the final reduced value.
 *
 * @param array|\Traversable $collection
 * @param callable $fn
 * @param $init
 *
 * @return mixed|null
 */
function reduce($collection, callable $fn, $init = null)
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));

    $ret = $init;

    foreach ($collection as $item) {
        $ret = $fn($item, $ret);
    }

    return $ret;
}

/**
 * Calculate the accumulation of a Traversable collection.
 *
 * The function receives each item in the traversable, along with the current accumulated value. YOu can provide a
 * callable to do the accumulation; if not, arithmetic addition is used.
 *
 * @param array|\Traversable $collection The collection to accumulate.
 * @param callable|null $accumulate An optional function to perform the accumulation. Defaults to arithmetic addition.
 * @param mixed $init The initial value to start the accumulation. Defaults to 0.
 *
 * @return mixed|null
 */
function accumulate($collection, callable $accumulate = null, $init = 0)
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));

    static $add = null;

    if (!isset($fn)) {
        if (!isset($add)) {
            $add = function ($a, $b) {
                return $a + $b;
            };
        }

		$accumulate = $add;
    }

    $ret = $init;

    foreach ($collection as $item) {
        $ret = $accumulate($item, $ret);
    }

    return $ret;
}

/**
 * Determine whether all items in a collection satisfy a predicate.
 *
 * @param array|\Traversable $collection The collection to test.
 * @param callable $predicate The predicate to test the collection with.
 *
 * @return bool `true` if all the items satisfy the predicate, false otherwise.
 */
function all($collection, callable $predicate): bool
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));

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
 * @param array|\Traversable $collection The collection to test.
 * @param callable $predicate The predicate to test the collection with.
 *
 * @return bool `true` if some of the items satisfy the predicate, false otherwise.
 */
function some($collection, callable $predicate): bool
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));

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
 * @param array|\Traversable $collection The collection to test.
 * @param callable $predicate The predicate to test the collection with.
 *
 * @return bool `true` if none of the items satisfy the predicate, false otherwise.
 */
function none($collection, callable $predicate): bool
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));

	foreach ($collection as $item) {
		if ($predicate($item)) {
			return false;
		}
	}

	return true;
}

function isSubsetOf($collection, $set): bool
{
	assert(is_array($collection) || $collection instanceof Traversable, new TypeError("\$collection to traverse is not an array or Traversable object."));
	assert(is_array($set) || $set instanceof Traversable, new TypeError("\$set is not an array or Traversable object."));

	foreach ($collection as $collectionItem) {
		$found = false;

		foreach ($set as $setItem) {
			if ($collectionItem === $setItem) {
				$found = true;
				break;
			}
		}

		if (!$found) {
			return false;
		}
	}

	return true;
}
