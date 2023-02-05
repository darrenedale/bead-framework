<?php

declare(strict_types=1);

namespace BeadTests\Polyfill;

use BeadTests\Framework\TestCase;

use function Bead\Polyfill\str_starts_with;
use function Bead\Polyfill\str_ends_with;
use function Bead\Polyfill\str_contains;

final class StringTest extends TestCase
{
    /**
     * Test data for testStrStartsWith().
     * 
     * @return iterable The test data.
     */
	public function dataForTestStrStartsWith(): iterable
	{
		yield "emptyStartsWithEmpty" => ["", "", true];
		yield "nonEmptyStartsWithEmpty" => ["hitch-hiker", "", true];
		yield "emptyStartsWithNonEmpty" => ["", "hitch-hiker", false];
		yield "nonEmptyStartsWithNonEmptyTrue" => ["hitch-hiker", "hitch", true];
		yield "nonEmptyStartsWithNonEmptyFalse" => ["hitch-hiker", "hiker", false];
		yield "nonEmptyStartsWithCharacterTrue" => ["hitch-hiker", "h", true];
		yield "nonEmptyStartsWithCharacterFalse" => ["hitch-hiker", "i", false];
		yield "needleLongerThanHaystack" => ["hitch-", "hitch-hiker", false];
	}

	/**
	 * @dataProvider dataForTestStrStartsWith
	 *
	 * @param array $haystack The haystack string to test with.
	 * @param array $needle The needle string to test with.
	 * @param bool $expected The expected result.
	 */
	public function testStrStartsWith(string $haystack, string $needle, bool $expected): void
	{
		self::assertEquals($expected, str_starts_with($haystack, $needle));
	}
    
    /**
     * Test data for testStrEndsWith().
     * 
     * @return iterable The test data.
     */
	public function dataForTestStrEndsWith(): iterable
	{
		yield "emptyEndsWithEmpty" => ["", "", true];
		yield "nonEmptyEndsWithEmpty" => ["hitch-hiker", "", true];
        yield "emptyEndsWithNonEmpty" => ["", "hitch-hiker", false];
		yield "nonEmptyEndsWithNonEmptyTrue" => ["hitch-hiker", "hiker", true];
		yield "nonEmptyEndsWithNonEmptyFalse" => ["hitch-hiker", "hitch", false];
		yield "nonEmptyEndsWithCharacterTrue" => ["hitch-hiker", "r", true];
		yield "nonEmptyEndsWithCharacterFalse" => ["hitch-hiker", "e", false];
		yield "needleLongerThanHaystack" => ["hitch-", "hitch-hiker", false];
	}

	/**
	 * @dataProvider dataForTestStrEndsWith
	 *
	 * @param array $haystack The haystack string to test with.
	 * @param array $needle The needle string to test with.
	 * @param bool $expected The expected result.
	 */
	public function testStrEndsWith(string $haystack, string $needle, bool $expected): void
	{
		self::assertEquals($expected, str_ends_with($haystack, $needle));
	}

    /**
     * Test data for testStrContains().
     * 
     * @return iterable The test data.
     */
	public function dataForTestStrContains(): iterable
	{
		yield "emptyContainsEmpty" => ["", "", true];
		yield "nonEmptyContainsEmpty" => ["hitch-hiker", "", true];
        yield "emptyContainsNonEmpty" => ["", "hitch-hiker", false];
		yield "nonEmptyContainsNonEmptyTrue" => ["hitch-hiker", "itch-hi", true];
		yield "nonEmptyContainsNonEmptyFalse" => ["hitch-hiker", "itch-hip", false];
		yield "nonEmptyContainsCharacterTrue" => ["hitch-hiker", "-", true];
		yield "nonEmptyContainsCharacterTrueStart" => ["hits-liker", "h", true];
		yield "nonEmptyContainsCharacterTrueEnd" => ["hitch-hiker", "r", true];
		yield "nonEmptyContainsCharacterFalse" => ["hitch-hiker", "a", false];
		yield "needleLongerThanHaystack" => ["hitch-", "hitch-hiker", false];
	}

	/**
	 * @dataProvider dataForTestStrContains
	 *
	 * @param array $haystack The haystack string to test with.
	 * @param array $needle The needle string to test with.
	 * @param bool $expected The expected result.
	 */
	public function testStrContains(string $haystack, string $needle, bool $expected): void
	{
		self::assertEquals($expected, str_contains($haystack, $needle));
	}
}
