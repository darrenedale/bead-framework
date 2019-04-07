<?php

/**
 * Created by PhpStorm.
 * User: darren
 * Date: 30/03/19
 * Time: 10:18
 */

declare(strict_types=1);

namespace Equit\Test;

use InvalidArgumentException;
use Equit\HtmlCleaner;
use StdClass;

class HtmlCleanerTest extends TestCase {

	/**
	 * Set up the test fixture.
	 *
	 * A fresh, default HtmlCleaner instance is set in m_cleaner.
	 */
	public function setUp(): void {
		$this->m_cleaner = new HtmlCleaner();
	}

	/**
	 * Helper function to assert the validity of a black or white list.
	 *
	 * In valid cases, $list will be an array. However, this is not type hinted because part of the validity check is
	 * to ensure that the list is indeed an array.
	 *
	 * @param array $list The list to check.
	 * @param string $name The name of the list (for assertion messages).
	 */
	private function checkWhiteOrBlackList($list, string $name): void {
		$this->assertIsArray($list, "{$name} is not an array");

		foreach($list as $item) {
			$this->assertIsString($item, "{$name} contains an non-string");
			$this->assertNotEmpty($item, "{$name} contains an empty tag");
		}
	}

	/**
	 * Helper function to assert the validity of all black and white lists in a cleaner object.
	 *
	 * @param \Equit\HtmlCleaner $cleaner The cleaner to check.
	 * @param string $namePrefix Prefix the name of the list with this in all assertion messages.
	 */
	private function checkAllWhiteAndBlackLists(HtmlCleaner $cleaner, string $namePrefix = ""): void {
		$this->checkWhiteOrBlackList($cleaner->blackListedTags(), "{$namePrefix} tags blacklist");
		$this->checkWhiteOrBlackList($cleaner->whiteListedTags(), "{$namePrefix} tags whitelist");
		$this->checkWhiteOrBlackList($cleaner->blackListedIds(), "{$namePrefix} ids blacklist");
		$this->checkWhiteOrBlackList($cleaner->whiteListedIds(), "{$namePrefix} ids whitelist");
		$this->checkWhiteOrBlackList($cleaner->blackListedClasses(), "{$namePrefix} classes blacklist");
		$this->checkWhiteOrBlackList($cleaner->whiteListedClasses(), "{$namePrefix} classes whitelist");
	}

	/**
	 * Helper to check whether an int is one of the valid filter modes in HtmlCleaner.
	 *
	 * @param int $mode The mode to check.
	 *
	 * @return bool `true` if the mode is one of the valid modes, `false` if not.
	 */
	private static function isValidFilterMode(int $mode): bool {
		return in_array($mode, self::ValidModes);
	}

	/**
	 * Test the outcome of the default constructor.
	 *
	 * The test fixture contains a cleaner set up with the defualt constructor. This is the object that is tested.
	 */
	public function testDefaultConstructor() {
		$this->checkAllWhiteAndBlackLists($this->m_cleaner, "default");
		$this->assertAttributeIsInt([$this->m_cleaner, "m_tagMode"], "tag filter mode of default cleaner is not an int");
		$this->assertAttributeIsInt([$this->m_cleaner, "m_idMode"], "id filter mode of default cleaner is not an int");
		$this->assertAttributeIsInt([$this->m_cleaner, "m_classMode"], "class filter mode of default cleaner is not an int");
	}

	public function dataProviderConstructor(): array {
		return [
			"default_constructor" => [],
			"tag_mode_only" => [HtmlCleaner::WhiteListMode],
			"invalid_tag_mode_only" => [self::InvalidMode],
			"tag_mode_and_class_mode" => [HtmlCleaner::WhiteListMode, HtmlCleaner::BlackListMode],
			"invalid_tag_mode_and_valid_class_mode" => [self::InvalidMode, HtmlCleaner::WhiteListMode],
			"invalid_tag_mode_and_invalid_class_mode" => [self::InvalidMode, self::InvalidMode],
			"all_three_modes" => [HtmlCleaner::WhiteListMode, HtmlCleaner::BlackListMode, HtmlCleaner::CombinedMode],
			"all_three_modes_invalid" => [self::InvalidMode, self::InvalidMode, self::InvalidMode],
		];
	}

