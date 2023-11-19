<?php

declare(strict_types=1);

namespace Bead\Util;

use DOMDocument;
use DOMNode;
use InvalidArgumentException;
use RuntimeException;
use StdClass;

use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Str\toCodePoints;

/**
 * Sanitise HTML according to configurable rules.
 *
 * HTML is sanitised according to three types of rules:
 * - allow/deny lists based on tag names;
 * - allow/deny lists based on classes;
 * - allow/deny lists based on ids.
 *
 * In all three cases, the allow-lists and deny-lists can be enabled or disabled independently, so you can use any
 * combination of allow/deny lists. Allow-lists and deny-lists can be used together. Deny-lists, when enabled,
 * *always* take precedence over allow-lists - for example, if an ID is on the ID deny-list it's forbidden, regardless
 * of whether it's also on the allow-list.
 *
 * Set which lists are enabled for each rule type with setTagMode(), setClassMode() and setIdMode(). These have getter
 * corollaries in tagMode(), classMode() and idMode(). The content of the allow- and deny-lists for the tag name lists
 * is set using allowTags() and denyTags(). Use allowClasses() and denyClasses() for classes and allowIds() and
 * denyIds() for IDs.
 *
 * @package Bead
 */
class HtmlCleaner
{
    /** Flag to turn on allow-list mode checking for a feature. */
    public const AllowListMode = 0x01;

    /** Flag to turn on deny-list mode checking for a feature. */
    public const DenyListMode = 0x02;

    /** Flag to turn on both allow-list and deny-list mode checking for a feature. */
    public const CombinedMode = self::AllowListMode | self::DenyListMode;

    public const CommonFormattingAllowlist = [
        "article", "section", "nav", "aside", "h1", "h2", "h3", "h4", "h5", "h6",
        "header", "footer", "address", "p", "pre", "blockquote", "ol", "ul", "li",
        "dl", "dt", "dd", "figure", "figcaption", "div", "main", "hr", "em", "strong",
        "cite", "q", "dfn", "abbr", "data", "time", "code", "var", "samp", "kbd",
        "mark", "ruby", "rb", "rt", "rp", "rtc", "bdi", "bdo", "span", "br", "wbr",
        "table", "tr", "td", "th", "caption", "tbody", "thead", "tfoot", "colgroup", "col",
    ];

    /** @var array List of strings for tags that are *always* stripped regardless of operation mode or list content. */
    private const FixedDenyList = [
        "script", "head",
    ];

    /** @var string The user's character set. */
    private string $userCharset = "UTF-8";

    /** @var int The mode of operation for tag name cleaning. */
    private int $tagMode = self::CombinedMode;

    /** @var int The mode of operation for class name cleaning. */
    private int $classMode = self::CombinedMode;

    /** @var int The mode of operation for ID cleaning. */
    private int $idMode = self::CombinedMode;

    /** @var array The tag allow list. */
    private array $allowedTags = [];

    /** @var array The tag deny list. */
    private array $deniedTags = [];

    /** @var array The class allow list. */
    private array $allowedClasses = [];

    /** @var array The class deny list. */
    private array $deniedClasses = [];

    /** @var array The id allow list. */
    private array $allowedIds = [];

    /** @var array The id deny list. */
    private array $deniedIds = [];

    /**
     * Initialise a new HtmlCleaner object.
     *
     * The default mode of operation is for all filters to operate in both deny-list and allow-list modes. The
     * allow-lists and deny-lists are all empty to begin with, which means only the fixed deny-list of tag names will
     * be removed.
     *
     * @param int $tagMode The mode of operation for the tag name filter.
     * @param int $classMode The mode of operation for the class attribute filter.
     * @param int $idMode The mode of operation for the id attribute filter.
     *
     * @throws InvalidArgumentException if the tag, class or ID mode is not valid.
     */
    public function __construct(int $tagMode = self::CombinedMode, int $classMode = self::CombinedMode, int $idMode = self::CombinedMode)
    {
        $this->setTagMode($tagMode);
        $this->setClassMode($classMode);
        $this->setIdMode($idMode);
    }

