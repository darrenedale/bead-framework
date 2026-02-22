<?php

declare(strict_types=1);

namespace BeadTests\Helpers;

use BeadTests\Framework\TestCase;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

use function Bead\Helpers\Str\attr;
use function Bead\Helpers\Str\build;
use function Bead\Helpers\Str\camelToKebab;
use function Bead\Helpers\Str\camelToSnake;
use function Bead\Helpers\Str\html;
use function Bead\Helpers\Str\kebabToCamel;
use function Bead\Helpers\Str\random;
use function Bead\Helpers\Str\scrub;
use function Bead\Helpers\Str\snakeToCamel;
use function Bead\Helpers\Str\toCodePoints;
use function range;
use function strlen;
use function strspn;

final class StrTest extends TestCase
{
    /** Provides camelCase strings in various encodings and their expected snake_case representation. */
    public static function providerTestCamelToSnake(): iterable
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
        ];
    }

    /** Provides snake_case strings in various encodings and their expected camelCase representation. */
    public static function providerTestSnakeToCamel(): iterable
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
        ];
    }
    /** Provides camelCase strings in various encodings and their expected kebab-case representation. */
    public static function providerTestCamelToKebab(): iterable
    {
        yield from [
            "typicalNoChange" => ["foo", null, "foo",],
            "typicalSingleTransformation" => ["fooBar", null, "foo-bar",],
            "typicalMultipleComponents" => ["fooBarBazFizzBuzz", null, "foo-bar-baz-fizz-buzz",],
            "extremeEmpty" => ["", null, "",],
            "extremeWhitespace" => [" fooBar ", null, " foo-bar ",],
            "extremeConsecutiveUpperCase" => ["PickNMix", null, "pick-n-mix",],

            "typicalMultipleComponentsUtf16" => [
                // fooBarBazFizzBuzz
                "\x00\x66\x00\x6f\x00\x6f\x00\x42\x00\x61\x00\x72\x00\x42\x00\x61\x00\x7a\x00\x46\x00\x69\x00\x7a\x00\x7a\x00\x42\x00\x75\x00\x7a\x00\x7a",
                "UTF-16",
                // foo-bar-baz-fizz-buzz
                "\x00\x66\x00\x6f\x00\x6f\x00\x2d\x00\x62\x00\x61\x00\x72\x00\x2d\x00\x62\x00\x61\x00\x7a\x00\x2d\x00\x66\x00\x69\x00\x7a\x00\x7a\x00\x2d\x00\x62\x00\x75\x00\x7a\x00\x7a",
            ],
        ];
    }

    /** Provides kebab-case strings in various encodings and their expected camelCase representation. */
    public static function providerTestKebabToCamel(): iterable
    {
        yield from [
            "typicalNoChange" => ["foo", null, "foo",],
            "typicalSingleTransformation" => ["foo-bar", null, "fooBar",],
            "typicalMultipleComponents" => ["foo-bar-baz-fizz-buzz", null, "fooBarBazFizzBuzz",],
            "extremeEmpty" => ["", null, "",],
            "extremeWhitespace" => [" foo-bar ", null, " fooBar ",],
            "extremeConsecutiveUnderscores" => ["foo--bar", null, "fooBar",],
            "extremeLeadingUnderscores" => ["--foo-bar", null, "fooBar",],

            "typicalMultipleComponentsUtf16" => [
                // foo-bar-baz-fizz-buzz
                "\x00\x66\x00\x6f\x00\x6f\x00\x2d\x00\x62\x00\x61\x00\x72\x00\x2d\x00\x62\x00\x61\x00\x7a\x00\x2d\x00\x66\x00\x69\x00\x7a\x00\x7a\x00\x2d\x00\x62\x00\x75\x00\x7a\x00\x7a",
                "UTF-16",
                // fooBarBazFizzBuzz
                "\x00\x66\x00\x6f\x00\x6f\x00\x42\x00\x61\x00\x72\x00\x42\x00\x61\x00\x7a\x00\x46\x00\x69\x00\x7a\x00\x7a\x00\x42\x00\x75\x00\x7a\x00\x7a",
            ],
        ];
    }

    /**
     * Test data for testAttr.
     *
     * @return iterable The test data.
     */
    public static function providerTestAttr(): iterable
    {
        yield from [
            "typicalNoEscaping" => ["foo", "foo",],
            "bothTypesOfQuotes" => ["\"''\"", "&quot;&apos;&apos;&quot;",],
            "ampersand-not-escaped" => ["a \"quoted\" & unquoted value", "a &quot;quoted&quot; & unquoted value",],
        ];
    }

    /**
     * Test data for testHtml.
     *
     * @return iterable The test data.
     */
    public static function providerTestHtml(): iterable
    {
        yield from [
            "typicalNoEscaping" => ["foo", "foo",],
            "bothTypesOfQuotes" => ["\"''\"", "&quot;&apos;&apos;&quot;",],
            "typicalCommonTag" => ["<div>", "&lt;div&gt;",],
            "typicalAmpersand" => ["Back & Forth", "Back &amp; Forth",],
            "typicalEuropeanCharacters" => [
                "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ",
                "&Agrave;&Aacute;&Acirc;&Atilde;&Auml;&Aring;&AElig;&Ccedil;&Egrave;&Eacute;&Ecirc;&Euml;&Igrave;&Iacute;&Icirc;&Iuml;&ETH;&Ntilde;&Ograve;&Oacute;&Ocirc;&Otilde;&Ouml;&Oslash;&Ugrave;&Uacute;&Ucirc;&Uuml;&Yacute;&THORN;&szlig;&agrave;&aacute;&acirc;&atilde;&auml;&aring;&aelig;&ccedil;&egrave;&eacute;&ecirc;&euml;&igrave;&iacute;&icirc;&iuml;&eth;&ntilde;&ograve;&oacute;&ocirc;&otilde;&ouml;&oslash;&ugrave;&uacute;&ucirc;&uuml;&yacute;&thorn;&yuml;",
            ],
            "extremeEmpty" => ["", "",],
            "extremeMaliciousScript" => ["<script>window.location.href = \"http://phishing-site.com/\"</script>", "&lt;script&gt;window&period;location&period;href &equals; &quot;http&colon;&sol;&sol;phishing-site&period;com&sol;&quot;&lt;&sol;script&gt;",],
        ];
    }

    /**
     * Test data for testBuildString
     *
     * @return iterable The test data.
     */
    public static function providerTestBuild(): iterable
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
        ];
    }

    public static function providerTestToCodePoints(): iterable
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

    /** Provides lengths of random data to generate. */
    public static function providerTestRandom(): iterable
    {
        foreach (range(1, 100) as $length) {
            yield [$length,];
        }
    }

    public static function providerTestScrub1(): iterable
    {
        yield "empty" => ["", []];
        yield "char" => ["a", [66]];
        yield "text" => ["lorum ipsum dolor sit amet", [228, 211, 102, 148, 110, 100, 185, 11, 60, 122, 148, 116, 121, 5, 161, 86, 64, 57, 138, 120, 240, 181, 129, 141, 231, 19, ]];
        yield "whitespace" => ["  ", [13, 28]];
        yield "nulls" => ["\0\0\0\0\0", [75, 9, 14, 81, 209]];
        yield "binary" => ["\x89\x50\x4e\x47\x0d\x0a\x1a\x0a\x00\x00\x00\x8d", [67, 32, 55, 80, 52, 200, 245, 11, 43, 178, 239, 12]];
    }

    /**
     * @param mixed $str The string to convert.
     * @param mixed $encoding The character encoding of the string to convert.
     * @param string $expected The expected snake_case string.
     */
    #[DataProvider("providerTestCamelToSnake")]
    public function testCamelToSnake1(mixed $str, mixed $encoding, string $expected): void
    {
        self::assertEquals($expected, camelToSnake($str, $encoding));
    }

    /**
     * @param mixed $str The string to convert.
     * @param mixed $encoding The character encoding of the string to convert.
     * @param string $expected The expected camelCase string.
     */
    #[DataProvider("providerTestSnakeToCamel")]
    public function testSnakeToCamel1(mixed $str, mixed $encoding, string $expected): void
    {
        self::assertEquals($expected, snakeToCamel($str, $encoding));
    }

    /**
     * @param mixed $str The string to convert.
     * @param mixed $encoding The character encoding of the string to convert.
     * @param string $expected The expected kebab-case string.
     */
    #[DataProvider("providerTestCamelToKebab")]
    public function testCamelToKebab1(mixed $str, mixed $encoding, string $expected): void
    {
        self::assertEquals($expected, camelToKebab($str, $encoding));
    }

    /**
     * @param mixed $str The string to convert.
     * @param mixed $encoding The character encoding of the string to convert.
     * @param string $expected The expected camelCase string.
     */
    #[DataProvider("providerTestKebabToCamel")]
    public function testkebabToCamel1(mixed $str, mixed $encoding, string $expected): void
    {
        self::assertEquals($expected, kebabToCamel($str, $encoding));
    }

    /**
     * @param mixed $raw The content to escape.
     * @param string $expected The expected escaped content.
     * @param string|null $exceptionClass The type of exception expected, if any.
     */
    #[DataProvider("providerTestAttr")]
    public function testAttr1(mixed $raw, string $expected, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $actual = attr($raw);
        self::assertEquals($expected, $actual);
    }

    /**
     * @param mixed $raw The content to escape.
     * @param string $expected The expected escaped content.
     * @param string|null $exceptionClass The type of exception expected, if any.
     */
    #[DataProvider("providerTestHtml")]
    public function testHtml(mixed $raw, string $expected, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }

        $actual = html($raw);
        self::assertEquals($expected, $actual);
    }

    /**
     * @param mixed $template The template string to build from.
     * @param array $args The arguments for insertion into the template.
     * @param string $expected The expected output string.
     */
    #[DataProvider("providerTestBuild")]
    public function testBuild(mixed $template, array $args, string $expected): void
    {
        self::assertEquals($expected, build($template, ...$args));
    }

    /**
     * @param mixed $str The string to convert.
     * @param mixed $encoding The encoding of the string to convert.
     * @param array $expected The expected set of codepoints.
     */
    #[DataProvider("providerTestToCodePoints")]
    public function testToCodePoints(mixed $str, mixed $encoding, array $expected): void
    {
        self::assertEquals($expected, toCodePoints($str, $encoding));
    }

    /**
     * Ensures that the random strings are the expected length.
     *
     * @param int $length The random string length required.
     */
    #[DataProvider("providerTestRandom")]
    public function testRandom1(int $length): void
    {
        self::assertEquals($length, strlen(random($length)));
    }

    /**
     * Ensures that the random strings contain only the characters stipulated in the function description.
     *
     * @param int $length The random string length required.
     */
    #[DataProvider("providerTestRandom")]
    public function testRandom2(int $length): void
    {
        self::assertEquals($length, strspn(random($length), "abcdefghijklmnopqrstuvwxyz-_ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"));
    }

    /** Ensure random() throws with an invalid length. */
    public function testRandom3(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Can't produce a random string of < 1 character in length.");
        random(-1);
    }

    /** Ensure random() throws when cryptographically secure random data is not available. */
    public function testRandom4(): void
    {
        $this->mockFunction(
            "random_bytes",
            function (int $length): string {
                throw new Exception("random_bytes() is not available.");
            }
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cryptographically-secure random strings are not available.");
        random(40);
    }

    /**
     * Ensure scrub replaces all of a string's content with random bytes.
     */
    #[DataProvider("providerTestScrub1")]
    public function testScrub1(string $str, array $randomBytes): void
    {
        $expected = array_reduce(
            $randomBytes,
            static fn (string $carry, int $byte): string => chr($byte) . $carry,
            "",
        );

        $this->mockFunction("rand", function (int $lower, int $upper) use (&$randomBytes): int {
            StrTest::assertGreaterThan(0, count($randomBytes));
            StrTest::assertEquals(0, $lower);
            StrTest::assertEquals(255, $upper);
            return array_shift($randomBytes);
        });

        $length = strlen($str);
        $original = $str;
        scrub($str);
        self::assertEquals($length, strlen($str));
        self::assertEquals($expected, $str);

        // random byte stream is same size as string, this proves rand() is called for every byte in the string
        self::assertCount(0, $randomBytes);

        // test data ensures no byte in the string should remain the same, this proves scrub() overwrites every byte
        for ($idx = 0; $idx < $length; ++$idx) {
            self::assertNotEquals($original[$idx], $str[$idx]);
        }
    }
}
