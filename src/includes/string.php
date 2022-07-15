<?php

/**
 * @file string.php
 * @author Darren Edale
 * @version 0.9.2
 * @version 0.9.2 *
 * @brief Definitions of stand-alone string-processing functions.
 *
 * These functions complement the string processing functions PHP provides.
 *
 * @package bead-framework
 */

namespace
{

	/**
	 * @param mixed $var Value/object to stringify
	 *
	 * @return string The string representation of the provided value or object.
	 */
	function stringify($var): string {
		if (is_string($var)) {
			return $var;
		}

		if (is_numeric($var)) {
			return "$var";
		}

		if (is_null($var)) {
			return "NULL";
		}

		if (is_bool($var)) {
			return ($var ? "TRUE" : "FALSE");
		}

		if (is_array($var)) {
			$ret = [];

			foreach ($var as $k => $o) {
				$ret[] = stringify($k) . "=>" . stringify($o);
			}

			return "ARRAY[" . implode(",", $ret) . "]";
		}

		if (is_resource($var)) {
			return "RESOURCE<" . get_resource_type($var) . ">";
		}

		if (is_object($var)) {
			if ($var instanceof DateTime) {
				return $var->format("Y-m-d H:i:s");
			}

			if (is_callable([$var, "__toString"], false)) {
				return $var->__toString();
			}

			return "OBJECT<" . get_class($var) . ">";
		}

		return "<unknown object>";
	}

	/**
	 * Convert a camelCase string to snake_case.
	 *
	 * The conversion is multibyte-safe.
	 *
	 * @param string $str The string to convert.
	 * @param string|null $encoding The encoding to use.
	 *
	 * @return string The converted string.
	 */
	function camelToSnake(string $str, ?string $encoding = null): string {
		if(empty($str)) {
			return $str;
		}

		if (isset($encoding)) {
			$oldEncoding = mb_regex_encoding();
			mb_regex_encoding($encoding);
		}

		$ret = mb_strtolower(mb_substr($str, 0, 1), $encoding) . mb_strtolower(mb_ereg_replace("([[:upper:]])", "_\\1", mb_substr($str, 1)), $encoding);

		if (isset($oldEncoding)) {
			mb_regex_encoding($oldEncoding);
		}

		return $ret;
	}

	if (!function_exists("snakeToCamel")) {
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

			$ret = mb_ereg_replace_callback("_+(.)", function (array $matches) use ($encoding): string {
				return mb_strtoupper($matches[1], $encoding ?? "UTF-8");
			}, $str);

			if (isset($oldEncoding)) {
				mb_regex_encoding($oldEncoding);
			}

			return $ret;
		}
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
	function html(string $str) {
		return htmlentities($str, ENT_COMPAT, "UTF-8");
	}

	/**
	 * Escape content for use as the data in a CSV cell.
	 *
	 * @param $content string The content to escape.
	 *
	 * @return string The escaped content.
	 */
	function escapeCsvCell(string $content): string {
		return str_replace("\"", "\\\"", $content);
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
	 * @param $template string The template to process.
	 * @param $args ...string The values to insert into the template.
	 *
	 * @return string The populated string.
	 */
	function buildString(string $template, ... $args): string {
		$argc = count($args);

		if (0 == $argc) {
			/* avoid pointlessly executing the rest ... */
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
	function randomString(int $length): string
	{
		$str = "";

		while (0 < $length) {
			// NOTE base64 of 30 bytes gives 40 chars, none of which is '=' padding
			$chars  = min($length, 40);
			$str    .= str_replace(["/", "+",], ["-", "_",], substr(base64_encode(random_bytes(30)), 0, $chars));
			$length -= $chars;
		}

		return $str;
	}

    if (!function_exists("str_starts_with")) {
        /**
         * Determine whether one string starts with another.
         *
         * This is a polyfill for the built-in function introduced in PHP 8.
         *
         * @param string $haystack The string that is being checked.
         * @param string $needle The string it must start with.
         *
         * @return bool true if the string does start with the other, false otherwise.
         */
        function str_starts_with(string $haystack, string $needle): bool
        {
            return "" === $needle || (strlen($haystack) >= strlen($needle) && $needle === substr($haystack, 0, strlen($needle)));
        }
    }

    if (!function_exists("str_ends_with")) {
        /**
         * Determine whether one string ends with another.
         *
         * This is a polyfill for the built-in function introduced in PHP 8.
         *
         * @param string $haystack The string that is being checked.
         * @param string $needle The string it must end with.
         *
         * @return bool true if the string does end with the other, false otherwise.
         */
        function str_ends_with(string $haystack, string $needle): bool
        {
            return "" === $needle || (strlen($haystack) >= strlen($needle) && $needle === substr($haystack, -strlen($needle)));
        }
    }

    if (!function_exists("str_contains")) {
        /**
         * Determine whether one string contains another.
         *
         * This is a polyfill for the built-in function introduced in PHP 8.
         *
         * @param string $haystack The string that is being checked.
         * @param string $needle The string it must contain.
         *
         * @return bool true if the string does contain the other, false otherwise.
         */
        function str_contains(string $haystack, string $needle): bool
        {
            return "" === $needle || false !== strpos($haystack, $needle);
        }
    }
}
