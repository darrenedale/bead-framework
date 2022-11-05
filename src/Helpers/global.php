<?php

/**
 * Include this file in your bootstrap script to make some oft-used function available in the global namespace.
 */

namespace
{

	use function Equit\Helpers\String\html as namespacedHtml;
	use function Equit\Helpers\I18n\tr as namespacedTr;

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
	function html(string $str): string
	{
		return namespacedHtml($str);
	}

	/**
	 * Convenience function to ease UI string translation.
	 *
	 * The idea is that strings to translate are passed to this function, which looks up the translation in a locale
	 * file. The file and line are provided for the purposes of disambiguation, should two identical source strings
	 * need to be translated differently in different contexts.
	 *
	 * Because translated strings may need to contain variable content - i.e. have the name of something inserted into
	 * them - and because the order of the inserted content in the string may vary between languages, indexed
	 * placeholders can be placed in the strings. The strings are then translated, with the translation placing the
	 * placeholders in a different order if required, so that the content can then be inserted into the string after
	 * translation in the correct order for the target language.
	 *
	 * If provided with additional `$args` this function, will insert the content of these arguments into the translated
	 * string based on the placeholders it contains. It does this using the @see Equit\Helpers\String\buildString()
	 * function.
	 *
	 * @param string|null $file  _optional_ The file from which the string originates.
	 * @param int|null $line _optional_ The source code line from which the string originates.
	 * @param mixed[] $args ...mixed _optional_ The values to insert in place of placeholders in the translated string.
	 *
	 * @return string The translated string.
	 * If you don't wish to have the tr() function do this automatically for you, simply call it without any additional
	 * arguments and you will receive the translated string with its placeholders all still present.
	 *
	 */
	function tr(string $str, ?string $file = null, ?int $line = null, ... $args): string
	{
		return namespacedTr($str, $file, $line, ... $args);
	}
}
