<?php

/**
 * Defines the LibEquit\Translator class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 *
 * ### Requires
 * - PHP v7.2.0 or later (uses array unpacking in foreach() with [] syntax)
 *
 * ### Changes
 * - (2017-05) Updated documentation. Some minor changes to make code smaller and infinitesimally quicker. Migrated to
 *   [] syntax for array literals.
 *
 * @file LibEquit\Translator.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit;

require_once("classes/equit/AppLog.php");

/**
 * Class to handle translation of UI strings into alternate languages.
 *
 * Objects of this class handle translation of UI strings in an application into a target language for presentation to
 * the end user. Each created object has a target language and a set of paths in which to search for translation files.
 *
 * Translators work in conjunction with tools to extract the translatable strings from the application into a format
 * that can be easily used to create a translation for each of the strings. These translation files are then installed
 * in a location where the translator object can read them and provide the appropriate translated string when asked.
 * Translation files are named with the IETF language subtag for the language/locale (e.g. "en", "fr", "pt-BR"), with
 * the .csv suffix. IANA maintains a list of current language subtags here:
 * <http://www.iana.org/assignments/language-subtag-registry/language-subtag-registry>
 *
 * Translatable strings are identified by their content, their source file and their line within that file. The file
 * and
 * line help to disambiguate the contexts for identical strings in case they need different translations on different
 * occasions. In all cases, if a specific translation for the file and line of a string is not found, one is sought for
 * just the file, and then for just the string. If no translation is found, the original string is returned unmodified.
 *
 * Translation files are simple CSV files. One file contains all the translations into a single language. Each row in
 * each file contains a translation of a single string in context. The first cell contains the file, the second the
 * line, the third the original string and the fourth the translated string. The file and line cells can be empty to
 * provide a "generic" translation that is not tied to a specific context.
 *
 * The _extract_translatable_strings_ tool is available to help provide the source translation files for translators to
 * work with. Given a set of paths to source files it will output the discovered translatable strings in each source
 * file in CSV format. The translator then simply adds his/her translated strings in the appropriate column using a
 * spreadsheet application. The tool always places file and line context information for every string in the generated
 * translation file. This can be removed by the translator if s/he thinks a generic translation for all contexts is
 * suitable.
 *
 * Translation files should only be generated after the source code for a release is frozen. This is because any
 * changes
 * to the source code after the translation files have been generated are likely to break the translation files due to
 * changes in the lines on which translatable strings occur. This problem can be lessened somewhat if the majority of
 * the strings in your UI can be serviced by generic translations, but it is good practice nonetheless to ensure that
 * your code is stable before having its UI translated.
 *
 * In order to use translations, an application must create an instance of the LibEquit\Translator class, provide it
 * with the language to use and one or more search paths in which to attempt to locate translation files, then use the
 * _translate()_ method with any string that it wishes to have translated. For example:
 *
 *     $t = new LibEquit\Translator('pt-BR');
 *     $t->addSearchPath('i18n/myapp/');
 *     $msg = $t->translate('Hello there %1', basename(__FILE__), __LINE__);
 *     echo str_replace('%1', $argv[1]);
 *
 * It is recommended that you use some form of placeholder system to represent content in strings that is not fixed. In
 * the above example, _%1_ is used as a placeholder to represent the content of a command-line argument. It is
 * recommended also that whatever scheme you use is capable of supporting reordering of the arguments by translators so
 * that they can be inserted into the translated string in the correct positions for the target language. Numbering
 * your
 * placeholders in sequence is a useful approach.
 *
 * This can be wrapped into small functions in your apps, using PHPs varargs and dynamic function-calling features to
 * reduce the amount of typing you need to do to translate each string. For example, with:
 *
 *     function tr( $s, $file = '', $line = '' ) {
 *        global $translator;
 *
 *        if($translator instanceof LibEquit\Translator) {
 *            $s = $translator->translate($s, basename($file), $line);
 *        }
 *
 *        // remove fixed args from array of args intended for placeholders
 *        $args = func_get_args();
 *        array_splice($args, 0, 3);
 *        $argc = count($args);
 *
 *        if(1 > $argc) {
 *            return $s;
 *        }
 *
 *        // push string onto front of arg list for call to buildString()
 *        array_unshift($args, $s);
 *        return call_user_func_array('buildString', $args);
 *     }
 *
 *     function buildString( $s ) {
 *        $args = func_get_args();
 *        array_shift($args);
 *        $argc = count($args);
 *
 *        // set up the array of placeholders: array('%1', '%2', ...)
 *        $placeholders = array();
 *
 *        for($i = 1; $i <= $argc; ++$i) {
 *            $placeholders[] = '%' . sprintf('%d', $i);
 *        }
 *
 *        return str_replace($placeholders, $args, $s);
 *     }
 *
 * translating a string becomes:
 *
 *     $translator = new LibEquit\Translator('pt-BR');
 *     $translator->addSearchPath('i18n/myapp/');
 *     echo tr('Hello there %1', __FILE__, __LINE__, $argv[1]);
 *
 * If you have no need for a separate string-building function, you could also subsume the string-building code into
 * the translation function to further reduce the code size:
 *
 *     function tr( $s, $file = '', $line = '' ) {
 *        global $translator;
 *
 *        if($translator instanceof LibEquit\Translator) {
 *            $s = $translator->translate($s, basename($file), $line);
 *        }
 *
 *        // remove fixed args from array of args intended for placeholders
 *        $args = func_get_args();
 *        array_splice($args, 0, 3);
 *        $argc = count($args);
 *
 *        if(1 > $argc) {
 *            // no args for placeholders
 *            return $s;
 *        }
 *
 *        // set up the array of placeholders to process, one for each
 *        // provided arg: array('%1', '%2', ...)
 *        $ph = array();
 *
 *        for($i = 1; $i <= $argc; ++$i) {
 *            $ph[] = '%' . sprintf('%d', $i);
 *        }
 *
 *        return str_replace($ph, $args, $s);
 *     }
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### Events
 * This module does not emit any events.
 *
 * ### Connections
 * This module does not connect to any events.
 *
 * ### Settings
 * This module does not read any settings.
 *
 * ### Session Data
 * This module does not create a session context.
 *
 * @class LibEquit\Translator
 * @author Darren Edale
 * @ingroup libequit
 * @package libequit
 *
 * @actions _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class Translator {
	/** @var int The column in the translation file that contains the file name. */
	protected const FILE_COL = 0;

	/** @var int The column in the translation file that contains the line number. */
	protected const LINE_COL = 1;

	/** @var int The column in the translation file that contains the original text. */
	protected const ORIGINAL_COL = 2;

	/** @var int The column in the translation file that contains the translated text. */
	protected const TRANSLATION_COL = 3;

	/** The translator's language. */
	private $m_lang = null;

	/** The cache of translated text. */
	private $m_cache = [];

	/** The paths to search for translation files. */
	private $m_searchPaths = [];

	/**
	 * Create a translator for a given language.
	 *
	 * @param $lang string _optional_ The language into which to translate.
	 */
	public function __construct(?string $lang = null) {
		$this->setLanguage($lang);
	}

	/**
	 * Fetch the target language of the translator.
	 *
	 * @return string The target language.
	 */
	public function language(): string {
		return $this->m_lang;
	}

	/**
	 * Set the target language of the translator.
	 *
	 * The language provided should be an IETF language tag. These are generally modelled on the ISO language
	 * specifications (though not tied to them) and are mostly of the form _&lt;lang&gt;[-&lt;region&gt;]_. The
	 * language you provide here will be used to search for the translation file from which to retrieve translated
	 * strings.
	 *
	 * @param $lang string|null The target language.
	 */
	public function setLanguage(?string $lang): void {
		$this->m_lang = $lang;
	}

	/**
	 * Add a search path.
	 *
	 * Adds a search path to the set of paths the translator will scan for translation files. The paths added will be
	 * scanned in the order in which they were added, and the scan will stop on the first file that matches the
	 * translator's language.
	 *
	 * The path provided will be canonicalised before it is added. This helps ensure that translation files are not
	 * sourced from invalid locations and that the same canonical path cannot be added more than once using different
	 * path strings to the same destination.
	 *
	 * If a translation file for the current language has already been loaded, adding a new path will _not_ cause the
	 * loaded file to be discarded.
	 *
	 * @param $path string The path to add.
	 */
	public function addSearchPath(string $path): void {
		$path = realpath($path);

		if($path && !in_array($path, $this->m_searchPaths)) {
			$this->m_searchPaths[] = $path;
		}
	}

	/**
	 * Remove a search path.
	 *
	 * Removes a search path from the set of paths the translator will scan for translation files. The path provided
	 * will be canonicalised before it is removed.
	 *
	 * If a translation file for the current language has already been loaded from the removed path, removing the path
	 * will _not_ cause the loaded file to be discarded.
	 *
	 * @param $path string The path to remove.
	 */
	public function removeSearchPath(string $path): void {
		$path = realpath($path);

		if($path) {
			$newPaths = [];

			foreach($this->m_searchPaths as $myPath) {
				if($myPath != $path) {
					$newPaths[] = $myPath;
				}
			}

			$this->m_searchPaths = $newPaths;
		}
	}

	/**
	 * Clear the list of search paths.
	 *
	 * Removes all search paths. Clearing all the search paths does not unload any translation files already loaded. It
	 * does, however, render the translator unable to offer any translations into any languages other than those for
	 * which it has already loaded a translation file. Translation files are loaded the first time they are needed -
	 * i.e. when a new language is encountered in a call to either hasTranslation() or translate().
	 */
	public function clearSearchPaths(): void {
		$this->m_searchPaths = [];
	}

	/**
	 * Retrieve the list of search paths.
	 *
	 * The list of paths is provided in the order in which they will be scanned.
	 *
	 * @return array[string] The list of search paths.
	 */
	public function searchPaths(): array {
		return $this->m_searchPaths;
	}

	/**
	 * Check whether a translation file for the current language has been loaded.
	 *
	 * @return bool _true_ if the translation file has been loaded, _false_ if not.
	 */
	private function isLoaded(): bool {
		return is_string($this->m_lang) && array_key_exists($this->m_lang, $this->m_cache);
	}

	/**
	 * Generate the key that locates a string's translation in the internal cache.
	 *
	 * @param $string string The string whose translation is sought.
	 * @param $file string|null The file from which the string is being translated.
	 * @param $line int|null The line in the file from which the string is being translated.
	 *
	 * @return string The key.
	 */
	private static function cacheKey(string $string, ?string $file, ?int $line): string {
		if(empty($file)) {
			$file = "~~NOFILE~~";
		}

		if(empty($line)) {
			$line = "~~NOLINE~~";
		}

		return "__" . md5($string) . "__{$file}__{$line}__";
	}

	/**
	 * Attempt to find and load a translation file for the current language.
	 *
	 * This method will discard any existing loaded translation file for the current language and will attempt to find
	 * and load another. The paths will be scanned in the order in which they were added, and the first translation file
	 * for the current language that is encountered will be loaded. The scan will stop either when a file is loaded or
	 * all the paths have been scanned, whichever occurs sooner.
	 *
	 * @return bool _true_ if a translation file for the current language was loaded, _false_ otherwise.
	 */
	private function load(): bool {
		if(!empty($this->m_lang)) {
			foreach($this->m_searchPaths as $path) {
				$filePath = "$path/{$this->m_lang}.csv";

				if(file_exists($filePath) && is_file($filePath) && is_readable($filePath)) {
					$f                            = fopen($filePath, "r");
					$this->m_cache[$this->m_lang] = [];

					while(false !== ($line = fgetcsv($f))) {
						$myFile  = $line[self::FILE_COL];
						$myLine  = $line[self::LINE_COL];
						$myOrig  = $line[self::ORIGINAL_COL];
						$myTrans = $line[self::TRANSLATION_COL];

						$this->m_cache[$this->m_lang][self::cacheKey($myOrig, $myFile, $myLine)] = $myTrans;
					}

					fclose($f);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether a string has a translation.
	 *
	 * The file and line parameters are for disambiguation and can be omitted if a generic translation is OK. This
	 * method _will not_ revert to more generic translations if the specific translation requested does not exist. This
	 * behaviour is unlike that of the _translate()_ method.
	 *
	 * @param $string string The string to check for.
	 * @param $file string|null _optional_ The source file of the string.
	 * @param $line int|null _optional_ The line number of the string in the source file.
	 *
	 * @return bool _true_ if the requested translation is available, _false_ if not.
	 */
	public function hasTranslation(string $string, ?string $file = null, ?int $line = null): bool {
		if(!$this->isLoaded()) {
			$this->load();
		}

		$cacheKey = self::cacheKey($string, $file, $line);
		return $this->isLoaded() && isset($this->m_cache[$this->m_lang][$cacheKey]) && !empty($this->m_cache[$this->m_lang][$cacheKey]);
	}

	/**
	 * Translate a string.
	 *
	 * The file and line parameters are for disambiguation and can be omitted if you just want a generic translation.
	 * This method _will_ provide a more generic translation if the specific translation requested is not available.
	 * This behaviour is in contrast to the hasTranslation() method, which will not check for more generic translations.
	 * This ensures that applications have a means of checking whether a specific translation is available, but also
	 * means that different translators can provide just more generic translations where suitable for their language
	 * without having to provide the same translation multiple times and the app will still pick up the correct
	 * translation.
	 *
	 * @param $string string The string to translate.
	 * @param $file string|null _optional_ The source file of the string.
	 * @param $line int|null _optional_ The line number of the string in the source file.
	 *
	 * @return string The translated string, or the original string if no suitable translation can be found.
	 */
	public function translate(string $string, string $file = null, $line = null): string {
		if(!$this->isLoaded()) {
			$this->load();
		}

		if($this->isLoaded()) {
			$keys = [
				[$string, $file, $line],
				[$string, $file, null],
				[$string, null, null],
			];

			foreach($keys as [$string, $file, $line]) {
				$myKey = self::cacheKey($string, $file, $line);

				if(!empty($this->m_cache[$this->m_lang][$myKey])) {
					return $this->m_cache[$this->m_lang][$myKey];
				}
			}
		}

		return $string;
	}
}