    /**
     * Set how the cleaner handles tag name cleaning.
     *
     * The mode is always one of the class mode constants. Providing anything else is undefined behaviour.
     *
     * @param int $mode The mode for tag checking.
     *
     * @throws InvalidArgumentException if the mode is not valid.
     */
    public function setTagMode(int $mode): void
    {
        self::checkMode($mode);
        $this->tagMode = $mode;
    }

    /**
     * Set how the cleaner handles class attribute cleaning.
     *
     * The mode is always one of the class mode constants. Providing anything else is undefined behaviour.
     *
     * @param int $mode The mode for class checking.
     *
     * @throws InvalidArgumentException if the mode is not valid.
     */
    public function setClassMode(int $mode): void
    {
        self::checkMode($mode);
        $this->classMode = $mode;
    }

    /**
     * Set how the cleaner handles id attribute cleaning.
     *
     * The mode is always one of the class mode constants. Providing anything else is undefined behaviour.
     *
     * @param int $mode The mode for id attribute checking.
     *
     * @throws InvalidArgumentException if the mode is not valid.
     */
    public function setIdMode(int $mode): void
    {
        self::checkMode($mode);
        $this->idMode = $mode;
    }

    /**
     * Check a provided allow/deny mode.
     *
     * @param int $mode The mode to check.
     *
     * @throws InvalidArgumentException if the mode is not valid.
     */
    final protected static function checkMode(int $mode): void
    {
        if (self::AllowListMode != $mode && self::DenyListMode != $mode && self::CombinedMode != $mode) {
            throw new InvalidArgumentException("Test mode for attributes and tag names must be one of the class test mode constants.");
        }
    }

    /**
     * Fetch the mode of operation for the tag name filter.
     *
     * @return int The mode.
     */
    public function tagMode(): int
    {
        return $this->tagMode;
    }

    /**
     * Fetch the mode of operation for the class attribute filter.
     *
     * @return int The mode.
     */
    public function classMode(): int
    {
        return $this->classMode;
    }

    /**
     * Fetch the mode of operation for the ID attribute filter.
     *
     * @return int The mode.
     */
    public function idMode(): int
    {
        return $this->idMode;
    }

    /**
     * Helper to determine whether a Unicode code point is valid for use as the first character in a HTML tag name.
     *
     * @param int $codePoint The code point to check.
     *
     * @return bool `true` if it may be used as the first character in an HTML tag name, `false` if not.
     */
    final protected static function isValidTagStartCodepoint(int $codePoint): bool
    {
        return (ord(":") == $codePoint) ||
            (ord("A") <= $codePoint && ord("Z") >= $codePoint) ||
            ord("_") == $codePoint ||
            (ord("a") <= $codePoint && ord("z") >= $codePoint) ||
            (0xC0 <= $codePoint && 0xD6 >= $codePoint) ||
            (0xD8 <= $codePoint && 0xF6 >= $codePoint) ||
            (0xF8 <= $codePoint && 0x2FF >= $codePoint) ||
            (0x370 <= $codePoint && 0x37D >= $codePoint) ||
            (0x37F <= $codePoint && 0x1FFF >= $codePoint) ||
            (0x200C <= $codePoint && 0x200D >= $codePoint) ||
            (0x2070 <= $codePoint && 0x218F >= $codePoint) ||
            (0x2C00 <= $codePoint && 0x2FEF >= $codePoint) ||
            (0x3001 <= $codePoint && 0xD7FF >= $codePoint) ||
            (0xF900 <= $codePoint && 0xFDCF >= $codePoint) ||
            (0xFDF0 <= $codePoint && 0xFFFD >= $codePoint) ||
            (0x10000 <= $codePoint && 0xEFFFF);
    }