	/**
	 * Test the constructor.
	 *
	 * The arguments are optional, defaulting to `null`. If `null`, that argument is not provided to the constructor
	 * (that is, the constructor's default for that argument will be used instead). It is therefore only valid to
	 * provide test data that omits values or provides null values in a pattern that is compatible with the operation
	 * of default arguments. In short, the first missing or null argument implies that all the following arguments
	 * must also be null or absent. The test method checks that this is the case, and will skip the test if it detects
	 * that invalid data have been provided.
	 *
	 * @param int|null $tagMode The tag filter mode to provide to the constructor.
	 * @param int|null $idMode The id filter mode to provide to the constructor.
	 * @param int|null $classMode The class filter mode to provide to the constructor.
	 *
	 * @dataProvider dataProviderConstructor
	 */
	public function testConstructor(?int $tagMode = null, ?int $classMode = null, ?int $idMode = null) {
		if(!isset($tagMode)) {
			if(isset($classMode) || isset($idMode)) {
				$this->markTestSkipped("unusable test data provided - args 2 and 3 must not be set if arg 1 is not set");
				return;
			}
		}
		else if(!isset($classMode)) {
			if(isset($idMode)) {
				$this->markTestSkipped("unusable test data provided - arg 3 must not be set if arg 2 is not set");
				return;
			}
		}

		$constructorArgs = [];
		$expectingException = false;

		foreach([$tagMode, $classMode, $idMode] as $arg) {
			if(!isset($arg)) {
				break;
			}

			$constructorArgs[] = $arg;
			$expectingException = $expectingException | !self::isValidFilterMode($arg);
		}

		if($expectingException) {
			$this->expectException(InvalidArgumentException::class);
		}

		$cleaner = new HtmlCleaner(...$constructorArgs);
		$this->checkAllWhiteAndBlackLists($cleaner);
		$this->assertAttributeIsInt([$cleaner, "m_tagMode"], "tag filter mode of constructed cleaner is not an int");
		$this->assertAttributeIsInt([$cleaner, "m_idMode"], "id filter mode of constructed cleaner is not an int");
		$this->assertAttributeIsInt([$cleaner, "m_classMode"], "class filter mode of constructed cleaner is not an int");

		if(isset($tagMode)) {
			if(self::isValidFilterMode($tagMode)) {
				$this->assertSame($tagMode, $cleaner->tagMode(), "the tag filter mode {$cleaner->tagMode()} in the constructed object does not match the provided, valid mode {$tagMode}");
			}
			else {
				$this->assertNotSame($tagMode, $cleaner->tagMode(), "the tag filter mode {$cleaner->tagMode()} in the constructed object matches the provided, invalid mode {$tagMode}");
			}
		}

		if(isset($idMode)) {
			if(self::isValidFilterMode($idMode)) {
				$this->assertSame($idMode, $cleaner->idMode(), "the id filter mode {$cleaner->idMode()} in the constructed object does not match the provided, valid mode {$idMode}");
			}
			else {
				$this->assertNotSame($idMode, $cleaner->idMode(), "the id filter mode {$cleaner->idMode()} in the constructed object matches the provided, invalid mode {$idMode}");
			}
		}

		if(isset($classMode)) {
			if(self::isValidFilterMode($classMode)) {
				$this->assertSame($classMode, $cleaner->classMode(), "the class filter mode {$cleaner->classMode()} in the constructed object does not match the provided, valid mode {$classMode}");
			}
			else {
				$this->assertNotSame($classMode, $cleaner->classMode(), "the class filter {$cleaner->classMode()} mode in the constructed object matches the provided, invalid mode {$classMode}");
			}
		}
	}

	/**
	 * Provide test data for filter operation modes.
	 *
	 * @return array The test data.
	 */
	public function dataProviderMode(): array {
		return [
			"whitelist_mode" => [HtmlCleaner::WhiteListMode, (object) [
				"exception" => null,
				"value" => HtmlCleaner::WhiteListMode,
			]
			],

			"blacklist_mode" => [HtmlCleaner::BlackListMode, (object) [
				"exception" => null,
				"value" => HtmlCleaner::BlackListMode,
			]
			],

			"combined_mode" => [HtmlCleaner::CombinedMode, (object) [
				"exception" => null,
				"value" => HtmlCleaner::CombinedMode,
			]
			],

			"invalid_int_mode" => [-1, (object) ["exception" => InvalidArgumentException::class, "value" => null,]],
			"invalid_type_mode" => ["1", (object) ["exception" => InvalidArgumentException::class, "value" => null,]],
		];
	}

