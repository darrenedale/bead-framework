<?php

/**
 * Created by PhpStorm.
 * User: darren
 * Date: 30/03/19
 * Time: 10:18
 */

declare(strict_types=1);

namespace BeadTests;

use BeadTests\Framework\TestCase;
use Bead\Util\HtmlCleaner;
use DOMDocument;
use Exception;
use InvalidArgumentException;
use RuntimeException;
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
    private HtmlCleaner $testCleaner;

    /**
     * Set up the test fixture.
     *
     * A fresh, default HtmlCleaner instance is set in m_cleaner.
     */
    public function setUp(): void
    {
        $this->testCleaner = new HtmlCleaner();
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
        self::assertIsArray($list, "{$name} is not an array");

        foreach ($list as $item) {
            self::assertIsString($item, "{$name} contains a non-string");
            self::assertNotEmpty($item, "{$name} contains an empty tag");
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

    /** Ensure the default constructor produces the expected instance. */
    public function testDefaultConstructor()
    {
        self::assertEquals(HtmlCleaner::CombinedMode, $this->testCleaner->tagMode());
        self::assertEquals(HtmlCleaner::CombinedMode, $this->testCleaner->idMode());
        self::assertEquals(HtmlCleaner::CombinedMode, $this->testCleaner->classMode());
        self::assertEquals([], $this->testCleaner->allowedTags());
        self::assertEquals([], $this->testCleaner->allowedIds());
        self::assertEquals([], $this->testCleaner->allowedClasses());
        self::assertEquals([], $this->testCleaner->deniedTags());
        self::assertEquals([], $this->testCleaner->deniedIds());
        self::assertEquals([], $this->testCleaner->deniedClasses());
    }

    /** Ensure we can set a valid tag mode in the constructor. */
    public function testConstructorWithTagMode()
    {
        $cleaner = new HtmlCleaner(tagMode: HtmlCleaner::AllowListMode);
        self::assertEquals(HtmlCleaner::AllowListMode, $cleaner->tagMode());
        self::assertEquals(HtmlCleaner::CombinedMode, $cleaner->idMode());
        self::assertEquals(HtmlCleaner::CombinedMode, $cleaner->classMode());
        self::assertEquals([], $cleaner->allowedTags());
        self::assertEquals([], $cleaner->allowedIds());
        self::assertEquals([], $cleaner->allowedClasses());
        self::assertEquals([], $cleaner->deniedTags());
        self::assertEquals([], $cleaner->deniedIds());
        self::assertEquals([], $cleaner->deniedClasses());
    }

    /** Ensure we can set a valid id mode in the constructor. */
    public function testConstructorWithIdMode()
    {
        $cleaner = new HtmlCleaner(idMode: HtmlCleaner::AllowListMode);
        self::assertEquals(HtmlCleaner::CombinedMode, $cleaner->tagMode());
        self::assertEquals(HtmlCleaner::AllowListMode, $cleaner->idMode());
        self::assertEquals(HtmlCleaner::CombinedMode, $cleaner->classMode());
        self::assertEquals([], $cleaner->allowedTags());
        self::assertEquals([], $cleaner->allowedIds());
        self::assertEquals([], $cleaner->allowedClasses());
        self::assertEquals([], $cleaner->deniedTags());
        self::assertEquals([], $cleaner->deniedIds());
        self::assertEquals([], $cleaner->deniedClasses());
    }

    /** Ensure we can set a valid class mode in the constructor. */
    public function testConstructorWithClassMode()
    {
        $cleaner = new HtmlCleaner(classMode: HtmlCleaner::AllowListMode);
        self::assertEquals(HtmlCleaner::CombinedMode, $cleaner->tagMode());
        self::assertEquals(HtmlCleaner::CombinedMode, $cleaner->idMode());
        self::assertEquals(HtmlCleaner::AllowListMode, $cleaner->classMode());
        self::assertEquals([], $cleaner->allowedTags());
        self::assertEquals([], $cleaner->allowedIds());
        self::assertEquals([], $cleaner->allowedClasses());
        self::assertEquals([], $cleaner->deniedTags());
        self::assertEquals([], $cleaner->deniedIds());
        self::assertEquals([], $cleaner->deniedClasses());
    }

    /** Ensure we get the expected exception when an invalid tag mode is given in the constructor. */
    public function testConstructorWithInvalidTagMode()
    {
        self::expectException(InvalidArgumentException::class);
        $cleaner = new HtmlCleaner(tagMode: self::InvalidMode);
    }

    /** Ensure we get the expected exception when an invalid id mode is given in the constructor. */
    public function testConstructorWithInvalidIdMode()
    {
        self::expectException(InvalidArgumentException::class);
        $cleaner = new HtmlCleaner(idMode: self::InvalidMode);
    }

    /** Ensure we get the expected exception when an invalid tag mode is given in the constructor. */
    public function testConstructorWithInvalidClassMode()
    {
        self::expectException(InvalidArgumentException::class);
        $cleaner = new HtmlCleaner(classMode: self::InvalidMode);
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
        ];
    }

    public function testTagMode(): void
    {
        $oldMode = $this->testCleaner->tagMode();

        if ($oldMode === HtmlCleaner::DenyListMode) {
            self::markTestSkipped("Test cleaner's existing tag mode is the same as the mode we're testing with.");
        }

        $this->testCleaner->setTagMode(HtmlCleaner::DenyListMode);
        self::assertEquals(HtmlCleaner::DenyListMode, $this->testCleaner->tagMode());
    }

    public function testIdMode(): void
    {
        $oldMode = $this->testCleaner->idMode();

        if ($oldMode === HtmlCleaner::DenyListMode) {
            self::markTestSkipped("Test cleaner's existing id mode is the same as the mode we're testing with.");
        }

        $this->testCleaner->setIdMode(HtmlCleaner::DenyListMode);
        self::assertEquals(HtmlCleaner::DenyListMode, $this->testCleaner->IdMode());
    }

    public function testClassMode(): void
    {
        $oldMode = $this->testCleaner->classMode();

        if ($oldMode === HtmlCleaner::DenyListMode) {
            self::markTestSkipped("Test cleaner's existing class mode is the same as the mode we're testing with.");
        }

        $this->testCleaner->setClassMode(HtmlCleaner::DenyListMode);
        self::assertEquals(HtmlCleaner::DenyListMode, $this->testCleaner->ClassMode());
    }

    public function testTagModeThrows(): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->setTagMode(self::InvalidMode);
    }

    public function testIdModeThrows(): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->setIdMode(self::InvalidMode);
    }

    public function testClassModeThrows(): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->setClassMode(self::InvalidMode);
    }

    /**
     * Provides valid data for Tag list tests.
     *
     * @return iterable The test data.
     */
    public function dataForTestTags(): iterable
    {
        yield "empty_Tag_list" => [[], [],];
        yield "single_string" => ["some-tag", ["some-tag"],];
        yield "array_several_strings" => [["some-tag", "some-other-tag", "another-tag",], ["some-tag", "some-other-tag", "another-tag",],];
        yield "array_several_strings_with_duplicates" => [["some-tag", "some-duplicate-tag", "another-tag", "some-duplicate-tag",], ["some-tag", "some-duplicate-tag", "another-tag",],];
        yield "array_same_string_extreme_number_of_times" => [["some-duplicate-tag", "some-duplicate-tag", "some-duplicate-tag", "some-duplicate-tag", "some-duplicate-tag", "some-duplicate-tag"], ["some-duplicate-tag"],];
    }

    /**
     * Provides invalid data for ID list tests.
     *
     * @return iterable The test data.
     */
    public function dataForTestTagsThrows(): iterable
    {
        yield "containsWhitespaceArray" => [["some tag"]];
        yield "containsWhitespaceString" => ["some tag"];
        yield "emptyStringArray" => [[""]];
        yield "emptyStringString" => [""];
    }

    /**
     * Ensure allowTags() accepts valid data.
     *
     * @dataProvider dataForTestTags
     *
     * @param array<string>|string $allowList The allowlist to test.
     * @param array<string> $expected The expected outcome allow list.
     */
    public function testAllowTags(array|string $allowList, array $expected)
    {
        $this->testCleaner->allowTags($allowList);
        self::assertEqualsCanonicalizing($expected, $this->testCleaner->allowedTags());
    }

    /**
     * Ensure allowTags() throws with invalid content.
     *
     * @dataProvider dataForTestTagsThrows
     */
    public function testAllowTagsThrows(array|string $allowList): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->allowTags($allowList);
    }

    /**
     * Ensure allowTags does not alter state with invalid content.
     * @dataProvider dataForTestTagsThrows
     */
    public function testAllowTagsPreservesState(array|string $allowList): void
    {
        $before = ["one", "two",];
        $this->testCleaner->allowTags($before);

        if ($before !== $this->testCleaner->allowedTags()) {
            self::markTestSkipped("The existing set of allowed ids is not as expected.");
        }

        try {
            $this->testCleaner->allowTags($allowList);
            self::fail("For this test, allowTags() is expected to throw.");
        } catch (InvalidArgumentException $err) {
            self::assertEqualsCanonicalizing($before, $this->testCleaner->allowedTags());
        }
    }

    /**
     * Ensure denyTags() accepts valid data.
     *
     * @dataProvider dataForTestTags
     *
     * @param array<string>|string $denyList The denylist to test.
     * @param array<string> $expected The expected outcome deny list.
     */
    public function testDenyTags(array|string $denyList, array $expected)
    {
        $this->testCleaner->denyTags($denyList);
        self::assertEqualsCanonicalizing($expected, $this->testCleaner->deniedTags());
    }

    /**
     * Ensure denyTags() throws with invalid content.
     *
     * @dataProvider dataForTestTagsThrows
     */
    public function testDenyTagsThrows(array|string $denyList): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->denyTags($denyList);
    }

    /**
     * Ensure denyTags does not alter state with invalid content.
     * @dataProvider dataForTestTagsThrows
     */
    public function testDenyTagsPreservesState(array|string $denyList): void
    {
        $before = ["one", "two",];
        $this->testCleaner->denyTags($before);

        if ($before !== $this->testCleaner->deniedTags()) {
            self::markTestSkipped("The existing set of denied ids is not as expected.");
        }

        try {
            $this->testCleaner->denyTags($denyList);
            self::fail("For this test, denyTags() is expected to throw.");
        } catch (InvalidArgumentException $err) {
            self::assertEqualsCanonicalizing($before, $this->testCleaner->deniedTags());
        }
    }

    /**
     * Provides valid data for Id list tests.
     *
     * @return iterable The test data.
     */
    public function dataForTestIds(): iterable
    {
        yield "empty_Id_list" => [[], [],];
        yield "single_string" => ["some-id", ["some-id"],];
        yield "array_several_strings" => [["some-id", "some-other-id", "another-id",], ["some-id", "some-other-id", "another-id",],];
        yield "array_several_strings_with_duplicates" => [["some-id", "some-duplicate-id", "another-id", "some-duplicate-id",], ["some-id", "some-duplicate-id", "another-id",],];
        yield "array_same_string_extreme_number_of_times" => [["some-duplicate-id", "some-duplicate-id", "some-duplicate-id", "some-duplicate-id", "some-duplicate-id", "some-duplicate-id"], ["some-duplicate-id"],];
    }

    /**
     * Provides invalid data for ID list tests.
     *
     * @return iterable The test data.
     */
    public function dataForTestIdsThrows(): iterable
    {
        yield "containsWhitespaceArray" => [["some string"]];
        yield "containsWhitespaceString" => ["some string"];
        yield "emptyStringArray" => [[""]];
        yield "emptyStringString" => [""];
    }

    /**
     * Ensure allowIds() accepts valid data.
     *
     * @dataProvider dataForTestIds
     *
     * @param array<string>|string $allowList The allowlist to test.
     * @param array<string> $expected The expected outcome allow list.
     */
    public function testAllowIds(array|string $allowList, array $expected)
    {
        $this->testCleaner->allowIds($allowList);
        self::assertEqualsCanonicalizing($expected, $this->testCleaner->allowedIds());
    }

    /**
     * Ensure allowIds() throws with invalid content.
     *
     * @dataProvider dataForTestIdsThrows
     */
    public function testAllowIdsThrows(array|string $allowList): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->allowIds($allowList);
    }

    /**
     * Ensure allowIds does not alter state with invalid content.
     * @dataProvider dataForTestIdsThrows
     */
    public function testAllowIdsPreservesState(array|string $allowList): void
    {
        $before = ["one", "two",];
        $this->testCleaner->allowIds($before);

        if ($before !== $this->testCleaner->allowedIds()) {
            self::markTestSkipped("The existing set of allowed ids is not as expected.");
        }

        try {
            $this->testCleaner->allowIds($allowList);
            self::fail("For this test, allowIds() is expected to throw.");
        } catch (InvalidArgumentException $err) {
            self::assertEqualsCanonicalizing($before, $this->testCleaner->allowedIds());
        }
    }

    /**
     * Ensure denyIds() accepts valid data.
     *
     * @dataProvider dataForTestIds
     *
     * @param array<string>|string $denyList The denylist to test.
     * @param array<string> $expected The expected outcome deny list.
     */
    public function testDenyIds(array|string $denyList, array $expected)
    {
        $this->testCleaner->denyIds($denyList);
        self::assertEqualsCanonicalizing($expected, $this->testCleaner->deniedIds());
    }

    /**
     * Ensure denyIds() throws with invalid content.
     *
     * @dataProvider dataForTestIdsThrows
     */
    public function testDenyIdsThrows(array|string $denyList): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->denyIds($denyList);
    }

    /**
     * Ensure denyIds does not alter state with invalid content.
     * @dataProvider dataForTestIdsThrows
     */
    public function testDenyIdsPreservesState(array|string $denyList): void
    {
        $before = ["one", "two",];
        $this->testCleaner->denyIds($before);

        if ($before !== $this->testCleaner->deniedIds()) {
            self::markTestSkipped("The existing set of denied ids is not as expected.");
        }

        try {
            $this->testCleaner->denyIds($denyList);
            self::fail("For this test, denyIds() is expected to throw.");
        } catch (InvalidArgumentException $err) {
            self::assertEqualsCanonicalizing($before, $this->testCleaner->deniedIds());
        }
    }

    /**
     * Provide test data for class lists.
     *
     * @return iterable The test data.
     */
    public function dataForTestClassList(): iterable
    {
        yield "empty_class_list" => [[], [],];
        yield "single_string" => ["dangerous", ["dangerous"],];
        yield "array_several_strings" => [["dangerous", "tracking-pixel", "auto-ajax-content",], ["tracking-pixel", "dangerous", "auto-ajax-content"],];
        yield "array_several_strings_with_duplicates" => [["dangerous", "tracking-pixel", "dangerous", "auto-ajax-content", "tracking-pixel", "auto-ajax-content", "dangerous", "auto-ajax-content", "dangerous", "tracking-pixel"], ["tracking-pixel", "dangerous", "auto-ajax-content"],];
        yield "array_same_string_extreme_number_of_times" => [["dangerous", "dangerous", "dangerous", "dangerous", "dangerous", "dangerous"], ["dangerous"],];
    }

    public function dataForTestClassesThrows(): iterable
    {
        yield "containsWhitespaceArray" => [["some string"]];
        yield "containsWhitespaceString" => ["some string"];
        yield "emptyStringArray" => [[""]];
        yield "emptyStringString" => [""];
    }

    /**
     * @dataProvider dataForTestClassList
     *
     * @param array<string>|string $denyList The denylist to test.
     * @param array<string> $expected The expected outcome deny list.
     */
    public function testDenyClasses(array|string $denyList, array $expected)
    {
        $this->testCleaner->denyClasses($denyList);
        self::assertEqualsCanonicalizing($expected, $this->testCleaner->deniedClasses());
    }

    /**
     * Ensure denyClasses() throws with invalid content.
     *
     * @dataProvider dataForTestClassesThrows
     */
    public function testDenyClassesThrows(array|string $denyList): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->denyClasses($denyList);
    }

    /**
     * Ensure denyClasses does not alter state with invalid content.
     * @dataProvider dataForTestClassesThrows
     */
    public function testDenyClassesPreservesState(array|string $denyList): void
    {
        $before = ["one", "two",];
        $this->testCleaner->denyClasses($before);

        if ($before !== $this->testCleaner->deniedClasses()) {
            self::markTestSkipped("The existing set of denied classes is not as expected.");
        }

        try {
            $this->testCleaner->denyClasses($denyList);
            self::fail("For this test, denyClasses() is expected to throw.");
        } catch (InvalidArgumentException $err) {
            self::assertEqualsCanonicalizing($before, $this->testCleaner->deniedClasses());
        }
    }

    /**
     * @dataProvider dataForTestClassList
     *
     * @param array<string>|string $allowList The Allowlist to test.
     * @param array<string> $expected The expected outcome Allow list.
     */
    public function testAllowClasses(array|string $allowList, array $expected)
    {
        $this->testCleaner->allowClasses($allowList);
        self::assertEqualsCanonicalizing($expected, $this->testCleaner->allowedClasses());
    }

    /**
     * Ensure AllowClasses() throws with invalid content.
     *
     * @dataProvider dataForTestClassesThrows
     */
    public function testAllowClassesThrows(array|string $allowList): void
    {
        self::expectException(InvalidArgumentException::class);
        $this->testCleaner->allowClasses($allowList);
    }

    /**
     * Ensure AllowClasses does not alter state with invalid content.
     * @dataProvider dataForTestClassesThrows
     */
    public function testAllowClassesPreservesState(array|string $allowList): void
    {
        $before = ["one", "two",];
        $this->testCleaner->allowClasses($before);

        if ($before !== $this->testCleaner->allowedClasses()) {
            self::markTestSkipped("The existing set of allowed classes is not as expected.");
        }

        try {
            $this->testCleaner->allowClasses($allowList);
            self::fail("For this test, allowClasses() is expected to throw.");
        } catch (InvalidArgumentException $err) {
            self::assertEqualsCanonicalizing($before, $this->testCleaner->allowedClasses());
        }
    }

    /**
     * Data provider for testIsAllowedTag()
     *
     * @return iterable The test data.
     */
    public function dataForTestIsAllowedTag(): iterable
    {
        yield "typicalSpan-CombinedMode-NoDenyList-Allowed" => ["span", ["span",], [], HtmlCleaner::CombinedMode, true,];
        yield "typicalSpan-CombinedMode-NoDenyList-NotAllowed" => ["span", ["div",], [], HtmlCleaner::CombinedMode, false,];
        yield "typicalDiv-CombinedMode-NoDenyList-Allowed" => ["div", ["div",], [], HtmlCleaner::CombinedMode, true,];
        yield "typicalDiv-CombinedMode-NoDenyList-NotAllowed" => ["div", ["section",], [], HtmlCleaner::CombinedMode, false,];
        yield "typicalSpan-CombinedMode-NoDenyList-MultipleAllowed" => ["span", ["span", "div", "section", "article", ], [], HtmlCleaner::CombinedMode, true,];
        yield "typicalSpan-CombinedMode-NoDenyList-MultipleNotAllowed" => ["span", ["div", "section", "article", "nav",], [], HtmlCleaner::CombinedMode, false,];
        yield "typicalDiv-CombinedMode-NoDenyList-MultipleAllowed" => ["div", ["div", "section", "article",], [], HtmlCleaner::CombinedMode, true,];
        yield "typicalDiv-CombinedMode-NoDenyList-MultipleNotAllowed" => ["div", ["section", "article", "nav",], [], HtmlCleaner::CombinedMode, false,];
        yield "extremeDivSpace-CombinedMode-NoDenyList-DivAllowed" => ["div ", ["div",], [], HtmlCleaner::CombinedMode, false,];
        yield "extremeSpanSpace-CombinedMode-NoDenyList-SpanAllowed" => ["span ", ["span",], [], HtmlCleaner::CombinedMode, false,];

        yield "typicalSpan-CombinedMode-NoAllowList-NotAllowed" => ["span", [], ["span",], HtmlCleaner::CombinedMode, false,];
        yield "typicalDiv-CombinedMode-NoAllowList-NotAllowed" => ["div", [], ["div",], HtmlCleaner::CombinedMode, false,];
        yield "typicalSpan-CombinedMode-NoAllowList-Multiple-NotAllowed" => ["span", [], ["span", "div", "section", "article", ], HtmlCleaner::CombinedMode, false,];
        yield "typicalDiv-CombinedMode-NoAllowList-Multiple-NotAllowed" => ["div", [], ["div", "section", "article",], HtmlCleaner::CombinedMode, false,];
        yield "extremeDivSpace-CombinedMode-NoAllowList-Div-NotAllowed" => ["div ", [], ["div",], HtmlCleaner::CombinedMode, false,];
        yield "extremeSpanSpace-CombinedMode-NoAllowList-Span-NotAllowed" => ["span ", [], ["span",], HtmlCleaner::CombinedMode, false,];

        yield "typicalSpan-AllowListMode-NoDenyList-Allowed" => ["span", ["span",], [], HtmlCleaner::AllowListMode, true,];
        yield "typicalSpan-AllowListMode-NoDenyList-NotAllowed" => ["span", ["div",], [], HtmlCleaner::AllowListMode, false,];
        yield "typicalDiv-AllowListMode-NoDenyList-Allowed" => ["div", ["div",], [], HtmlCleaner::AllowListMode, true,];
        yield "typicalDiv-AllowListMode-NoDenyList-NotAllowed" => ["div", ["section",], [], HtmlCleaner::AllowListMode, false,];
        yield "typicalSpan-AllowListMode-NoDenyList-MultipleAllowed" => ["span", ["span", "div", "section", "article", ], [], HtmlCleaner::AllowListMode, true,];
        yield "typicalSpan-AllowListMode-NoDenyList-MultipleNotAllowed" => ["span", ["div", "section", "article", "nav",], [], HtmlCleaner::AllowListMode, false,];
        yield "typicalDiv-AllowListMode-NoDenyList-MultipleAllowed" => ["div", ["div", "section", "article",], [], HtmlCleaner::AllowListMode, true,];
        yield "typicalDiv-AllowListMode-NoDenyList-MultipleNotAllowed" => ["div", ["section", "article", "nav",], [], HtmlCleaner::AllowListMode, false,];
        yield "extremeDivSpace-AllowListMode-NoDenyList-DivAllowed" => ["div ", ["div",], [], HtmlCleaner::AllowListMode, false,];
        yield "extremeSpanSpace-AllowListMode-NoDenyList-SpanAllowed" => ["span ", ["span",], [], HtmlCleaner::AllowListMode, false,];

        yield "typicalSpan-AllowListMode-NoAllowList-NotAllowed" => ["span", [], ["span",], HtmlCleaner::AllowListMode, false,];
        yield "typicalDiv-AllowListMode-NoAllowList-NotAllowed" => ["div", [], ["div",], HtmlCleaner::AllowListMode, false,];
        yield "typicalSpan-AllowListMode-NoAllowList-Multiple-NotAllowed" => ["span", [], ["span", "div", "section", "article", ], HtmlCleaner::AllowListMode, false,];
        yield "typicalDiv-AllowListMode-NoAllowList-Multiple-NotAllowed" => ["div", [], ["div", "section", "article",], HtmlCleaner::AllowListMode, false,];
        yield "extremeDivSpace-AllowListMode-NoAllowList-Div-NotAllowed" => ["div ", [], ["div",], HtmlCleaner::AllowListMode, false,];
        yield "extremeSpanSpace-AllowListMode-NoAllowList-Span-NotAllowed" => ["span ", [], ["span",], HtmlCleaner::AllowListMode, false,];
    }

    /**
     * @dataProvider dataForTestIsAllowedTag
     */
    public function testIsAllowedTag(string $tag, array $allowedTags, array $deniedTags, int $mode, bool $expected): void
    {
        $this->testCleaner->allowTags($allowedTags);
        $this->testCleaner->denyTags($deniedTags);
        $this->testCleaner->setTagMode($mode);
        self::assertSame($expected, $this->testCleaner->isAllowedTag($tag));
    }

    /**
     * Data provider for testIsAllowedId()
     *
     * @return iterable The test data.
     */
    public function dataForTestIsAllowedId(): iterable
    {
        yield "allowedCombinedWithNoDenyList" => ["some-id", ["some-id",], [], HtmlCleaner::CombinedMode, true,];
        yield "forbiddenCombinedWithNoDenyList" => ["forbidden-id", ["some-id",], [], HtmlCleaner::CombinedMode, false,];
        yield "forbiddenCombinedWithNoAllowList" => ["some-id", [], ["some-id",], HtmlCleaner::CombinedMode, false,];

        yield "allowedAllowModeWithNoDenyList" => ["some-id", ["some-id",], [], HtmlCleaner::AllowListMode, true,];
        yield "forbiddenAllowModeWithNoAllowList" => ["some-id", [], ["some-id",], HtmlCleaner::AllowListMode, false,];

        yield "allowedDenyModeWithNoDenyList" => ["some-id", ["some-other-id",], [], HtmlCleaner::DenyListMode, true,];
        yield "forbiddenDenyModeWithNoAllowList" => ["some-id", [], ["some-id",], HtmlCleaner::DenyListMode, false,];
    }

    /**
     * @dataProvider dataForTestIsAllowedId
     */
    public function testIsAllowedId(string $tag, array $allowedIds, array $deniedIds, int $mode, bool $expected): void
    {
        $this->testCleaner->allowIds($allowedIds);
        $this->testCleaner->denyIds($deniedIds);
        $this->testCleaner->setIdMode($mode);
        self::assertSame($expected, $this->testCleaner->isAllowedId($tag));
    }

    /**
     * Data provider for testIsAllowedClass()
     *
     * @return iterable The test data.
     */
    public function dataForTestIsAllowedClass(): iterable
    {
        yield "allowedCombinedWithNoDenyList" => ["some-class", ["some-class",], [], HtmlCleaner::CombinedMode, true,];
        yield "forbiddenCombinedWithNoDenyList" => ["forbidden-class", ["some-class",], [], HtmlCleaner::CombinedMode, false,];
        yield "forbiddenCombinedWithNoAllowList" => ["some-class", [], ["some-class",], HtmlCleaner::CombinedMode, false,];

        yield "allowedAllowModeWithNoDenyList" => ["some-class", ["some-class",], [], HtmlCleaner::AllowListMode, true,];
        yield "forbiddenAllowModeWithNoAllowList" => ["some-class", [], ["some-class",], HtmlCleaner::AllowListMode, false,];

        yield "allowedDenyModeWithNoDenyList" => ["some-class", ["some-other-class",], [], HtmlCleaner::DenyListMode, true,];
        yield "forbiddenDenyModeWithNoAllowList" => ["some-class", [], ["some-class",], HtmlCleaner::DenyListMode, false,];

        yield "allowedMaultipleClassesCombinedWithNoDenyList" => ["some-class some-other-class", ["some-class", "some-other-class"], [], HtmlCleaner::CombinedMode, true,];
        yield "forbiddenMaultipleClassesCombinedWithNoDenyList" => ["forbidden-class some-class", ["some-class",], [], HtmlCleaner::CombinedMode, false,];
        yield "forbiddenMaultipleClassesCombinedWithNoAllowList" => ["some-class some-other-class", [], ["some-class",], HtmlCleaner::CombinedMode, false,];

        yield "allowedMaultipleClassesAllowModeWithNoDenyList" => ["some-class some-other-class", ["some-class", "some-other-class",], [], HtmlCleaner::AllowListMode, true,];
        yield "forbiddenMaultipleClassesAllowModeWithNoAllowList" => ["some-class some-other-class", [], ["some-class",], HtmlCleaner::AllowListMode, false,];

        yield "allowedMaultipleClassesDenyModeWithNoDenyList" => ["some-class some-other-class", ["some-other-class",], [], HtmlCleaner::DenyListMode, true,];
        yield "forbiddenMaultipleClassesDenyModeWithNoAllowList" => ["some-class some-other-class", [], ["some-class",], HtmlCleaner::DenyListMode, false,];
    }

    /**
     * @dataProvider dataForTestIsAllowedClass
     */
    public function testIsAllowedClass(string $class, array $allowedClasses, array $deniedClasses, int $mode, bool $expected): void
    {
        $this->testCleaner->allowClasses($allowedClasses);
        $this->testCleaner->denyClasses($deniedClasses);
        $this->testCleaner->setClassMode($mode);
        self::assertSame($expected, $this->testCleaner->isAllowedClassAttribute($class));
    }

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
            $this->testCleaner->setTagMode($config->tags->mode);
            $this->testCleaner->allowTags($config->tags->allowList);
            $this->testCleaner->denyTags($config->tags->denyList);

            $this->testCleaner->setIdMode($config->ids->mode);
            $this->testCleaner->allowIds($config->ids->allowList);
            $this->testCleaner->denyIds($config->ids->denyList);

            $this->testCleaner->setClassMode($config->classes->mode);
            $this->testCleaner->allowClasses($config->classes->allowList);
            $this->testCleaner->denyClasses($config->classes->denyList);
        } catch (InvalidArgumentException $err) {
            $this->markTestSkipped("Exception {$err} configuring cleaner - check config in test data");
        }

        $actualHtml = $this->testCleaner->clean($testHtml);
        self::assertSame($expectedHtml, $actualHtml);
    }

    /** Ensure clean() throws when the HTML can't be parsed. */
    public function testCleanThrows(): void
    {
        $this->mockMethod(DOMDocument::class, "loadXml", false);
        self::expectException(RuntimeException::class);
        $this->testCleaner->clean("");
    }
}
