<?php

declare(strict_types=1);

namespace Bead\Helpers\Str;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use SplFixedArray;

use function array_map;
use function base64_encode;
use function count;
use function htmlentities;
use function mb_convert_encoding;
use function mb_ereg_replace_callback;
use function mb_regex_encoding;
use function mb_strtolower;
use function mb_strtoupper;
use function mb_substr;
use function min;
use function random_bytes;
use function sprintf;
use function str_replace;
use function str_split;
use function substr;
use function unpack;

/**
 * Convert a camelCase string to snake_case.
 *
 * The conversion is multibyte-safe. It is the caller's responsibility to be sure that the provided string is camel
 * cased. If it isn't, GIGO.
 *
 * @param string $str The string to convert.
 * @param string|null $encoding The encoding to use.
 *
 * @return string The converted string.
 */
function camelToSnake(string $str, ?string $encoding = null): string
{
	if(empty($str)) {
		return $str;
	}

	if (isset($encoding)) {
		$oldEncoding = mb_regex_encoding();
		mb_regex_encoding($encoding);
	}

	$pattern = "([[:upper:]])";
	$replacement = "_";

	if (isset($encoding) && "UTF-8" !== $encoding) {
		$pattern = mb_convert_encoding($pattern, $encoding, "UTF-8");
		$replacement = mb_convert_encoding($replacement, $encoding, "UTF-8");
	}

	# use mb_substr to get first char as it could be multibyte
	$ret =
		mb_strtolower(
			mb_substr($str, 0, 1, $encoding),
			$encoding
		) .
		mb_strtolower(mb_ereg_replace_callback(
			$pattern,
			function(array $matches) use ($replacement): string
			{
				return "{$replacement}{$matches[1]}";
			},
			mb_substr($str, 1, null, $encoding),
		), $encoding);

	if (isset($oldEncoding)) {
		mb_regex_encoding($oldEncoding);
	}

	return $ret;
}

/**
 * Convert a snake-case string to camel case.
 *
 * The string is expected to be lower-case and punctuated with single _ characters between words. If this is not the
 * form of the string passed in, you may not get the expected results.
 *
 * @param string $str The snake-case string to convert to camel case.
 * @param string|null $encoding The encoding expected in the string. If not given, UTF-8 is used.
 *
 * @return string The `camelCase` version of the `snake_case` string.
 */
function snakeToCamel(string $str, ?string $encoding = null): string
{
	if (isset($encoding)) {
		$oldEncoding = mb_regex_encoding();
		mb_regex_encoding($encoding);
	}

	// ignore all leading _ chars (to avoid upper-casing the first non-underscore)
	$trim = 0;

	while ($trim < (strlen($str) - $trim) && "_" === $str[$trim]) {
		++$trim;
	}

	$pattern = "_+(.)";

	if (isset($encoding) && "UTF-8" !== $encoding) {
		$pattern = mb_convert_encoding($pattern, $encoding, "UTF-8");
	}

	$ret = mb_ereg_replace_callback($pattern, function (array $matches) use ($encoding): string {
		return mb_strtoupper($matches[1], $encoding ?? "UTF-8");
	}, mb_substr($str, $trim));

	if (isset($oldEncoding)) {
		mb_regex_encoding($oldEncoding);
	}

	return $ret;
}

/**
 * Escape some content for inclusion in the page.
 *
 * Any characters in the string that have syntactic meaning in HTML are escaped such that they will be interpreted by
 * the user agent as a normal text character rather than something meaningful in HTML. The content provided must be
 * UTF-8 encoded, and the returned, escaped, content will be UTF-8 encoded too.
 *
 * @param $str string The content to escape.
 *
 * @return string The escaped content.
 */
function html(string $str)
{
	return htmlentities($str, ENT_COMPAT, "UTF-8");
}

/**
 * Build a string based on a template and data to insert into it.
 *
 * This method uses placeholders starting with a '\%' character, followed by an index number starting at 1 for the
 * first argument and incrementing by one for each subsequent argument up to the total number of arguments in the
 * string.
 *
 * For example, given the string "Hello %1, my name is %2" and the extra arguments "Darren" and "Susan", this method
 * will return the string "Hello Darren, my name is Susan".
 *
 * This method is used by the translation function tr() to automatically insert data into translated strings.
 *
 * @param string $template The template to process.
 * @param mixed[] ...$args The values to insert into the template.
 *
 * @return string The populated string.
 */
function build(string $template, mixed ... $args): string
{
	$argc = count($args);

	if (0 == $argc) {
		/* avoid pointlessly executing the rest */
		return $template;
	}

	/* set up the array of placeholders */
	$placeholders = new SplFixedArray($argc);

	for ($i = $argc; $i > 0;) {
		/* NOTE the sprintf() is called before $placeholders[$i] is set, so
		 * when it's set the post-decrement of $i has already been done. so
		 * the index set in $placeholders is one less than the number
		 * written into the placeholder string, which is what we want. this
		 * slightly obscure way of doing it just saves one integer addition
		 * or subtraction operation */
		$placeholders[$i] = '%' . sprintf('%d', $i--);
	}

	return str_replace($placeholders->toArray(), $args, $template);
}

/**
 * Convert a string in a given encoding to an array of Unicode code points.
 *
 * The encoding must be one supported by the mb-string extension.
 *
 * @param string $str The string to convert.
 * @param string $encoding The string's encoding. Default is "UTF-8".
 *
 * @return array<int> The code points.
 */
function toCodePoints(string $str, string $encoding): array
{
	// convert the string to UTF32LE, where each character is represented by four bytes, each of which is a 32-bit
	// LE int, the Unicode code point
	if ("UTF-32LE" !== $encoding) {
		$str = mb_convert_encoding($str, "UTF-32LE", $encoding);
	}

	// then split it into chunks of four chars so that we have the four bytes of the code point for each character
	// in an array of four-byte strings; then map that array by unpacking each four-byte string to an int value
	return array_map(function (string $codePointBytes): int {
		return unpack("V", $codePointBytes)[1];
	}, str_split($str, 4));
}

/**
 * Generate a cryptographically-secure random string of a given length.
 *
 * The string generated will consist entirely of alphanumeric characters, dashes and underscores. The source of
 * randomness for the characters is cryptographically-secure. This does not mean you can't create poor random
 * strings with this function - if you pass a length of 1 you won't get a very strong random string.
 *
 * @param int $length The number of characters in the string.
 *
 * @return string
 * @throws Exception if a cryptographically-secure source of randomness is not available.
 */
function random(int $length): string
{
	assert (0 <= $length, new InvalidArgumentException("Can't produce a random string of < 0 characters in length."));
	$str = "";

    try {
        while (0 < $length) {
            // NOTE base64 of 30 bytes gives 40 chars, none of which is '=' padding
            $chars = min($length, 40);
            $str .= str_replace(["/", "+",], ["-", "_",], substr(base64_encode(random_bytes(30)), 0, $chars));
            $length -= $chars;
        }
    } catch (Exception $err) {
        throw new RuntimeException("Cryptographically-secure random strings are not available.");
    }

	return $str;
}