    /**
     * Helper to determine whether a Unicode code point is valid for use in a HTML tag name in a position other than the
     * first character.
     *
     * @param int $codePoint The code point to check.
     *
     * @return bool `true` if it may be used in an HTML tag name, `false` if not.
     */
    final protected static function isValidTagCodepoint(int $codePoint): bool
    {
        return self::isValidTagStartCodepoint($codePoint) ||
            ord("-") == $codePoint ||
            ord(".") == $codePoint ||
            (ord("0") <= $codePoint && ord("9") >= $codePoint) ||
            0xB7 == $codePoint ||
            (0x0300 <= $codePoint && 0x036F >= $codePoint) ||
            (0x203F <= $codePoint && 0x2040 >= $codePoint);
    }

    /**
     * Helper to determine whether a provided tag name is valid for HTML.
     *
     * @param string $name The name to test.
     *
     * @return bool `true` if the tag name is valid, `false` if not.
     */
    final protected function isValidTagName(string $name): bool
    {
        if ("" === $name) {
            return false;
        }

        $codePoints = toCodePoints($name, $this->userCharset);

        return self::isValidTagStartCodepoint($codePoints[0]) && all(array_slice($codePoints, 1), function (int $codePoint): bool {
            return self::isValidTagCodepoint($codePoint);
        });
    }

    final protected function isValidClass(string $name): bool
    {
        if ("" === $name) {
            return false;
        }

        // TODO implement completely according to spec
        return false === mb_strpos($name, " ", 0, $this->userCharset);
    }

    final protected function isValidId(string $id): bool
    {
        if ("" === $id) {
            return false;
        }

        // TODO implement completely according to spec
        return false === mb_strpos($id, " ", 0, $this->userCharset);
    }

    /**
     * Helper to add content to deny or allow lists.
     *
     * You must provide valid items - either a single non-empty string or an array full of non-empty strings. Assertions
     * guard this during testing/development. All items will be converted to lower case and duplicate items will not
     * end up in the target list.
     *
     * @param array<int,string> $list Reference to the list to add to.
     * @param array<int,string>|string $items The items to add.
     * @param callable $validator A function to use to validate the items before they are added to the list.
     *
     * @throws InvalidArgumentException if $items is not a string or array, or if it contains an invalid entry for the
     * list
     */
    final protected function addToList(array & $list, array|string $items, callable $validator): void
    {
        if (is_string($items)) {
            $items = [$items];
        }

        if (!all($items, $validator)) {
            throw new InvalidArgumentException("Invalid value found in allow or deny list.");
        }

        array_walk($items, function (string & $item) {
            $item = mb_strtolower($item, $this->userCharset);
        });

        /** @var array<int,string> $items Hint for Psalm that array_walk doesn't change the type of $items */

        $list = array_unique([...$list, ...$items,]);
    }

