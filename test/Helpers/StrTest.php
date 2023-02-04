<?php

declare(strict_types=1);

namespace BeadTests\Helpers;

use BeadTests\Framework\TestCase;
use InvalidArgumentException;
use Exception;
use RuntimeException;
use TypeError;

use function Bead\Helpers\Str\camelToSnake;
use function Bead\Helpers\Str\snakeToCamel;
use function Bead\Helpers\Str\html;
use function Bead\Helpers\Str\build;
use function Bead\Helpers\Str\toCodePoints;
use function Bead\Helpers\Str\random;
use function range;
use function strlen;
use function strspn;
use function uopz_get_return;
use function uopz_set_return;
use function uopz_unset_return;

final class StrTest extends TestCase
{
	public function tearDown(): void
	{
		if (!is_null(uopz_get_return("random_bytes"))) {
			uopz_unset_return("random_bytes");
		}
	}

	public function dataForTestCamelToSnake(): iterable
	{
		yield from [
			"typicalNoChange" => ["foo", null, "foo",],
			"typicalSingleTransformation" => ["fooBar", null, "foo_bar",],
			"typicalMultipleComponents" => ["fooBarBazFizzBuzz", null, "foo_bar_baz_fizz_buzz",],
			"extremeEmpty" => ["", null, "",],
			"extremeWhitespace" => [" fooBar ", null, " foo_bar ",],
			"extremeConsecutiveUpperCase" => ["PickNMix", null, "pick_n_mix",],

			"typicalMultipleComponentsUtf16" => [
				// fooBarBazFizzBuzz
				"\x00\x66\x00\x6f\x00\x6f\x00\x42\x00\x61\x00\x72\x00\x42\x00\x61\x00\x7a\x00\x46\x00\x69\x00\x7a\x00\x7a\x00\x42\x00\x75\x00\x7a\x00\x7a",
				"UTF-16",
				// foo_bar_baz_fizz_buzz
				"\x00\x66\x00\x6f\x00\x6f\x00\x5f\x00\x62\x00\x61\x00\x72\x00\x5f\x00\x62\x00\x61\x00\x7a\x00\x5f\x00\x66\x00\x69\x00\x7a\x00\x7a\x00\x5f\x00\x62\x00\x75\x00\x7a\x00\x7a",
			],

			"invalidInt" => [42, null, "", TypeError::class,],
			"invalidFloat" => [3.1415927, null, "", TypeError::class,],
			"invalidBoolean" => [true, null, "", TypeError::class,],
			"invalidNull" => [null, null, "", TypeError::class,],
			"invalidStringable" => [
				new class
				{
					public function __toString(): string
					{
						return "fooBar";
					}
				},
				null,
				"",
				TypeError::class,
			],
			"invalidArray" => [["fooBar",], null, "", TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestCamelToSnake
	 *
	 * @param mixed $str The string to convert.
	 * @param mixed $encoding The character encoding of the string to convert.
	 * @param string $expected The expected snake_case string.
	 * @param string|null $exceptionClass The type of exception expected, if any.
	 */
	public function testCamelToSnake(mixed $str, mixed $encoding, string $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = camelToSnake($str, $encoding);
		$this->assertEquals($expected, $actual);
	}
	
	public function dataForTestSnakeToCamel(): iterable
	{
		yield from [
			"typicalNoChange" => ["foo", null, "foo",],
			"typicalSingleTransformation" => ["foo_bar", null, "fooBar",],
			"typicalMultipleComponents" => ["foo_bar_baz_fizz_buzz", null, "fooBarBazFizzBuzz",],
			"extremeEmpty" => ["", null, "",],
			"extremeWhitespace" => [" foo_bar ", null, " fooBar ",],
			"extremeConsecutiveUnderscores" => ["foo__bar", null, "fooBar",],
			"extremeLeadingUnderscores" => ["__foo_bar", null, "fooBar",],

			"typicalMultipleComponentsUtf16" => [
				// foo_bar_baz_fizz_buzz
				"\x00\x66\x00\x6f\x00\x6f\x00\x5f\x00\x62\x00\x61\x00\x72\x00\x5f\x00\x62\x00\x61\x00\x7a\x00\x5f\x00\x66\x00\x69\x00\x7a\x00\x7a\x00\x5f\x00\x62\x00\x75\x00\x7a\x00\x7a",
				"UTF-16",
				// fooBarBazFizzBuzz
				"\x00\x66\x00\x6f\x00\x6f\x00\x42\x00\x61\x00\x72\x00\x42\x00\x61\x00\x7a\x00\x46\x00\x69\x00\x7a\x00\x7a\x00\x42\x00\x75\x00\x7a\x00\x7a",
			],

			"invalidInt" => [42, null, "", TypeError::class,],
			"invalidFloat" => [3.1415927, null, "", TypeError::class,],
			"invalidBoolean" => [true, null, "", TypeError::class,],
			"invalidNull" => [null, null, "", TypeError::class,],
			"invalidStringable" => [
				new class
				{
					public function __toString(): string
					{
						return "foo_bar";
					}
				},
				null,
				"",
				TypeError::class,
			],
			"invalidArray" => [["foo_bar",], null, "", TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestSnakeToCamel
	 *
	 * @param mixed $str The string to convert.
	 * @param mixed $encoding The character encoding of the string to convert.
	 * @param string $expected The expected camelCase string.
	 * @param string|null $exceptionClass The type of exception expected, if any.
	 */
	public function testSnakeToCamel(mixed $str, mixed $encoding, string $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = snakeToCamel($str, $encoding);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * Test data for testHtml.
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestHtml(): iterable
	{
		yield from [
			"typicalNoEscaping" => ["foo", "foo",],
			"typicalCommonTag" => ["<div>", "&lt;div&gt;",],
			"typicalAmpersand" => ["Back & Forth", "Back &amp; Forth",],
			"typicalEuropeanCharacters" => [
				"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ",
				"&Agrave;&Aacute;&Acirc;&Atilde;&Auml;&Aring;&AElig;&Ccedil;&Egrave;&Eacute;&Ecirc;&Euml;&Igrave;&Iacute;&Icirc;&Iuml;&ETH;&Ntilde;&Ograve;&Oacute;&Ocirc;&Otilde;&Ouml;&Oslash;&Ugrave;&Uacute;&Ucirc;&Uuml;&Yacute;&THORN;&szlig;&agrave;&aacute;&acirc;&atilde;&auml;&aring;&aelig;&ccedil;&egrave;&eacute;&ecirc;&euml;&igrave;&iacute;&icirc;&iuml;&eth;&ntilde;&ograve;&oacute;&ocirc;&otilde;&ouml;&oslash;&ugrave;&uacute;&ucirc;&uuml;&yacute;&thorn;&yuml;",
			],
			"extremeEmpty" => ["", "",],
			"extremeMaliciousScript" => ["<script>window.location.href = \"http://phishing-site.com/\"</script>", "&lt;script&gt;window.location.href = &quot;http://phishing-site.com/&quot;&lt;/script&gt;",],

			"invalidInt" => [42, "", TypeError::class,],
			"invalidFloat" => [3.1415927, "", TypeError::class,],
			"invalidBoolean" => [true, "", TypeError::class,],
			"invalidNull" => [null, "", TypeError::class,],
			"invalidStringable" => [
				new class
				{
					public function __toString(): string
					{
						return "foo";
					}
				},
				"",
				TypeError::class,
			],
			"invalidArray" => [["foo",], "", TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestHtml
	 *
	 * @param mixed $raw The content to escape.
	 * @param string $expected The expected escaped content.
	 * @param string|null $exceptionClass The type of exception expected, if any.
	 */
	public function testHtml(mixed $raw, string $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = html($raw);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * Test data for testBuildString
	 * 
	 * @return iterable The test data.
	 */
	public function dataForTestBuild(): iterable
	{
		yield from [
			"typicalNoArgs" => ["foo", [], "foo",],
			"typicalOneArg" => ["Hello %1", ["Darren",], "Hello Darren",],
			"typicalOneIntArg" => ["Meaning: %1", [42,], "Meaning: 42",],
			"typicalOneFloatArg" => ["Pi: %1", [3.1415927,], "Pi: 3.1415927",],
			"typicalOneStringableArg" => [
				"User: %1",
				[
					new class
					{
						public function __toString(): string
						{
							return "Darren";
						}
					},
				],
				"User: Darren",
			],
			"typicalMultipleArgs" =>  ["%1, %2, %3", ["first", "second", "third",], "first, second, third",],
			"typicalReversedPositionalArgs" => ["Second: %2, First: %1", ["first-arg", "second-arg",], "Second: second-arg, First: first-arg",],

			"invalidIntTemplate" => [42, [], "", TypeError::class,],
			"invalidFloatTemplate" => [3.1415927, [], "", TypeError::class,],
			"invalidBooleanTemplate" => [true, [], "", TypeError::class,],
			"invalidNullTemplate" => [null, [], "", TypeError::class,],
			"invalidStringableTemplate" => [
				new class
				{
					public function __toString(): string
					{
						return "foo";
					}
				},
				[],
				"",
				TypeError::class,
			],
			"invalidArrayTemplate" => [["foo",], [], "", TypeError::class,],
		];
	}

	/**
	 * @dataProvider dataForTestBuild
	 *
	 * @param mixed $template The template string to build from.
	 * @param array $args The arguments for insertion into the template.
	 * @param string $expected The expected output string.
	 * @param string|null $exceptionClass The type of exception expected, if any.
	 */
	public function testBuild(mixed $template, array $args, string $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = build($template, ...$args);
		$this->assertEquals($expected, $actual);
	}

	public function dataForTestToCodePoints(): iterable
	{
		yield from [
			"typicalAscii" => ["ABCDEabcde", "UTF8", [65, 66, 67, 68, 69, 97, 98, 99, 100, 101],],
			"typicalEuropeanUtf8" => ["ÀÉÎÑÖÚàéîðöù", "UTF8", [0x000000c0, 0x000000c9, 0x000000ce, 0x000000d1, 0x000000d6, 0x000000da, 0x000000e0, 0x000000e9, 0x000000ee, 0x000000f0, 0x000000f6, 0x000000f9,],],
			// Hebrew Alef, Bet, Gimel, Dalet, He - UTF8 as hex to avoid bi-di text confusion
			"typicalHebrewUtf8" => ["\xd7\x90\xd7\x91\xd7\x92\xd7\x93\xd7\x94", "UTF8", [0x000005d0, 0x000005d1, 0x000005d2, 0x000005d3, 0x000005d4,],],
			// Arabic Theh, Jeem, Hah, Khah, Dal - UTF8 as hex to avoid bi-di text confusion
			"typicalArabicUtf8" => ["\xd8\xab\xd8\xac\xd8\xad\xd8\xae\xd8\xaf", "UTF8", [0x0000062b, 0x0000062c, 0x0000062d, 0x0000062e, 0x0000062f, ],],
		];
	}

	/**
	 * @dataProvider dataForTestToCodePoints
	 *
	 * @param mixed $str The string to convert.
	 * @param mixed $encoding The encoding of the string to convert.
	 * @param array $expected The expected set of codepoints.
	 * @param string|null $exceptionClass The type of exception expected, if any.
	 */
	public function testToCodePoints(mixed $str, mixed $encoding, array $expected, ?string $exceptionClass = null): void
	{
		if (isset($exceptionClass)) {
			$this->expectException($exceptionClass);
		}

		$actual = toCodePoints($str, $encoding);
		$this->assertEquals($expected, $actual);
	}

	/**
	 * Test data for testRandom()
	 *
	 * @return iterable The test data.
	 */
	public function dataForTestRandom(): iterable
	{
		foreach (range(1, 100) as $length) {
			yield [$length,];
		}
	}

	/**
	 * Ensures that the random strings are the expected length.
	 *
	 * @dataProvider dataForTestRandom
	 * @param int $length The random string length required.
	 */
	public function testRandomLength(int $length): void
	{
		$actual = random($length);
		$this->assertEquals($length, strlen($actual));
	}


	/**
	 * Ensures that the random strings contain only the characters stipulated in the function description.
	 *
	 * @dataProvider dataForTestRandom
	 * @param int $length The random string length required.
	 */
	public function testRandomContent(int $length): void
	{
		$actual = random($length);
		$this->assertEquals($length, strspn($actual, "abcdefghijklmnopqrstuvwxyz-_ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"));
	}

	public function testRandomThrowsWithInvalidLength(): void
	{
        if (1 !== ini_get("zend.assertions")) {
            $this->markTestSkipped("Assertions are not enabled, Str\\random() must fail an assertion for this test.");
        }

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Can't produce a random string of < 0 characters in length.");
		random(-1);
	}

    /** Ensure random() */
	public function testRandomIsCryptoSecure(): void
	{
		uopz_set_return(
			"random_bytes",
			function(int $length): string
			{
				throw new Exception("random_bytes() is not available.");
			},
			true
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Cryptographically-secure random strings are not available.");
		$bytes = random(40);
	}
}
