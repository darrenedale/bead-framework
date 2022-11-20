<?php

namespace {

	use Bead\Application;

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
	 * If provided with additional _$args_ this function, will insert the content of these arguments into the translated
	 * string based on the placeholders it contains. It does this using the @param $string string The string to translate.
	 *
	 * @param $file string|null _optional_ The file from which the string originates.
	 * @param $line int|null _optional_ The source code line from which the string originates.
	 * @param $args ...mixed _optional_ The values to insert in place of placeholders in the translated string.
	 *
	 * @return string The translated string.
	 * @link buildString() function.
	 * If you don't wish to have the tr() function do this automatically for you, simply call it without any additional
	 * arguments and you will receive the translated string with its placeholders all still present.
	 *
	 */
	function tr(string $string, string $file = null, int $line = null, ... $args): string {
		$app = Application::instance();

		if (isset($app)) {
			$translator = $app->translator();

			if (isset($translator)) {
				$string = $translator->translate($string, $file, $line);
			}
		}

		if (0 == count($args)) {
			return $string;
		}

		return buildString($string, ... $args);
	}

}