    /**
     * Add tags to the allow-list.
     *
     * If the allow-list is enabled, any element whose tag name is not on the allow-list will be stripped from the
     * cleaned HTML.
     *
     * None of the tags to add may be an empty string. Providing an empty string or an array containing one or more
     * empty strings will result in undefined behaviour. The tags will be converted to lower-case for the allow-list,
     * and duplicates will not be added.
     *
     * @param $tags array<string>|string The tag(s) to add to the allow-list.
     *
     * @throws InvalidArgumentException if $tags is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function allowTags(array|string $tags): void
    {
        $this->addToList($this->allowedTags, $tags, fn (string $tagName): bool => $this->isValidTagName($tagName));
    }

    /**
     * Add tags to the deny-list.
     *
     * If the deny-list is enabled, any element whose tag name is on the deny-list will be stripped from the cleaned
     * HTML.
     *
     * None of the tags to add may be an empty string. Providing an empty string or an array containing one or more
     * empty strings will result in undefined behaviour. The tags will be converted to lower-case for the deny-list, and
     * duplicates will not be added.
     *
     * @param $tags array|string The tag(s) to add to the deny-list.
     *
     * @throws InvalidArgumentException if $tags is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function denyTags(array|string $tags): void
    {
        $this->addToList($this->deniedTags, $tags, fn (string $tagName): bool => $this->isValidTagName($tagName));
    }

    /**
     * Add classes to the allow-list.
     *
     * If the allow-list is enabled, any element whose class attribute contains a class that is not on the allow-list
     * will be stripped from the cleaned HTML.
     *
     * None of the classes to add may be an empty string. Providing an empty string or an array containing one or more
     * empty strings will result in undefined behaviour. The classes will be converted to lower-case for the
     * allow-list,
     * and duplicates will not be added.
     *
     * @param $classes array|string The classes(s) to add to the allow-list.
     *
     * @throws InvalidArgumentException if $classes is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function allowClasses(array|string $classes): void
    {
        $this->addToList($this->allowedClasses, $classes, fn (string $class): bool => $this->isValidClass($class));
    }

    /**
     * Add classes to the deny-list.
     *
     * If the deny-list is enabled, any element whose class attribute contains a class on the deny-list will be stripped
     * from the cleaned HTML.
     *
     * None of the classes to add may be an empty string. Providing an empty string or an array containing one or more
     * empty strings will result in undefined behaviour. The classes will be converted to lower-case for the deny-list,
     * and duplicates will not be added.
     *
     * @param $classes array|string The classes(s) to add to the deny-list.
     *
     * @throws InvalidArgumentException if $classes is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function denyClasses(array|string $classes): void
    {
        $this->addToList($this->deniedClasses, $classes, fn (string $class): bool => $this->isValidClass($class));
    }

    /**
     * Add IDs to the allow-list.
     *
     * If the allow-list is enabled, any element whose `id` attribute value is not on the allow-list will be stripped
     * from the cleaned HTML.
     *
     * None of the IDs to add may be an empty string. Providing an empty string or an array containing one or more
     * empty strings will result in undefined behaviour. The IDs will be converted to lower-case for the allow-list,
     * and
     * duplicates will not be added.
     *
     * @param $ids array|string The ID(s) to add to the allow-list.
     *
     * @throws InvalidArgumentException if $ids is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function allowIds(array|string $ids): void
    {
        $this->addToList($this->allowedIds, $ids, fn (string $id): bool => $this->isValidId($id));
    }

    /**
     * Add IDs to the deny-list.
     *
     * If the deny-list is enabled, any element whose `id` attribute value is on the deny-list will be stripped from
     * the cleaned HTML.
     *
     * None of the IDs to add may be an empty string. Providing an empty string or an array containing one or more
     * empty strings will result in undefined behaviour. The IDs will be converted to lower-case for the deny-list, and
     * duplicates will not be added.
     *
     * @param $ids array<string>|string The ID(s) to add to the deny-list.
     *
     * @throws InvalidArgumentException if $ids is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function denyIds(array|string $ids): void
    {
        $this->addToList($this->deniedIds, $ids, fn (string $id): bool => $this->isValidId($id));
    }

    /**
     * Fetch the list of allow-listed tag names.
     *
     * @return array<string> The allow-list.
     */
    public function allowedTags(): array
    {
        return $this->allowedTags;
    }

    /**
     * Fetch the list of deny-listed tag names.
     *
     * @return array<string> The deny-list.
     */
    public function deniedTags(): array
    {
        return $this->deniedTags;
    }

    /**
     * Fetch the list of allow-listed class names.
     *
     * @return array<string> The allow-list.
     */
    public function allowedClasses(): array
    {
        return $this->allowedClasses;
    }

    /**
     * Fetch the list of deny-listed class names.
     *
     * @return array The deny-list.
     */
    public function deniedClasses(): array
    {
        return $this->deniedClasses;
    }

    /**
     * Fetch the list of allow-listed IDs.
     *
     * @return array The allow-list.
     */
    public function allowedIds(): array
    {
        return $this->allowedIds;
    }

    /**
     * Fetch the list of deny-listed IDs.
     *
     * @return array The deny-list.
     */
    public function deniedIds(): array
    {
        return $this->deniedIds;
    }

