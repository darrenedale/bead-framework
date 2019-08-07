<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 06/10/18
 * Time: 20:23
 */

namespace {

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
				/** @noinspection PhpUndefinedMethodInspection */
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

		if(isset($encoding)) {
			$oldEncoding = mb_regex_encoding();
			mb_regex_encoding($encoding);
		}

		$ret = mb_strtolower(mb_substr($str, 0, 1), $encoding) . mb_strtolower(mb_ereg_replace("([[:upper:]])", "_\\1", mb_substr($str, 1)), $encoding);

		if(isset($oldEncoding)) {
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

}