	/**
	 * @dataProvider dataProviderMode
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by setIdMode(), or null if we
	 *   are not expecting an exception
	 * - `value` should be the expected value of idMode() or `null` if we expect it not to change
	 *
	 * @param int $mode The mode to attempt to set.
	 * @param \StdClass $expectation A description of what is expected to happen.
	 */
	public function testTagMode($mode, StdClass $expectation) {
		$oldMode = $this->m_cleaner->tagMode();
		$this->assertIsInt($oldMode, "default tag name mode is not an integer");
		$this->assertContains($oldMode, self::ValidModes);

		if(!is_int($mode)) {
			$this->expectException("TypeError", "argument for setTagMode() is not an integer");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->setTagMode($mode);
		$actual = $this->m_cleaner->tagMode();
		$this->assertIsInt($actual, "actual tag name mode is not an integer");

		if(isset($expectation->value)) {
			$this->assertSame($expectation->value, $actual, "actual tag name mode is not as expected after call to setTagMode({$mode})");
		}
		else {
			$this->assertSame($oldMode, $actual, "actual tag name mode is not unchanged after failed call to setTagMode({$mode})");
		}
	}

	/**
	 * @dataProvider dataProviderMode
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by setIdMode(), or null if we
	 *   are not expecting an exception
	 * - `value` should be the expected value of idMode() or `null` if we expect it not to change
	 *
	 * @param int $mode The mode to attempt to set.
	 * @param \StdClass $expectation A description of what is expected to happen.
	 */
	public function testIdMode($mode, StdClass $expectation) {
		$oldMode = $this->m_cleaner->idMode();
		$this->assertIsInt($oldMode, "default ID mode is not an integer");
		$this->assertContains($oldMode, self::ValidModes);

		if(!is_int($mode)) {
			$this->expectException("TypeError", "argument for setIdMode() is not an integer");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->setIdMode($mode);
		$actual = $this->m_cleaner->idMode();
		$this->assertIsInt($actual, "actual ID mode is not an integer");

		if(isset($expectation->value)) {
			$this->assertSame($expectation->value, $actual, "actual ID mode is not as expected after call to setIdMode({$mode})");
		}
		else {
			$this->assertSame($oldMode, $actual, "actual ID mode is not unchanged after failed call to setIdMode({$mode})");
		}
	}

	/**
	 * @dataProvider dataProviderMode
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by setIdMode(), or null if we
	 *   are not expecting an exception
	 * - `value` should be the expected value of idMode() or `null` if we expect it not to change
	 *
	 * @param int $mode The mode to attempt to set.
	 * @param \StdClass $expectation A description of what is expected to happen.
	 */
	public function testClassMode($mode, StdClass $expectation) {
		$oldMode = $this->m_cleaner->classMode();
		$this->assertIsInt($oldMode, "default class mode is not an integer");
		$this->assertContains($oldMode, self::ValidModes);

		if(!is_int($mode)) {
			$this->expectException("TypeError", "argument for setClassMode() is not an integer");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->setClassMode($mode);
		$actual = $this->m_cleaner->classMode();
		$this->assertIsInt($actual, "actual class mode is not an integer");

		if(isset($expectation->value)) {
			$this->assertSame($expectation->value, $actual, "actual class mode is not as expected after call to setClassMode({$mode})");
		}
		else {
			$this->assertSame($oldMode, $actual, "actual class mode is not unchanged after failed call to setClassMode({$mode})");
		}
	}

	/**
	 * Provide test data for tag black/white lists.
	 *
	 * @todo some tests triggering multiple calls to whiteListTags()/blackListTags() to check the de-duplication over
	 * multiple calls
	 *
	 * @return array The test data.
	 */
	public function dataProviderTagList(): array {
		return [
			"empty_tag_list" => [
				[],
				(object) [
					"exception" => null,
					"value" => [],
				],
			],

			"single_string" => [
				"remove",
				(object) [
					"exception" => null,
					"value" => ["remove"],
				],
			],

			"array_several_strings" => [
				["remove", "ditch", "obliterate",],
				(object) [
					"exception" => null,
					"value" => ["remove", "ditch", "obliterate"],
				],
			],

			"array_several_strings_with_duplicates" => [
				["remove", "ditch", "remove", "obliterate", "ditch", "remove", "obliterate", "remove"],
				(object) [
					"exception" => null,
					"value" => ["remove", "ditch", "obliterate"],
				],
			],

			"array_same_string_extreme_number_of_times" => [
				["remove", "remove", "remove", "remove", "remove", "remove", "remove", "remove"],
				(object) [
					"exception" => null,
					"value" => ["remove"],
				],
			],
		];
	}

	/**
	 * @dataProvider dataProviderTagList
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by blackListTags(), or `null`
	 *   if we are not expecting an exception
	 * - `value` should be the expected value of blackListedTags() or `null` if we expect it not to change
	 *
	 * @param array|string $blackList The blacklist to test.
	 * @param \StdClass $expectation A description of the expected outcome.
	 */
	public function testBlackListTags($blackList, StdClass $expectation) {
		$oldBlacklist = $this->m_cleaner->blackListedTags();

		if(!is_string($blackList) && !is_array($blackList)) {
			$this->expectException("TypeError", "argument for blackListTags() is not an array or string");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->blackListTags($blackList);
		$actualBlacklist = $this->m_cleaner->blackListedTags();
		$this->assertIsArray($actualBlacklist, "actual tags blacklist is not an array");

		if(isset($expectation->value)) {
			$this->assertFlatArraysAreEquivalent($expectation->value, $actualBlacklist, "actual tags blacklist is not as expected after call to blackListTags()");
		}
		else {
			$this->assertFlatArraysAreEquivalent($oldBlacklist, $actualBlacklist, "actual tags blacklist is not unchanged after failed call to blackListTags()");
		}
	}

	/**
	 * @dataProvider dataProviderTagList
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by whiteListTags(), or `null`
	 *   if we are not expecting an exception
	 * - `value` should be the expected value of whiteListedTags() or `null` if we expect it not to change
	 *
	 * @param array|string $whiteList The whitelist to test.
	 * @param \StdClass $expectation A description of the expected outcome.
	 */
	public function testWhiteListTags($whiteList, StdClass $expectation) {
		$oldWhitelist = $this->m_cleaner->whiteListedTags();

		if(!is_string($whiteList) && !is_array($whiteList)) {
			$this->expectException("TypeError", "argument for whiteListTags() is not an array or string");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->whiteListTags($whiteList);
		$actualWhitelist = $this->m_cleaner->whiteListedTags();
		$this->assertIsArray($actualWhitelist, "actual tags whitelist is not an array");

		if(isset($expectation->value)) {
			$this->assertFlatArraysAreEquivalent($expectation->value, $actualWhitelist, "actual tags whitelist is not as expected after call to whiteListTags()");
		}
		else {
			$this->assertFlatArraysAreEquivalent($oldWhitelist, $actualWhitelist, "actual tags whitelist is not unchanged after failed call to whiteListTags()");
		}
	}
	
	/**
	 * Provide test data for id lists.
	 *
	 * @todo some tests triggering multiple calls to whiteListIds()/blackListIds() to check the de-duplication over
	 * multiple calls
	 *
	 * @return array The test data.
	 */
	public function dataProviderIdList(): array {
		return [
			"empty_id_list" => [
				[],
				(object) [
					"exception" => null,
					"value" => [],
				],
			],

			"single_string" => [
				"forbidden-id",
				(object) [
					"exception" => null,
					"value" => ["forbidden-id"],
				],
			],

			"array_several_strings" => [
				["forbidden-id", "this-one-goes", "not-welcome",],
				(object) [
					"exception" => null,
					"value" => ["forbidden-id", "not-welcome", "this-one-goes"],
				],
			],

			"array_several_strings_with_duplicates" => [
				["forbidden-id", "not-welcome", "this-one-goes", "forbidden-id", "not-welcome", "forbidden-id", "this-one-goes", "this-one-goes"],
				(object) [
					"exception" => null,
					"value" => ["forbidden-id", "not-welcome", "this-one-goes"],
				],
			],

			"array_same_string_extreme_number_of_times" => [
				["forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id",],
				(object) [
					"exception" => null,
					"value" => ["forbidden-id"],
				],
			],
		];
	}

	/**
	 * @dataProvider dataProviderIdList
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by blackListIds(), or `null`
	 *   if we are not expecting an exception
	 * - `value` should be the expected value of blackListedIds() or `null` if we expect it not to change
	 *
	 * @param array|string $blackList The blacklist to test.
	 * @param \StdClass $expectation A description of the expected outcome.
	 */
	public function testBlackListIds($blackList, StdClass $expectation) {
		$oldBlacklist = $this->m_cleaner->blackListedIds();

		if(!is_string($blackList) && !is_array($blackList)) {
			$this->expectException("TypeError", "argument for blackListIds() is not an array or string");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->blackListIds($blackList);
		$actualBlacklist = $this->m_cleaner->blackListedIds();
		$this->assertIsArray($actualBlacklist, "actual ids blacklist is not an array");

		if(isset($expectation->value)) {
			$this->assertFlatArraysAreEquivalent($expectation->value, $actualBlacklist, "actual ids blacklist is not as expected after call to blackListIds()");
		}
		else {
			$this->assertFlatArraysAreEquivalent($oldBlacklist, $actualBlacklist, "actual ids blacklist is not unchanged after failed call to blackListIds()");
		}
	}

	/**
	 * @dataProvider dataProviderIdList
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by whiteListIds(), or `null`
	 *   if we are not expecting an exception
	 * - `value` should be the expected value of whiteListedIds() or `null` if we expect it not to change
	 *
	 * @param array|string $whiteList The whitelist to test.
	 * @param \StdClass $expectation A description of the expected outcome.
	 */
	public function testWhiteListIds($whiteList, StdClass $expectation) {
		$oldWhitelist = $this->m_cleaner->whiteListedIds();

		if(!is_string($whiteList) && !is_array($whiteList)) {
			$this->expectException("TypeError", "argument for whiteListIds() is not an array or string");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->whiteListIds($whiteList);
		$actualWhitelist = $this->m_cleaner->whiteListedIds();
		$this->assertIsArray($actualWhitelist, "actual ids whitelist is not an array");

		if(isset($expectation->value)) {
			$this->assertFlatArraysAreEquivalent($expectation->value, $actualWhitelist, "actual ids whitelist is not as expected after call to whiteListIds()");
		}
		else {
			$this->assertFlatArraysAreEquivalent($oldWhitelist, $actualWhitelist, "actual ids whitelist is not unchanged after failed call to whiteListIds()");
		}
	}
	/**
	 * Provide test data for class lists.
	 *
	 * @todo some tests triggering multiple calls to whiteListClasses()/blackListClasses() to check the de-duplication
	 * over multiple calls
	 *
	 * @return array The test data.
	 */
	public function dataProviderClassList(): array {
		return [
			"empty_class_list" => [
				[],
				(object) [
					"exception" => null,
					"value" => [],
				],
			],

			"single_string" => [
				"dangerous",
				(object) [
					"exception" => null,
					"value" => ["dangerous"],
				],
			],

			"array_several_strings" => [
				["dangerous", "tracking-pixel", "auto-ajax-content"],
				(object) [
					"exception" => null,
					"value" => ["tracking-pixel", "dangerous", "auto-ajax-content"],
				],
			],

			"array_several_strings_with_duplicates" => [
				["dangerous", "tracking-pixel", "dangerous", "auto-ajax-content", "tracking-pixel", "auto-ajax-content", "dangerous", "auto-ajax-content", "dangerous", "tracking-pixel"],
				(object) [
					"exception" => null,
					"value" => ["tracking-pixel", "dangerous", "auto-ajax-content"],
				],
			],

			"array_same_string_extreme_number_of_times" => [
				["dangerous", "dangerous", "dangerous", "dangerous", "dangerous", "dangerous"],
				(object) [
					"exception" => null,
					"value" => ["dangerous"],
				],
			],
		];
	}

	/**
	 * @dataProvider dataProviderClassList
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by blackListClasses(), or
	 *   `null` if we are not expecting an exception
	 * - `value` should be the expected value of blackListedClasses() or `null` if we expect it not to change
	 *
	 * @param array|string $blackList The blacklist to test.
	 * @param \StdClass $expectation A description of the expected outcome.
	 */
	public function testBlackListClasses($blackList, StdClass $expectation) {
		$oldBlacklist = $this->m_cleaner->blackListedClasses();

		if(!is_string($blackList) && !is_array($blackList)) {
			$this->expectException("TypeError", "argument for blackListClasses() is not an array or string");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->blackListClasses($blackList);
		$actualBlacklist = $this->m_cleaner->blackListedClasses();
		$this->assertIsArray($actualBlacklist, "actual classes blacklist is not an array");

		if(isset($expectation->value)) {
			$this->assertFlatArraysAreEquivalent($expectation->value, $actualBlacklist, "actual classes blacklist is not as expected after call to blackListClasses()");
		}
		else {
			$this->assertFlatArraysAreEquivalent($oldBlacklist, $actualBlacklist, "actual classes blacklist is not unchanged after failed call to blackListClasses()");
		}
	}

	/**
	 * @dataProvider dataProviderClassList
	 *
	 * The expectation contains two properties:
	 * - `exception` should be the name of an exception class that we expect to be thrown by blackListClasses(), or
	 *   `null` if we are not expecting an exception
	 * - `value` should be the expected value of blackListedClasses() or `null` if we expect it not to change
	 *
	 * @param array|string $whiteList The blacklist to test.
	 * @param \StdClass $expectation A description of the expected outcome.
	 */
	public function testWhiteListClasses($whiteList, StdClass $expectation) {
		$oldWhitelist = $this->m_cleaner->whiteListedClasses();

		if(!is_string($whiteList) && !is_array($whiteList)) {
			$this->expectException("TypeError", "argument for whiteListClasses() is not an array or string");
		}
		else if(!empty($expectation->exception)) {
			$this->expectException($expectation->exception);
		}

		$this->m_cleaner->whiteListClasses($whiteList);
		$actualWhitelist = $this->m_cleaner->whiteListedClasses();
		$this->assertIsArray($actualWhitelist, "actual classes whitelist is not an array");

		if(isset($expectation->value)) {
			$this->assertFlatArraysAreEquivalent($expectation->value, $actualWhitelist, "actual classes whitelist is not as expected after call to whiteListClasses()");
		}
		else {
			$this->assertFlatArraysAreEquivalent($oldWhitelist, $actualWhitelist, "actual classes whitelist is not unchanged after failed call to whiteListClasses()");
		}
	}

	public function testIsAllowedTag() {
	}

	public function testIsAllowedNode() {
	}

	public function testIsAllowedId() {
	}

	public function testIsAllowedClassAttribute() {
	}

	public function dataProviderClean(): array {
		/** @noinspection BadExpressionStatementJS */
		return [
			"hello_world_plain_no_rules" => [
				"Hello World!",
				"Hello World!",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"hello_world_div_no_rules" => [
				"<div>Hello World!</div>",
				"<div>Hello World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_single_node_by_blacklisted_tag" => [
				"<div>Hello <span>evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["span"],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_consecutive_nodes_by_blacklisted_tag" => [
				"<div>Hello <span>evil</span><span>evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["span"],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_consecutive_nodes_by_different_blacklisted_tags" => [
				"<div>Hello <span>evil</span><strong>evil</strong> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["span", "strong"],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_single_node_by_blacklisted_id" => [
				"<div>Hello <span id=\"evil-element\">evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil-element"],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_consecutive_nodes_by_blacklisted_ids" => [
				"<div>Hello <span id=\"evil-element-1\">evil</span><span id=\"evil-element-2\">evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil-element-1", "evil-element-2",],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			// this is a malformed-HTML dataset
			"remove_consecutive_nodes_by_same_blacklisted_id" => [
				"<div>Hello <span id=\"evil-element\">evil</span><span id=\"evil-element\">evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil-element",],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_single_node_by_blacklisted_class" => [
				"<div>Hello <span class=\"evil\">evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil"],
						"whiteList" => [],
					],
				]
			],

			"remove_single_node_by_blacklisted_class_in_element_with_multiple_classes" => [
				"<div>Hello <span class=\"lovely sunny evil benevolent\">evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil"],
						"whiteList" => [],
					],
				]
			],

			"remove_consecutive_nodes_by_blacklisted_class" => [
				"<div>Hello <span class=\"evil\">evil</span><span class=\"super evil\">evil</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil"],
						"whiteList" => [],
					],
				]
			],

			"remove_consecutive_nodes_by_different_blacklisted_classes" => [
				"<div>Hello <span class=\"evil\">evil</span><span class=\"diabolical\">diabolical</span> World!</div>",
				"<div>Hello  World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => ["evil", "diabolical"],
						"whiteList" => [],
					],
				]
			],

			// tests using whitelist (similar scenarios to above)

			// typical use cases
			// TODO use HtmlCleaner::CommonFormattingWhitelist?
			"basic_formatting_allowed" => [
				"<h1>The Lorem Ipsum</h1><p><em>Lorem impsum dolor sit amet...</em> is just the <strong>beginning</strong> of the inaugural <strong><em>Lorum Ipsum</em></strong> text.</p><p>This test data needs to be expanded to include lots more markup that a user might potentially include in his/her input.</p><p>It should also be infused with some other deliberate nefarious content to ensure that it's stripped.</p><p>What we're looking for is that no legitimate user content is removed while all illegitimate content is stripped out.</p>",
				"<h1>The Lorem Ipsum</h1><p><em>Lorem impsum dolor sit amet...</em> is just the <strong>beginning</strong> of the inaugural <strong><em>Lorum Ipsum</em></strong> text.</p><p>This test data needs to be expanded to include lots more markup that a user might potentially include in his/her input.</p><p>It should also be infused with some other deliberate nefarious content to ensure that it's stripped.</p><p>What we're looking for is that no legitimate user content is removed while all illegitimate content is stripped out.</p>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::WhiteListMode,
						"blackList" => [],
						"whiteList" => ["div", "h1", "h2", "h3", "h4", "h5", "h6", "p", "span", "ul", "ol", "li", "section", "article", "strong", "em", "br",],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			// blacklist ignored in whitelist-only mode
			// whitelist ignored in blacklist-only mode
			// blacklist takes precedence in combined mode
			// complex mixed modes


			"entity_refs_to_look_like_forbidden_tag" => [
				"<div>Hello &lt;span class=evil&gt;evil&lt;/span&gt; World!</div>",
				"<div>Hello &lt;span class=evil&gt;evil&lt;/span&gt; World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_permanently_forbidden_tags" => [
				"<script>alert(&quot;Wiping hard drive now.&quot;);</script><div>Hello World!</div>",
				"<div>Hello World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_permanently_forbidden_tags_self_closing" => [
				"<script /><div>Hello World!</div>",
				"<div>Hello World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_permanently_forbidden_tags_empty" => [
				"<script></script><div>Hello World!</div>",
				"<div>Hello World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],

			"remove_permanently_forbidden_tags_all_everywhere" => [
				"<head><script>alert(\"wiping hard drive now...\");</script><title>Dangerous page</title></head><script>alert(\"wiping hard drive now...\");</script><div>Hello World!</div><head /><head><title>Still dangerous</title></head><script />",
				"<div>Hello World!</div>",
				(object) [
					"tags" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"ids" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
					"classes" => (object) [
						"mode" => HtmlCleaner::BlackListMode,
						"blackList" => [],
						"whiteList" => [],
					],
				]
			],
		];
	}

	/**
	 * @dataProvider dataProviderClean
	 *
	 * The config looks like this:
	 *     {
	 *         tags = {
	 *             mode = (int),
	 *             blackList = [],
	 *             whiteList = [],
	 *         }
	 *         ids = {
	 *             mode = (int),
	 *             blackList = [],
	 *             whiteList = [],
	 *         }
	 *         classes = {
	 *             mode = (int),
	 *             blackList = [],
	 *             whiteList = [],
	 *         }
	 *     }
	 *
	 * @param string $expectedHtml The expected clean HTML.
	 * @param string $testHtml The HTML to clean.
	 * @param \StdClass $config The cleaner configuration under test.
	 *
	 * @throws \Exception
	 */
	public function testClean(string $testHtml, string $expectedHtml, StdClass $config) {
		try {
			$this->m_cleaner->setTagMode($config->tags->mode);
			$this->m_cleaner->whiteListTags($config->tags->whiteList);
			$this->m_cleaner->blackListTags($config->tags->blackList);

			$this->m_cleaner->setIdMode($config->ids->mode);
			$this->m_cleaner->whiteListIds($config->ids->whiteList);
			$this->m_cleaner->blackListIds($config->ids->blackList);

			$this->m_cleaner->setClassMode($config->classes->mode);
			$this->m_cleaner->whiteListClasses($config->classes->whiteList);
			$this->m_cleaner->blackListClasses($config->classes->blackList);
		}
		catch(InvalidArgumentException $err) {
			$this->markTestSkipped("Exception {$err} thrown configuring cleaner - check config in test data");
			return;
		}

		$actualHtml = $this->m_cleaner->clean($testHtml);
		$this->assertSame($expectedHtml, $actualHtml);
	}

	private const ValidModes = [
		HtmlCleaner::WhiteListMode, HtmlCleaner::BlackListMode, HtmlCleaner::CombinedMode,
	];

	private const InvalidMode = -9999;

	/**
	 * @var HtmlCleaner The HtmlCleaner test instance.
	 */
	private $m_cleaner = null;
}