    /**
     * Check whether a tag with a given name will pass the cleaner.
     *
     * The comparison is case-insensitive - tags on the deny-list (if enabled) or not on the allow-list (if enabled)
     * will fail regardless of case.
     *
     * @param string $tagName
     *
     * @return bool `true` if the ID attribute value is allowed, `false` if not.
     */
    public function isAllowedTag(string $tagName): bool
    {
        // all lists are in lower case
        $tagName = mb_strtolower($tagName, $this->userCharset);

        if (in_array($tagName, self::FixedDenyList)) {
            return false;
        }

        if (($this->tagMode & self::DenyListMode) && in_array($tagName, $this->deniedTags)) {
            return false;
        }

        if (($this->tagMode & self::AllowListMode) && !in_array($tagName, $this->allowedTags)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether the value of a class attribute will pass the cleaner.
     *
     * The comparison is case-insensitive - classes named in the attribute that are on the deny-list (if enabled) or not
     * on the allow-list (if enabled) will fail regardless of case.
     *
     * @param string $class
     *
     * @return bool `true` if the class attribute value is allowed, `false` if not.
     */
    public function isAllowedClassAttribute(string $class): bool
    {
        $class = mb_strtolower($class, $this->userCharset);
        $classes = preg_split("/[[:space:]]/", $class, 0, PREG_SPLIT_NO_EMPTY);

        if ($this->classMode & self::DenyListMode) {
            foreach ($classes as $class) {
                if (in_array($class, $this->deniedClasses)) {
                    return false;
                }
            }
        }

        if ($this->classMode & self::AllowListMode) {
            foreach ($classes as $class) {
                if (!in_array($class, $this->allowedClasses)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check whether the value of an ID attribute will pass the cleaner.
     *
     * The comparison is case-insensitive - ids on the deny-list (if enabled) or not on the allow-list (if enabled) will
     * fail regardless of case.
     *
     * @param string $id
     *
     * @return bool `true` if the ID attribute value is allowed, `false` if not.
     */
    public function isAllowedId(string $id): bool
    {
        // all lists are in lower case
        $id = mb_strtolower($id, $this->userCharset);

        if (($this->idMode & self::DenyListMode) && in_array($id, $this->deniedIds)) {
            return false;
        }

        if (($this->idMode & self::AllowListMode) && !in_array($id, $this->allowedIds)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether a DOM node will pass the cleaner.
     *
     * @param DOMNode $node The node to check.
     *
     * @return bool `true` if the node is allowed, `false` if not.
     */
    final protected function isAllowedNode(DOMNode $node): bool
    {
        if (XML_ELEMENT_NODE == $node->nodeType) {
            if (!$this->isAllowedTag($node->nodeName)) {
                return false;
            }

            if ($node->hasAttributes()) {
                $attr = $node->attributes->getNamedItem("id");

                if (isset($attr) && !$this->isAllowedId($attr->nodeValue)) {
                    return false;
                }

                $attr = $node->attributes->getNamedItem("class");

                if (isset($attr) && !$this->isAllowedClassAttribute($attr->nodeValue)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Clean some HTML according to the rules set in the cleaner.
     *
     * @param string $html The HTML to clean.
     *
     * @return string The cleaned HTML.
     * @throws RuntimeException if the HTML cannot be parsed.
     *
     */
    final public function clean(string $html): string
    {
        $doc = new DOMDocument("1.0", $this->userCharset);

        if (!$doc->loadXml("<htmlcleaner_root>{$html}</htmlcleaner_root>")) {
            throw new RuntimeException("failed to parse HTML");
        }

        $cleanNode = function (DOMNode $node) use (& $cleanNode): void {
            if ($node->hasChildNodes()) {
                // can't use foreach() because it will skip next node when a node is removed
                $idx = 0;

                while ($idx < $node->childNodes->length) {
                    $childNode = $node->childNodes[$idx];

                    if (!$this->isAllowedNode($childNode)) {
                        $node->removeChild($childNode);
                        continue;
                    }

                    $cleanNode($childNode);
                    ++$idx;
                }
            }
        };

        $cleanNode($doc->firstChild);
        $cleanHtml = "";

        foreach ($doc->firstChild->childNodes as $node) {
            $cleanHtml .= $doc->saveXML($node);
        }

        return $cleanHtml;
    }
}
