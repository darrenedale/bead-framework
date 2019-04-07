<?php

/**
 * @file array.php
 * @author Darren Edale
 * @version 1.1.2
 * @date Jan 2018May 2014
 * @ingroup generic-includes
 *
 * @brief Definitions of stand-alone array-processing functions.
 *
 * These functions complement the array processing functions PHP provides.
 *
 * @package libequit
 */

/**
 * Recursively count the items in an array or object.
 *
 * If any element/property of the passed array/object is itself an array/object, that item is recursively counted and
 * its count is added to the result. One upshot of this is that any array item that is an empty array will contribute
 * 0 to the final count of the array. This is in contrast to count(), which will see an empty array item as a single
 * item in an array and add 1 to the count, just like any other item in the array.
 *
 * The purpose of this is to aggregate the number of items in multi-dimensional arrays.
 *
 * @param $arr array|object The array/object to count.
 *
 * @return int The recursive count.
 */
function recursiveCount($arr): int {
	$ret = 0;

	foreach($arr as $v) {
		if(is_array($v) || is_object($v)) {
			$ret += recursiveCount($v);
		}
		else {
			++$ret;
		}
	}

	return $ret;
}


/**
 * Flatten an array to a single dimension.
 *
 * Any items in the provided array that are themselves arrays have their
 * items added to the return array. This is done recursively such that
 * the final result is a single array that contains all the items of
 * all arrays contained within.
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
 * @param $arr array The array to flatten.
 *
 * @return array The flattened array.
 */
function flatten(array $arr): array {
	$ret = [];

	foreach($arr as $v) {
		if(is_array($v)) {
			$ret = array_merge($ret, flatten($v));
		}
		else {
			$ret[] = $v;
		}
	}

	return $ret;
}


/**
 * Implode an array into a grammatically correct list in a `string`.
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
 * @param $array array The array to implode.
 * @param $glue string The glue to use between list elements.
 * @param $lastGlue string The glue to use between the penultimate and last list elements.
 *
 * @return string the imploded array.
 */
function grammaticalImplode(array $array, string $glue = ", ", string $lastGlue = " and "): string {
	$n = count($array);

	if(0 == $n) {
		return "";
	}

	if(1 == $n) {
		return "{$array[0]}";
	}

	$last = array_pop($array);
	return implode($glue, $array) . "$lastGlue$last";
}



/**
 * Remove empty entries from an array.
 *
 * The array is processed _in situ_ - the array is received by reference and the entries are removed from it directly.
 * There is no need to assign the result (indeed there is no result to assign).  Array keys, including numeric keys,
 * are *not* modified.
 *
 * @param $arr array The array from which to remove empty entries.
 */
function removeEmptyElements( & $arr ) {
	$arr = array_filter($arr, function($val) { return "" != "$val"; });
}
