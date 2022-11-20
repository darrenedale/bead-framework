<?php

/**
 * Created by PhpStorm.
 * User: darren
 * Date: 30/03/19
 * Time: 10:18
 */

declare(strict_types = 1);

namespace BeadTests;

use BeadTests\Framework\TestCase;
use Exception;
use InvalidArgumentException;
use Bead\Util\HtmlCleaner;
use StdClass;
use TypeError;

class HtmlCleanerTest extends TestCase
{
    private const ValidModes = [
        HtmlCleaner::AllowListMode, HtmlCleaner::DenyListMode, HtmlCleaner::CombinedMode,
    ];

    private const InvalidMode = -9999;

    /**
     * @var HtmlCleaner The HtmlCleaner test instance.
     */
    private HtmlCleaner $m_cleaner;

    /**
     * Set up the test fixture.
     *
     * A fresh, default HtmlCleaner instance is set in m_cleaner.
     */
    public function setUp(): void
    {
        $this->m_cleaner = new HtmlCleaner();
    }

    /**
     * Helper function to assert the validity of a deny or allow list.
     *
     * In valid cases, $list will be an array. However, this is not type hinted because part of the validity check is
     * to ensure that the list is indeed an array.
     *
     * @param array $list The list to check.
     * @param string $name The name of the list (for assertion messages).
     */
    private function checkAllowOrDenyList($list, string $name): void
    {
        $this->assertIsArray($list, "{$name} is not an array");

        foreach ($list as $item) {
            $this->assertIsString($item, "{$name} contains an non-string");
            $this->assertNotEmpty($item, "{$name} contains an empty tag");
        }
    }

    /**
     * Helper function to assert the validity of all deny and allow lists in a cleaner object.
     *
     * @param HtmlCleaner $cleaner The cleaner to check.
     * @param string $namePrefix Prefix the name of the list with this in all assertion messages.
     */
    private function checkAllAllowOrDenyLists(HtmlCleaner $cleaner, string $namePrefix = ""): void
    {
        $this->checkAllowOrDenyList($cleaner->deniedTags(), "{$namePrefix} tags denylist");
        $this->checkAllowOrDenyList($cleaner->allowedTags(), "{$namePrefix} tags allowlist");
        $this->checkAllowOrDenyList($cleaner->deniedIds(), "{$namePrefix} ids denylist");
        $this->checkAllowOrDenyList($cleaner->allowedIds(), "{$namePrefix} ids allowlist");
        $this->checkAllowOrDenyList($cleaner->deniedClasses(), "{$namePrefix} classes denylist");
        $this->checkAllowOrDenyList($cleaner->allowedClasses(), "{$namePrefix} classes allowlist");
    }

    /**
     * Helper to check whether an int is one of the valid filter modes in HtmlCleaner.
     *
     * @param int $mode The mode to check.
     *
     * @return bool `true` if the mode is one of the valid modes, `false` if not.
     */
    private static function isValidFilterMode(int $mode): bool
    {
        return in_array($mode, self::ValidModes);
    }

    /**
     * Test the outcome of the default constructor.
     *
     * The test fixture contains a cleaner set up with the default constructor. This is the object that is tested.
     */
    public function testDefaultConstructor()
    {
        $this->checkAllAllowOrDenyLists($this->m_cleaner, "default");
        $this->assertAttributeIsInt([$this->m_cleaner, "m_tagMode"], "tag filter mode of default cleaner is not an int");
        $this->assertAttributeIsInt([$this->m_cleaner, "m_idMode"], "id filter mode of default cleaner is not an int");
        $this->assertAttributeIsInt([$this->m_cleaner, "m_classMode"], "class filter mode of default cleaner is not an int");
    }

