<?php

namespace Equit\Test;

use \PHPUnit\Framework\TestCase;

require_once "../includes/array.php";

class ArrayFunctionsTest extends TestCase {
	
	/**
	 * @dataProvider typicalRecursiveCountData
	 */
	public function testTypicalUsesOfRecursiveCountSucceed(array $data, int $expected) {
		$this->assertSame($expected, recursiveCount($data));
	}

	public function testRecursiveCountHandlesEmptyArray() {
		$arr = [];
		$this->assertSame(0, recursiveCount($arr));
	}

	public function testRecursiveCountHandlesArrayOfEmptyArrays() {
		$arr = [[],[],[]];
		$this->assertSame(0, recursiveCount($arr));
	}

	public function typicalRecursiveCountData() {
		return [
			// array to recursive count, expected count
			[[[1,2,3],[],[4,5]], 5],
			[[1,2,3,4,5,6,7,8,9,0], 10],
		];
	}

	/**
	 * @dataProvider typicalFlattenData
	 */
	public function testTypicalUsesOfFlattenSucceed(array $data, array $expected) {
		$this->assertEquals($expected, flatten($data));
	}

	public function testFlattenHandlesEmptyArray() {
		$arr = [];
		$this->assertEquals([], flatten($arr));
	}

	public function testFlattenHandlesArrayOfEmptyArrays() {
		$arr = [[],[],[]];
		$this->assertEquals([], flatten($arr));
	}

	public function typicalFlattenData() {
		return [
			// array to flatten, expected flattened array
			[
				[[1,2,3],[],[4,5]],
				[1,2,3,4,5]
			],
			[
				[1,2,3,4,5,6,7,8,9,0],
				[1,2,3,4,5,6,7,8,9,0],
			],
			[
				[[1,2,3,4,5],[6,7],[8,9]],
				[1,2,3,4,5,6,7,8,9],
			],
			[
				[[],[],[],[1,2,3,4,5,6,7,8,9]],
				[1,2,3,4,5,6,7,8,9],
			],
		];
	}
	
	/**
	 * @dataProvider typicalGrammaticalImplodeData
	 */
	public function testTypicalUsesOfGrammaticalImplodeSucceed(array $arr, string $glue, string $lastGlue, string $expected) {
		$this->assertSame($expected, grammaticalImplode($arr, $glue, $lastGlue));
	}

	public function testGrammaticalImplodeHandlesEmptyArray() {
		$arr = [];
		$this->assertSame("", grammaticalImplode($arr));
		$this->assertSame("", grammaticalImplode($arr, "; "));
		$this->assertSame("", grammaticalImplode($arr, "; ", "or"));
	}

	/**
	 * @dataProvider typicalGrammaticalImplodeDataForDefaultArgs
	 */
	public function testTypicalUsesOfGrammaticalImplodeWithDefaultArgsSucceed(array $arr, string $expected) {
		$this->assertSame($expected, grammaticalImplode($arr));
	}

	public function typicalGrammaticalImplodeData() {
		return [
			// array to implode, glue, last glue, expected string
			[
				["Hello", "Goodbye"], ",", " or ", "Hello or Goodbye"
			],
			[
				["Darren", "Junaid", "Susan", "Maximillian", "Iside"], "; ", " and ", "Darren; Junaid; Susan; Maximillian and Iside"
			],
		];
	}

	public function typicalGrammaticalImplodeDataForDefaultArgs() {
		return [
			// array to implode, expected string
			[
				["Hello", "Goodbye"], "Hello and Goodbye"
			],
			[
				["Darren", "Junaid", "Susan", "Maximillian", "Iside"], "Darren, Junaid, Susan, Maximillian and Iside"
			],
		];
	}
	
	/**
	 * @dataProvider typicalRemoveEmptyElementsData
	 */
	public function testTypicalUsesOfRemoveEmptyElementsSucceed(array $arr, array $expected) {
		removeEmptyElements($arr);
		$this->assertEquals(array_values($arr), array_values($expected));
	}

	public function testRemoveEmptyElementsHandlesEmptyArray() {
		$arr = [];
		removeEmptyElements($arr);
		$this->assertSame([], $arr);
	}

	public function testRemoveEmptyElementsHandlesArrayOfAllEmptyElements() {
		$arr = ["", "", "", ""];
		removeEmptyElements($arr);
		$this->assertSame([], $arr);
	}

	public function testRemoveEmptyElementsHandlesAllTypesOfEmpty() {
		$arr = ["", null];
		removeEmptyElements($arr);
		$this->assertSame([], $arr);
	}
	
	public function typicalRemoveEmptyElementsData() {
		return [
			// array to process, expected resulting array
			[[1, 2, 3], [1, 2, 3]],
			[["one", "two", "", "three"], ["one", "two", "three"]],
			[[1, "two", "", 3, 4, "five", "", 7], [1, "two", 3, 4, "five", 7]],
			[["one" => 1, "two" => 2, "three" => "", "four" => 4], [1, 2, 4]],
			[[0 => 1, 1 => 2, 2 => "three", 3 => "", 4 => 4], [0 => 1, 1 => 2, 2 => "three", 4 => 4]], 
		];
	}
}
