<?php

/**
 * @todo The DOM objects always work with UTF-8. We should therefore convert the content using the input charset before
 * providing it to the DOMDocument and convert it back when providing output.
 * @todo finish comprehensive unit test
 * @todo ability to config attributes to strip (while leaving the element in place)
 */

declare(strict_types = 1);

namespace Equit;

use DOMDocument;
use DOMNode;
use Exception;
use InvalidArgumentException;
use StdClass;

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
 * @package Equit
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
    private string $m_userCharset = "UTF-8";

    /** @var int The mode of operation for tag name cleaning. */
    private int $m_tagMode = self::CombinedMode;

    /** @var int The mode of operation for class name cleaning. */
    private int $m_classMode = self::CombinedMode;

    /** @var int The mode of operation for ID cleaning. */
    private int $m_idMode = self::CombinedMode;

    /** @var StdClass The tag deny/allow lists. */
    private StdClass $m_tags;

    /** @var StdClass The class name deny/allow lists. */
    private StdClass $m_classes;

    /** @var StdClass The ID deny/allow lists. */
    private StdClass $m_ids;

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
     */
    public function __construct(int $tagMode = self::CombinedMode, int $classMode = self::CombinedMode, int $idMode = self::CombinedMode)
    {
        $this->m_tags = (object) [
            "allowList" => [],
            "denyList" => [],
        ];

        $this->m_classes = (object) [
            "allowList" => [],
            "denyList" => [],
        ];

        $this->m_ids = (object) [
            "allowList" => [],
            "denyList" => [],
        ];

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
     */
    public function setTagMode(int $mode): void
    {
        if (self::AllowListMode != $mode && self::DenyListMode != $mode && self::CombinedMode != $mode) {
            throw new InvalidArgumentException("test mode for tag names must be one of the class test mode constants");
        }

        $this->m_tagMode = $mode;
    }

    /**
     * Set how the cleaner handles class attribute cleaning.
     *
     * The mode is always one of the class mode constants. Providing anything else is undefined behaviour.
     *
     * @param int $mode The mode for class checking.
     */
    public function setClassMode(int $mode): void
    {
        if (self::AllowListMode != $mode && self::DenyListMode != $mode && self::CombinedMode != $mode) {
            throw new InvalidArgumentException("test mode for class attributes must be one of the class test mode constants");
        }

        $this->m_classMode = $mode;
    }

    /**
     * Set how the cleaner handles id attribute cleaning.
     *
     * The mode is always one of the class mode constants. Providing anything else is undefined behaviour.
     *
     * @param int $mode The mode for id attribute checking.
     */
    public function setIdMode(int $mode): void
    {
        if (self::AllowListMode != $mode && self::DenyListMode != $mode && self::CombinedMode != $mode) {
            throw new InvalidArgumentException("test mode for id attributes must be one of the class test mode constants");
        }

        $this->m_idMode = $mode;
    }

    /**
     * Fetch the mode of operation for the tag name filter.
     *
     * @return int The mode.
     */
    public function tagMode(): int
    {
        return $this->m_tagMode;
    }

    /**
     * Fetch the mode of operation for the class attribute filter.
     *
     * @return int The mode.
     */
    public function classMode(): int
    {
        return $this->m_classMode;
    }

    /**
     * Fetch the mode of operation for the ID attribute filter.
     *
     * @return int The mode.
     */
    public function idMode(): int
    {
        return $this->m_idMode;
    }

    /**
     * Helper to add content to deny or allow lists.
     *
     * You must provide valid items - either a single non-empty string or an array full of non-empty strings. Assertions
     * guard this during testing/development. All items will be converted to lower case and duplicate items will not
     * end up in the target list.
     *
     * @param array $list Reference to the list to add to.
     * @param array|string $items The items to add.
     *
     * @throws InvalidArgumentException if $items is not a string or array, or if it contains a non-string or empty
     * string
     */
    protected final function addToList(array &$list, $items): void
    {
        if (is_string($items)) {
            $items = [$items];
        }

        if (!is_array($items)) {
            throw new InvalidArgumentException("items to add to allow-list/deny-list must be string or array of strings");
        }

        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException("non-string in allow-list/deny-list");
            }

            if (empty($item)) {
                throw new InvalidArgumentException("empty string in allow-list/deny-list");
            }
        }

        array_walk($items, function (string &$item) {
            $item = mb_convert_case($item, MB_CASE_LOWER, $this->m_userCharset);
        });

        $list = array_unique(array_merge($list, $items));
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
     * @param $tags array|string The tag(s) to add to the allow-list.
     *
     * @throws InvalidArgumentException if $tags is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function allowTags($tags): void
    {
        $this->addToList($this->m_tags->allowList, $tags);
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
    public function denyTags($tags): void
    {
        $this->addToList($this->m_tags->denyList, $tags);
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
    public function allowClasses($classes): void
    {
        $this->addToList($this->m_classes->allowList, $classes);
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
    public function denyClasses($classes): void
    {
        $this->addToList($this->m_classes->denyList, $classes);
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
    public function allowIds($ids): void
    {
        $this->addToList($this->m_ids->allowList, $ids);
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
     * @param $ids array|string The ID(s) to add to the deny-list.
     *
     * @throws InvalidArgumentException if $ids is not a string or array, or if it contains a non-string or empty
     * string
     */
    public function denyIds($ids): void
    {
        $this->addToList($this->m_ids->denyList, $ids);
    }

    /**
     * Fetch the list of allow-listed tag names.
     *
     * @return array The allow-list.
     */
    public function allowedTags(): array
    {
        return $this->m_tags->allowList;
    }

    /**
     * Fetch the list of deny-listed tag names.
     *
     * @return array The deny-list.
     */
    public function deniedTags(): array
    {
        return $this->m_tags->denyList;
    }

    /**
     * Fetch the list of allow-listed class names.
     *
     * @return array The allow-list.
     */
    public function allowedClasses(): array
    {
        return $this->m_classes->allowList;
    }

    /**
     * Fetch the list of deny-listed class names.
     *
     * @return array The deny-list.
     */
    public function deniedClasses(): array
    {
        return $this->m_classes->denyList;
    }

    /**
     * Fetch the list of allow-listed IDs.
     *
     * @return array The allow-list.
     */
    public function allowedIds(): array
    {
        return $this->m_ids->allowList;
    }

    /**
     * Fetch the list of deny-listed IDs.
     *
     * @return array The deny-list.
     */
    public function deniedIds(): array
    {
        return $this->m_ids->denyList;
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
        $tagName = mb_convert_case($tagName, MB_CASE_LOWER, $this->m_userCharset);

        if (in_array($tagName, self::FixedDenyList)) {
            return false;
        }

        if ($this->m_tagMode & self::DenyListMode && in_array($tagName, $this->m_tags->denyList)) {
            return false;
        }

        if ($this->m_tagMode & self::AllowListMode && !in_array($tagName, $this->m_tags->allowList)) {
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
        // all lists are in lower case
        $class   = mb_convert_case($class, MB_CASE_LOWER, $this->m_userCharset);
        $classes = preg_split("/[[:space:]]/", $class, 0, PREG_SPLIT_NO_EMPTY);

        if ($this->m_classMode & self::DenyListMode) {
            foreach ($classes as $class) {
                if (in_array($class, $this->m_classes->denyList)) {
                    return false;
                }
            }
        }

        if ($this->m_classMode & self::AllowListMode) {
            foreach ($classes as $class) {
                if (!in_array($class, $this->m_classes->allowList)) {
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
        $id = mb_convert_case($id, MB_CASE_LOWER, $this->m_userCharset);

        if ($this->m_idMode & self::DenyListMode && in_array($id, $this->m_ids->denyList)) {
            return false;
        }

        if ($this->m_tagMode & self::AllowListMode && !in_array($id, $this->m_ids->allowList)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether a DOM node will pass the cleaner.
     *
     * @param \DOMNode $node The node to check.
     *
     * @return bool `true` if the node is allowed, `false` if not.
     */
    public function isAllowedNode(DOMNode $node): bool
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
     * @throws Exception if the HTML cannot be parsed.
     *
     */
    public final function clean(string $html): string
    {
        $doc = new DOMDocument("1.0", $this->m_userCharset);

        if (!$doc->loadXml("<htmlcleaner_root>$html</htmlcleaner_root>")) {
            throw new Exception("failed to parse HTML");
        }

        $cleanNode = function (DOMNode $node) use (&$cleanNode): void {
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