    public function dataForTestConstructor(): array
    {
        return [
            "default_constructor" => [],
            "tag_mode_only" => [HtmlCleaner::AllowListMode],
            "invalid_tag_mode_only" => [self::InvalidMode],
            "tag_mode_and_class_mode" => [HtmlCleaner::AllowListMode, HtmlCleaner::DenyListMode],
            "invalid_tag_mode_and_valid_class_mode" => [self::InvalidMode, HtmlCleaner::AllowListMode],
            "invalid_tag_mode_and_invalid_class_mode" => [self::InvalidMode, self::InvalidMode],
            "all_three_modes" => [HtmlCleaner::AllowListMode, HtmlCleaner::DenyListMode, HtmlCleaner::CombinedMode],
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
     * @dataProvider dataForTestConstructor
     */
    public function testConstructor(?int $tagMode = null, ?int $classMode = null, ?int $idMode = null)
    {
        if (!isset($tagMode)) {
            if (isset($classMode) || isset($idMode)) {
                $this->markTestSkipped("unusable test data provided - args 2 and 3 must not be set if arg 1 is not set");
            }
        } else {
            if (!isset($classMode)) {
                if (isset($idMode)) {
                    $this->markTestSkipped("unusable test data provided - arg 3 must not be set if arg 2 is not set");
                }
            }
        }

        $constructorArgs    = [];
        $expectingException = false;

        foreach ([$tagMode, $classMode, $idMode] as $arg) {
            if (!isset($arg)) {
                break;
            }

            $constructorArgs[]  = $arg;
            $expectingException = $expectingException | !self::isValidFilterMode($arg);
        }

        if ($expectingException) {
            $this->expectException(InvalidArgumentException::class);
        }

        $cleaner = new HtmlCleaner(...$constructorArgs);
        $this->checkAllAllowOrDenyLists($cleaner);
        $this->assertAttributeIsInt([$cleaner, "m_tagMode"], "tag filter mode of constructed cleaner is not an int");
        $this->assertAttributeIsInt([$cleaner, "m_idMode"], "id filter mode of constructed cleaner is not an int");
        $this->assertAttributeIsInt([$cleaner, "m_classMode"], "class filter mode of constructed cleaner is not an int");

        if (isset($tagMode)) {
            if (self::isValidFilterMode($tagMode)) {
                $this->assertSame($tagMode, $cleaner->tagMode(), "the tag filter mode {$cleaner->tagMode()} in the constructed object does not match the provided, valid mode {$tagMode}");
            } else {
                $this->assertNotSame($tagMode, $cleaner->tagMode(), "the tag filter mode {$cleaner->tagMode()} in the constructed object matches the provided, invalid mode {$tagMode}");
            }
        }

        if (isset($idMode)) {
            if (self::isValidFilterMode($idMode)) {
                $this->assertSame($idMode, $cleaner->idMode(), "the id filter mode {$cleaner->idMode()} in the constructed object does not match the provided, valid mode {$idMode}");
            } else {
                $this->assertNotSame($idMode, $cleaner->idMode(), "the id filter mode {$cleaner->idMode()} in the constructed object matches the provided, invalid mode {$idMode}");
            }
        }

        if (isset($classMode)) {
            if (self::isValidFilterMode($classMode)) {
                $this->assertSame($classMode, $cleaner->classMode(), "the class filter mode {$cleaner->classMode()} in the constructed object does not match the provided, valid mode {$classMode}");
            } else {
                $this->assertNotSame($classMode, $cleaner->classMode(), "the class filter {$cleaner->classMode()} mode in the constructed object matches the provided, invalid mode {$classMode}");
            }
        }
    }

    /**
     * Provide test data for filter operation modes.
     *
     * @return array The test data.
     */
    public function dataForTestMode(): array
    {
        return [
            "allowlist_mode" => [HtmlCleaner::AllowListMode, (object)[
                "exception" => null,
                "value" => HtmlCleaner::AllowListMode,
            ],
            ],

            "denylist_mode" => [HtmlCleaner::DenyListMode, (object)[
                "exception" => null,
                "value" => HtmlCleaner::DenyListMode,
            ],
            ],

            "combined_mode" => [HtmlCleaner::CombinedMode, (object)[
                "exception" => null,
                "value" => HtmlCleaner::CombinedMode,
            ],
            ],

            "invalid_int_mode" => [-1, (object)["exception" => InvalidArgumentException::class, "value" => null,]],
            "invalid_type_mode" => ["1", (object)["exception" => InvalidArgumentException::class, "value" => null,]],
        ];
    }

    /**
     * @dataProvider dataForTestMode
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by setIdMode(), or null if we
     *   are not expecting an exception
     * - `value` should be the expected value of idMode() or `null` if we expect it not to change
     *
     * @param int $mode The mode to attempt to set.
     * @param StdClass $expectation A description of what is expected to happen.
     */
    public function testTagMode($mode, StdClass $expectation)
    {
        $oldMode = $this->m_cleaner->tagMode();
        $this->assertIsInt($oldMode, "default tag name mode is not an integer");
        $this->assertContains($oldMode, self::ValidModes);

        if (!is_int($mode)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->setTagMode($mode);
        $actual = $this->m_cleaner->tagMode();
        $this->assertIsInt($actual, "actual tag name mode is not an integer");

        if (isset($expectation->value)) {
            $this->assertSame($expectation->value, $actual, "actual tag name mode is not as expected after call to setTagMode({$mode})");
        } else {
            $this->assertSame($oldMode, $actual, "actual tag name mode is not unchanged after failed call to setTagMode({$mode})");
        }
    }

    /**
     * @dataProvider dataForTestMode
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by setIdMode(), or null if we
     *   are not expecting an exception
     * - `value` should be the expected value of idMode() or `null` if we expect it not to change
     *
     * @param int $mode The mode to attempt to set.
     * @param StdClass $expectation A description of what is expected to happen.
     */
    public function testIdMode($mode, StdClass $expectation)
    {
        $oldMode = $this->m_cleaner->idMode();
        $this->assertIsInt($oldMode, "default ID mode is not an integer");
        $this->assertContains($oldMode, self::ValidModes);

        if (!is_int($mode)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->setIdMode($mode);
        $actual = $this->m_cleaner->idMode();
        $this->assertIsInt($actual, "actual ID mode is not an integer");

        if (isset($expectation->value)) {
            $this->assertSame($expectation->value, $actual, "actual ID mode is not as expected after call to setIdMode({$mode})");
        } else {
            $this->assertSame($oldMode, $actual, "actual ID mode is not unchanged after failed call to setIdMode({$mode})");
        }
    }

    /**
     * @dataProvider dataForTestMode
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by setIdMode(), or null if we
     *   are not expecting an exception
     * - `value` should be the expected value of idMode() or `null` if we expect it not to change
     *
     * @param int $mode The mode to attempt to set.
     * @param StdClass $expectation A description of what is expected to happen.
     */
    public function testClassMode($mode, StdClass $expectation)
    {
        $oldMode = $this->m_cleaner->classMode();
        $this->assertIsInt($oldMode, "default class mode is not an integer");
        $this->assertContains($oldMode, self::ValidModes);

        if (!is_int($mode)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->setClassMode($mode);
        $actual = $this->m_cleaner->classMode();
        $this->assertIsInt($actual, "actual class mode is not an integer");

        if (isset($expectation->value)) {
            $this->assertSame($expectation->value, $actual, "actual class mode is not as expected after call to setClassMode({$mode})");
        } else {
            $this->assertSame($oldMode, $actual, "actual class mode is not unchanged after failed call to setClassMode({$mode})");
        }
    }

    /**
     * Provide test data for tag deny/allow lists.
     *
     * @return array The test data.
     * @todo some tests triggering multiple calls to allowTags()/denyTags() to check the de-duplication over
     * multiple calls
     *
     */
    public function dataForTestTagList(): array
    {
        return [
            "empty_tag_list" => [
                [],
                (object)[
                    "exception" => null,
                    "value" => [],
                ],
            ],

            "single_string" => [
                "remove",
                (object)[
                    "exception" => null,
                    "value" => ["remove"],
                ],
            ],

            "array_several_strings" => [
                ["remove", "ditch", "obliterate",],
                (object)[
                    "exception" => null,
                    "value" => ["remove", "ditch", "obliterate"],
                ],
            ],

            "array_several_strings_with_duplicates" => [
                ["remove", "ditch", "remove", "obliterate", "ditch", "remove", "obliterate", "remove"],
                (object)[
                    "exception" => null,
                    "value" => ["remove", "ditch", "obliterate"],
                ],
            ],

            "array_same_string_extreme_number_of_times" => [
                ["remove", "remove", "remove", "remove", "remove", "remove", "remove", "remove"],
                (object)[
                    "exception" => null,
                    "value" => ["remove"],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataForTestTagList
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by denyTags(), or `null`
     *   if we are not expecting an exception
     * - `value` should be the expected value of deniedTags() or `null` if we expect it not to change
     *
     * @param array|string $denyList The denylist to test.
     * @param StdClass $expectation A description of the expected outcome.
     */
    public function testDenyTags($denyList, StdClass $expectation)
    {
        $oldBlacklist = $this->m_cleaner->deniedTags();

        if (!is_string($denyList) && !is_array($denyList)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->denyTags($denyList);
        $actualBlacklist = $this->m_cleaner->deniedTags();
        $this->assertIsArray($actualBlacklist, "actual tags denylist is not an array");

        if (isset($expectation->value)) {
            $this->assertEqualsCanonicalizing($expectation->value, $actualBlacklist, "actual tags denylist is not as expected after call to denyTags()");
        } else {
            $this->assertEqualsCanonicalizing($oldBlacklist, $actualBlacklist, "actual tags denylist is not unchanged after failed call to denyTags()");
        }
    }

    /**
     * @dataProvider dataForTestTagList
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by allowTags(), or `null`
     *   if we are not expecting an exception
     * - `value` should be the expected value of allowedTags() or `null` if we expect it not to change
     *
     * @param array|string $allowList The allowlist to test.
     * @param StdClass $expectation A description of the expected outcome.
     */
    public function testAllowListTags($allowList, StdClass $expectation)
    {
        $oldAllowlist = $this->m_cleaner->allowedTags();

        if (!is_string($allowList) && !is_array($allowList)) {
            $this->expectException("TypeError", "argument for allowTags() is not an array or string");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->allowTags($allowList);
        $actualAllowlist = $this->m_cleaner->allowedTags();
        $this->assertIsArray($actualAllowlist, "actual tags allowlist is not an array");

        if (isset($expectation->value)) {
            $this->assertEqualsCanonicalizing($expectation->value, $actualAllowlist, "actual tags allowlist is not as expected after call to allowTags()");
        } else {
            $this->assertEqualsCanonicalizing($oldAllowlist, $actualAllowlist, "actual tags allowlist is not unchanged after failed call to allowTags()");
        }
    }

    /**
     * Provide test data for id lists.
     *
     * @return array The test data.
     * @todo some tests triggering multiple calls to allowListIds()/denyIds() to check the de-duplication over
     * multiple calls
     *
     */
    public function dataForTestIdList(): array
    {
        return [
            "empty_id_list" => [
                [],
                (object)[
                    "exception" => null,
                    "value" => [],
                ],
            ],

            "single_string" => [
                "forbidden-id",
                (object)[
                    "exception" => null,
                    "value" => ["forbidden-id"],
                ],
            ],

            "array_several_strings" => [
                ["forbidden-id", "this-one-goes", "not-welcome",],
                (object)[
                    "exception" => null,
                    "value" => ["forbidden-id", "not-welcome", "this-one-goes"],
                ],
            ],

            "array_several_strings_with_duplicates" => [
                ["forbidden-id", "not-welcome", "this-one-goes", "forbidden-id", "not-welcome", "forbidden-id", "this-one-goes", "this-one-goes"],
                (object)[
                    "exception" => null,
                    "value" => ["forbidden-id", "not-welcome", "this-one-goes"],
                ],
            ],

            "array_same_string_extreme_number_of_times" => [
                ["forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id", "forbidden-id",],
                (object)[
                    "exception" => null,
                    "value" => ["forbidden-id"],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataForTestIdList
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by denyIds(), or `null`
     *   if we are not expecting an exception
     * - `value` should be the expected value of deniedIds() or `null` if we expect it not to change
     *
     * @param array|string $denyList The denylist to test.
     * @param StdClass $expectation A description of the expected outcome.
     */
    public function testDenyIds($denyList, StdClass $expectation)
    {
        $oldBlacklist = $this->m_cleaner->deniedIds();

        if (!is_string($denyList) && !is_array($denyList)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->denyIds($denyList);
        $actualBlacklist = $this->m_cleaner->deniedIds();
        $this->assertIsArray($actualBlacklist, "actual ids denylist is not an array");

        if (isset($expectation->value)) {
            $this->assertEqualsCanonicalizing($expectation->value, $actualBlacklist, "actual ids denylist is not as expected after call to denyIds()");
        } else {
            $this->assertEqualsCanonicalizing($oldBlacklist, $actualBlacklist, "actual ids denylist is not unchanged after failed call to denyIds()");
        }
    }

    /**
     * @dataProvider dataForTestIdList
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by allowListIds(), or `null`
     *   if we are not expecting an exception
     * - `value` should be the expected value of allowedIds() or `null` if we expect it not to change
     *
     * @param array|string $allowList The allowlist to test.
     * @param StdClass $expectation A description of the expected outcome.
     */
    public function testAllowListIds($allowList, StdClass $expectation)
    {
        $oldAllowlist = $this->m_cleaner->allowedIds();

        if (!is_string($allowList) && !is_array($allowList)) {
            $this->expectException("TypeError", "argument for allowIds() is not an array or string");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->allowIds($allowList);
        $actualAllowlist = $this->m_cleaner->allowedIds();
        $this->assertIsArray($actualAllowlist, "actual ids allowlist is not an array");

        if (isset($expectation->value)) {
            $this->assertEqualsCanonicalizing($expectation->value, $actualAllowlist, "actual ids allowlist is not as expected after call to allowIds()");
        } else {
            $this->assertEqualsCanonicalizing($oldAllowlist, $actualAllowlist, "actual ids allowlist is not unchanged after failed call to allowIds()");
        }
    }

    /**
     * Provide test data for class lists.
     *
     * @return array The test data.
     * @todo some tests triggering multiple calls to allowClasses()/denyClasses() to check the de-duplication
     * over multiple calls
     *
     */
    public function dataForTestClassList(): array
    {
        return [
            "empty_class_list" => [
                [],
                (object)[
                    "exception" => null,
                    "value" => [],
                ],
            ],

            "single_string" => [
                "dangerous",
                (object)[
                    "exception" => null,
                    "value" => ["dangerous"],
                ],
            ],

            "array_several_strings" => [
                ["dangerous", "tracking-pixel", "auto-ajax-content"],
                (object)[
                    "exception" => null,
                    "value" => ["tracking-pixel", "dangerous", "auto-ajax-content"],
                ],
            ],

            "array_several_strings_with_duplicates" => [
                ["dangerous", "tracking-pixel", "dangerous", "auto-ajax-content", "tracking-pixel", "auto-ajax-content", "dangerous", "auto-ajax-content", "dangerous", "tracking-pixel"],
                (object)[
                    "exception" => null,
                    "value" => ["tracking-pixel", "dangerous", "auto-ajax-content"],
                ],
            ],

            "array_same_string_extreme_number_of_times" => [
                ["dangerous", "dangerous", "dangerous", "dangerous", "dangerous", "dangerous"],
                (object)[
                    "exception" => null,
                    "value" => ["dangerous"],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataForTestClassList
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by denyClasses(), or
     *   `null` if we are not expecting an exception
     * - `value` should be the expected value of deniedClasses() or `null` if we expect it not to change
     *
     * @param array|string $denyList The denylist to test.
     * @param StdClass $expectation A description of the expected outcome.
     */
    public function testDenyClasses($denyList, StdClass $expectation)
    {
        $oldBlacklist = $this->m_cleaner->deniedClasses();

        if (!is_string($denyList) && !is_array($denyList)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->denyClasses($denyList);
        $actualBlacklist = $this->m_cleaner->deniedClasses();
        $this->assertIsArray($actualBlacklist, "actual classes denylist is not an array");

        if (isset($expectation->value)) {
            $this->assertEqualsCanonicalizing($expectation->value, $actualBlacklist, "actual classes denylist is not as expected after call to denyClasses()");
        } else {
            $this->assertEqualsCanonicalizing($oldBlacklist, $actualBlacklist, "actual classes denylist is not unchanged after failed call to denyClasses()");
        }
    }

    /**
     * @dataProvider dataForTestClassList
     *
     * The expectation contains two properties:
     * - `exception` should be the name of an exception class that we expect to be thrown by denyClasses(), or
     *   `null` if we are not expecting an exception
     * - `value` should be the expected value of deniedClasses() or `null` if we expect it not to change
     *
     * @param array|string $allowList The denylist to test.
     * @param StdClass $expectation A description of the expected outcome.
     */
    public function testAllowListClasses($allowList, StdClass $expectation)
    {
        $oldAllowlist = $this->m_cleaner->allowedClasses();

        if (!is_string($allowList) && !is_array($allowList)) {
            $this->expectException("TypeError");
        } else {
            if (!empty($expectation->exception)) {
                $this->expectException($expectation->exception);
            }
        }

        $this->m_cleaner->allowClasses($allowList);
        $actualAllowlist = $this->m_cleaner->allowedClasses();
        $this->assertIsArray($actualAllowlist, "actual classes allowlist is not an array");

        if (isset($expectation->value)) {
            $this->assertEqualsCanonicalizing($expectation->value, $actualAllowlist, "actual classes allowlist is not as expected after call to allowClasses()");
        } else {
            $this->assertEqualsCanonicalizing($oldAllowlist, $actualAllowlist, "actual classes allowlist is not unchanged after failed call to allowClasses()");
        }
    }

    /**
     * Data provider for testIsAllowedTag()
     *
     * @return array[]
     */
    public function dataForTestIsAllowedTag(): array
    {
        return [
            "typicalSpan-Allowed" => ["span", true, ["span",]],
            "typicalSpan-NotAllowed" => ["span", false, ["div",]],
            
            "typicalDiv-Allowed" => ["div", true, ["div",]],
            "typicalDiv-NotAllowed" => ["div", false, ["section",]],
            
            "typicalSpan-MultipleAllowed" => ["span", true, ["span", "div", "section", "article", ]],
            "typicalSpan-MultipleNotAllowed" => ["span", false, ["div", "section", "article", "nav",]],

            "typicalDiv-MultipleAllowed" => ["div", true, ["div", "section", "article",]],
            "typicalDiv-MultipleNotAllowed" => ["div", false, ["section", "article", "nav",]],

            "extremeDivSpace-DivAllowed" => ["div ", false, ["div",]],
            "extremeSpanSpace-SpanAllowed" => ["span ", false, ["span",]],

            "invalidSpan-SpanSpaceAllowed" => ["span", false, ["span ",], InvalidArgumentException::class,],
            "invalidDiv-DivSpaceAllowed" => ["div", false, ["div ",], InvalidArgumentException::class,],
            "invalidDivSpace-DivSpaceAllowed" => ["div ", true, ["div ",], InvalidArgumentException::class,],
            "invalidSpanSpace-SpanSpaceAllowed" => ["span ", true, ["span ",], InvalidArgumentException::class,],

            "invalidStringableTagName" => [new class {
            public function __toString(): string
                {
                    return "span";
                }
            }, false, ["span",], TypeError::class,],
            "invalidNullTagName" => [null, false, ["span",], TypeError::class,],
            "invalidIntTagName" => [21, false, ["span",], TypeError::class,],
            "invalidFloatTagName" => [21.5467, false, ["span",], TypeError::class,],
            "invalidTrueTagName" => [true, false, ["span",], TypeError::class,],
            "invalidFalseTagName" => [false, false, ["span",], TypeError::class,],
            "invalidArrayTagName" => [["span"], false, ["span",], TypeError::class,],
            "invalidObjectTagName" => [(object) ["span"], false, ["span",], TypeError::class,],
        ];
    }

    /**
     * @dataProvider dataForTestIsAllowedTag
     */
    public function testIsAllowedTag($tag, bool $allowed, ?array $allowedTags = null, ?string $exceptionClass = null): void
    {
        if (isset($exceptionClass)) {
            $this->expectException($exceptionClass);
        }
        
        if (isset($allowedTags)) {
            $this->m_cleaner->allowTags($allowedTags);
        }
        
        $this->assertSame($allowed, $this->m_cleaner->isAllowedTag($tag));
    }

//    public function testIsAllowedNode()
//    {
//    }
//
//    public function testIsAllowedId()
//    {
//    }
//
//    public function testIsAllowedClassAttribute()
//    {
//    }

    public function dataForTestClean(): array
    {
        /** @noinspection BadExpressionStatementJS */
        return [
            "hello_world_plain_no_rules" => [
                "Hello World!",
                "Hello World!",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "hello_world_div_no_rules" => [
                "<div>Hello World!</div>",
                "<div>Hello World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_single_node_by_denylisted_tag" => [
                "<div>Hello <span>evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["span"],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_consecutive_nodes_by_denylisted_tag" => [
                "<div>Hello <span>evil</span><span>evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["span"],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_consecutive_nodes_by_different_denylisted_tags" => [
                "<div>Hello <span>evil</span><strong>evil</strong> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["span", "strong"],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_single_node_by_denylisted_id" => [
                "<div>Hello <span id=\"evil-element\">evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil-element"],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_consecutive_nodes_by_denylisted_ids" => [
                "<div>Hello <span id=\"evil-element-1\">evil</span><span id=\"evil-element-2\">evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil-element-1", "evil-element-2",],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            // this is a malformed-HTML dataset
            "remove_consecutive_nodes_by_same_denylisted_id" => [
                "<div>Hello <span id=\"evil-element\">evil</span><span id=\"evil-element\">evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil-element",],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_single_node_by_denylisted_class" => [
                "<div>Hello <span class=\"evil\">evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil"],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_single_node_by_denylisted_class_in_element_with_multiple_classes" => [
                "<div>Hello <span class=\"lovely sunny evil benevolent\">evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil"],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_consecutive_nodes_by_denylisted_class" => [
                "<div>Hello <span class=\"evil\">evil</span><span class=\"super evil\">evil</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil"],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_consecutive_nodes_by_different_denylisted_classes" => [
                "<div>Hello <span class=\"evil\">evil</span><span class=\"diabolical\">diabolical</span> World!</div>",
                "<div>Hello  World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => ["evil", "diabolical"],
                        "allowList" => [],
                    ],
                ],
            ],

            // tests using allowlist (similar scenarios to above)

            // typical use cases
            // TODO use HtmlCleaner::CommonFormattingAllowlist?
            "basic_formatting_allowed" => [
                "<h1>The Lorem Ipsum</h1><p><em>Lorem impsum dolor sit amet...</em> is just the <strong>beginning</strong> of the inaugural <strong><em>Lorum Ipsum</em></strong> text.</p><p>This test data needs to be expanded to include lots more markup that a user might potentially include in his/her input.</p><p>It should also be infused with some other deliberate nefarious content to ensure that it's stripped.</p><p>What we're looking for is that no legitimate user content is removed while all illegitimate content is stripped out.</p>",
                "<h1>The Lorem Ipsum</h1><p><em>Lorem impsum dolor sit amet...</em> is just the <strong>beginning</strong> of the inaugural <strong><em>Lorum Ipsum</em></strong> text.</p><p>This test data needs to be expanded to include lots more markup that a user might potentially include in his/her input.</p><p>It should also be infused with some other deliberate nefarious content to ensure that it's stripped.</p><p>What we're looking for is that no legitimate user content is removed while all illegitimate content is stripped out.</p>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::AllowListMode,
                        "denyList" => [],
                        "allowList" => ["div", "h1", "h2", "h3", "h4", "h5", "h6", "p", "span", "ul", "ol", "li", "section", "article", "strong", "em", "br",],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            // denylist ignored in allowlist-only mode
            // allowlist ignored in denylist-only mode
            // denylist takes precedence in combined mode
            // complex mixed modes

            "entity_refs_to_look_like_forbidden_tag" => [
                "<div>Hello &lt;span class=evil&gt;evil&lt;/span&gt; World!</div>",
                "<div>Hello &lt;span class=evil&gt;evil&lt;/span&gt; World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_permanently_forbidden_tags" => [
                "<script>alert(&quot;Wiping hard drive now.&quot;);</script><div>Hello World!</div>",
                "<div>Hello World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_permanently_forbidden_tags_self_closing" => [
                "<script /><div>Hello World!</div>",
                "<div>Hello World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_permanently_forbidden_tags_empty" => [
                "<script></script><div>Hello World!</div>",
                "<div>Hello World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],

            "remove_permanently_forbidden_tags_all_everywhere" => [
                "<head><script>alert(\"wiping hard drive now...\");</script><title>Dangerous page</title></head><script>alert(\"wiping hard drive now...\");</script><div>Hello World!</div><head /><head><title>Still dangerous</title></head><script />",
                "<div>Hello World!</div>",
                (object)[
                    "tags" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "ids" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                    "classes" => (object)[
                        "mode" => HtmlCleaner::DenyListMode,
                        "denyList" => [],
                        "allowList" => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataForTestClean
     *
     * The config looks like this:
     *     {
     *         tags = {
     *             mode = (int),
     *             denyList = [],
     *             allowList = [],
     *         }
     *         ids = {
     *             mode = (int),
     *             denyList = [],
     *             allowList = [],
     *         }
     *         classes = {
     *             mode = (int),
     *             denyList = [],
     *             allowList = [],
     *         }
     *     }
     *
     * @param string $expectedHtml The expected clean HTML.
     * @param string $testHtml The HTML to clean.
     * @param StdClass $config The cleaner configuration under test.
     *
     * @throws Exception
     */
    public function testClean(string $testHtml, string $expectedHtml, StdClass $config)
    {
        try {
            $this->m_cleaner->setTagMode($config->tags->mode);
            $this->m_cleaner->allowTags($config->tags->allowList);
            $this->m_cleaner->denyTags($config->tags->denyList);

            $this->m_cleaner->setIdMode($config->ids->mode);
            $this->m_cleaner->allowIds($config->ids->allowList);
            $this->m_cleaner->denyIds($config->ids->denyList);

            $this->m_cleaner->setClassMode($config->classes->mode);
            $this->m_cleaner->allowClasses($config->classes->allowList);
            $this->m_cleaner->denyClasses($config->classes->denyList);
        }
        catch (InvalidArgumentException $err) {
            $this->markTestSkipped("Exception {$err} thrown configuring cleaner - check config in test data");
        }

        $actualHtml = $this->m_cleaner->clean($testHtml);
        $this->assertSame($expectedHtml, $actualHtml);
    }
}
