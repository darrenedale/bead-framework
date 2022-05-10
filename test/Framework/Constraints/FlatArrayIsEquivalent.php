<?php

namespace Equit\Test\Framework\Constraints;

use PHPUnit\Framework\Constraint\Constraint;

/**
 * Constraint class to compare (flat) arrays for equivalence.
 *
 * Flat arrays are single-dimensional arrays where only content is important. Keys are not important and order of
 * elements is not important. If two arrays contain identical content, regardless of the order in which that content
 * is arranged, they are considered equivalent.
 *
 * Only scalar values are supported and multi-dimensional arrays are also not supported.
 *
 * @package Equit\Test\Constraints
 */
class FlatArrayIsEquivalent extends Constraint {
	/**
	 * Initialise a new instance of the constraint.
	 *
	 * @param array $arr The original array against which others will be matched.
	 */
	public function __construct(array $arr) {
		foreach($arr as $item) {
			if(!is_scalar($item)) {
				throw new \InvalidArgumentException("only flat arrays of scalar types can be used with this constraint");
			}

			if(!isset($this->m_frequencyMap[$item])) {
				$this->m_frequencyMap[$item] = self::countFrequency($item, $arr);
				$this->m_count += $this->m_frequencyMap[$item];
			}
		}
	}

	private static function countFrequency($item, array $arr): int {
		$freq = 0;

		foreach($arr as $arrayItem) {
			if($arrayItem === $item) {
				++$freq;
			}
		}

		return $freq;
	}

	/**
	 * Check that an array is equivalent to our original.
	 *
	 * @param mixed $other The array to compare to the original.
	 *
	 * @return bool `true` if they are equivalent, `false` if not.
	 */
	public function matches($other): bool {
		if($this->m_count != count($other)) {
			return false;
		}

		foreach($other as $item) {
			if(!isset($this->m_frequencyMap[$item])) {
				// $other contains an item not found in the original
				return false;
			}

			if($this->m_frequencyMap[$item] != self::countFrequency($item, $other)) {
				// the number of $item elements in $other is not the same as the original
				return false;
			}
		}

		// note if $other is missing one or more elements from the original then the count at the start will be off OR
		// one of the item counts will be off, so we've definitely already caught that case without having to explicitly
		// check for it
		return true;
	}

	public function toString(): string {
		return "arrays are equivalent";
	}

	private $m_frequencyMap = [];
	private $m_count = 0;
}
