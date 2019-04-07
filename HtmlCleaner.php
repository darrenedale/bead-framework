<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 30/03/19
 * Time: 08:02
 *
 * @todo The DOM objects always work with UTF-8. We should therefore convert the content using the input charset before
 * providing it to the DOMDocument and convert it back when providing output.
 * @todo finish comprehensive unit test
 * @todo ability to config attributes to strip (while leaving the element in place)
 */

declare(strict_types=1);

namespace Equit;

use DOMDocument;
use DOMNode;
use Exception;
use InvalidArgumentException;

/**
 * Sanitise HTML according to configurable rules.
 *
 * HTML is sanitised according to three types of rules:
 * - white/black lists based on tag names;
 * - white/black lists based on classes;
 * - white/black lists based on ids.
 *
 * In all three cases, the whitelists and blacklists can be enabled or disabled independently, so you can use any
 * combination of white/black lists. Whitelists and blacklists can be used together. Blacklists, when enabled,`
 * *always* take precedence over whitelists - for example, if an ID is on the ID blacklist it's forbidden, regardless of
 * whether it's also on the whitelist.
 *
 * Set which lists are enabled for each rule type with setTagMode(), setClassMode() and setIdMode(). These have getter
 * corollaries in tagMode(), classMode() and idMode(). The content of the white and black lists for the tag name lists
 * is set using whiteListTags() and blackListTags(). Use whiteListClasses() and blackListClasses() for classes and
 * whiteListIds() and blackListIds() for IDs.
 *
 * @package Equit
 */
class HtmlCleaner {
	/**
	 * Flag to turn on whitelist mode checking for a feature.
	 */
	public const WhiteListMode = 0x01;

	/**
	 * Flag to turn on blacklist mode checking for a feature.
	 */
	public const BlackListMode = 0x02;

	/**
	 * Flag to turn on both whitelist and blacklist mode checking for a feature.
	 */
	public const CombinedMode = self::WhiteListMode | self::BlackListMode;

	public const CommonFormattingWhitelist = [
		"article", "section", "nav", "aside", "h1", "h2", "h3", "h4", "h5", "h6",
		"header", "footer", "address", "p", "pre", "blockquote", "ol", "ul", "li",
		"dl", "dt", "dd", "figure", "figcaption", "div", "main", "hr", "em", "strong",
		"cite", "q", "dfn", "abbr", "data", "time", "code", "var", "samp", "kbd",
		"mark", "ruby", "rb", "rt", "rp", "rtc", "bdi", "bdo", "span", "br", "wbr",
		"table", "tr", "td", "th", "caption", "tbody", "thead", "tfoot", "colgroup", "col",
	];
	
	/**
	 * @var array List of strings for tags that are *always* stripped regardless of operation mode or list content.
	 */
	private const FixedBlackList = [
		"script", "head",
	];

	/**
	 * Initialise a new HtmlCleaner object.
	 *
	 * The default mode of operation is for all filters to operate in both blacklist and whitelist modes. The whitelists
	 * and blacklists are all empty to begin with, which means only the fixed blacklist of tag names will be removed.
	 *
	 * @param int $tagMode The mode of operation for the tag name filter.
	 * @param int $classMode The mode of operation for the class attribute filter.
	 * @param int $idMode The mode of operation for the id attribute filter.
	 */
	public function __construct(int $tagMode = self::CombinedMode, int $classMode = self::CombinedMode, int $idMode = self::CombinedMode) {
		$this->m_tags = (object) [
			"whiteList" => [],
			"blackList" => [],
		];

		$this->m_classes = (object) [
			"whiteList" => [],
			"blackList" => [],
		];

		$this->m_ids = (object) [
			"whiteList" => [],
			"blackList" => [],
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
	public function setTagMode(int $mode): void {
		if(self::WhiteListMode != $mode && self::BlackListMode != $mode && self::CombinedMode != $mode) {
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
	public function setClassMode(int $mode): void {
		if(self::WhiteListMode != $mode && self::BlackListMode != $mode && self::CombinedMode != $mode) {
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
	public function setIdMode(int $mode): void {
		if(self::WhiteListMode != $mode && self::BlackListMode != $mode && self::CombinedMode != $mode) {
			throw new InvalidArgumentException("test mode for id attributes must be one of the class test mode constants");
		}

		$this->m_idMode = $mode;
	}

	/**
	 * Fetch the mode of operation for the tag name filter.
	 *
	 * @return int The mode.
	 */
	public function tagMode(): int {
		return $this->m_tagMode;
	}

	/**
	 * Fetch the mode of operation for the class attribute filter.
	 *
	 * @return int The mode.
	 */
	public function classMode(): int {
		return $this->m_classMode;
	}

	/**
	 * Fetch the mode of operation for the ID attribute filter.
	 *
	 * @return int The mode.
	 */
	public function idMode(): int {
		return $this->m_idMode;
	}

	/**
	 * Helper to add content to black or white lists.
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
	protected final function addToList(array &$list, $items): void {
		if(is_string($items)) {
			$items = [$items];
		}

		if(!is_array($items)) {
			throw new InvalidArgumentException("items to add to whitelist/blacklist must be string or array of strings");
		}

		foreach($items as $item) {
			if(!is_string($item)) {
				throw new InvalidArgumentException("non-string in whitelist/blacklist");
			}

			if(empty($item)) {
				throw new InvalidArgumentException("empty string in whitelist/blacklist");
			}
		}

		array_walk($items, function(string &$item) {
			$item = mb_convert_case($item, MB_CASE_LOWER, $this->m_userCharset);
		});

		$list = array_unique(array_merge($list, $items));
	}

	/**
	 * Add tags to the whitelist.
	 *
	 * If the whitelist is enabled, any element whose tag name is not on the whitelist will be stripped from the cleaned
	 * HTML.
	 *
	 * None of the tags to add may be an empty string. Providing an empty string or an array containing one or more
	 * empty strings will result in undefined behaviour. The tags will be converted to lower-case for the whitelist, and
	 * duplicates will not be added.
	 *
	 * @param $tags array|string The tag(s) to add to the whitelist.
	 *
	 * @throws InvalidArgumentException if $tags is not a string or array, or if it contains a non-string or empty
	 * string
	 */
	public function whiteListTags($tags): void {
		$this->addToList($this->m_tags->whiteList, $tags);
	}

	/**
	 * Add tags to the blacklist.
	 *
	 * If the blacklist is enabled, any element whose tag name is on the blacklist will be stripped from the cleaned
	 * HTML.
	 *
	 * None of the tags to add may be an empty string. Providing an empty string or an array containing one or more
	 * empty strings will result in undefined behaviour. The tags will be converted to lower-case for the blacklist, and
	 * duplicates will not be added.
	 *
	 * @param $tags array|string The tag(s) to add to the blacklist.
	 *
	 * @throws InvalidArgumentException if $tags is not a string or array, or if it contains a non-string or empty
	 * string
	 */
	public function blackListTags($tags): void {
		$this->addToList($this->m_tags->blackList, $tags);
	}

	/**
	 * Add classes to the whitelist.
	 *
	 * If the whitelist is enabled, any element whose class attribute contains a class that is not on the whitelist will
	 * be stripped from the cleaned HTML.
	 *
	 * None of the classes to add may be an empty string. Providing an empty string or an array containing one or more
	 * empty strings will result in undefined behaviour. The classes will be converted to lower-case for the whitelist,
	 * and duplicates will not be added.
	 *
	 * @param $classes array|string The classes(s) to add to the whitelist.
	 *
	 * @throws InvalidArgumentException if $classes is not a string or array, or if it contains a non-string or empty
	 * string
	 */
	public function whiteListClasses($classes): void {
		$this->addToList($this->m_classes->whiteList, $classes);
	}

	/**
	 * Add classes to the blacklist.
	 *
	 * If the blacklist is enabled, any element whose class attribute contains a class on the blacklist will be stripped
	 * from the cleaned HTML.
	 *
	 * None of the classes to add may be an empty string. Providing an empty string or an array containing one or more
	 * empty strings will result in undefined behaviour. The classes will be converted to lower-case for the blacklist,
	 * and duplicates will not be added.
	 *
	 * @param $classes array|string The classes(s) to add to the blacklist.
	 *
	 * @throws InvalidArgumentException if $classes is not a string or array, or if it contains a non-string or empty
	 * string
	 */
	public function blackListClasses($classes): void {
		$this->addToList($this->m_classes->blackList, $classes);
	}

	/**
	 * Add IDs to the whitelist.
	 *
	 * If the whitelist is enabled, any element whose `id` attribute value is not on the whitelist will be stripped from
	 * the cleaned HTML.
	 *
	 * None of the IDs to add may be an empty string. Providing an empty string or an array containing one or more
	 * empty strings will result in undefined behaviour. The IDs will be converted to lower-case for the whitelist, and
	 * duplicates will not be added.
	 *
	 * @param $ids array|string The ID(s) to add to the whitelist.
	 *
	 * @throws InvalidArgumentException if $ids is not a string or array, or if it contains a non-string or empty
	 * string
	 */
	public function whiteListIds($ids): void {
		$this->addToList($this->m_ids->whiteList, $ids);
	}

	/**
	 * Add IDs to the blacklist.
	 *
	 * If the blacklist is enabled, any element whose `id` attribute value is on the blacklist will be stripped from
	 * the cleaned HTML.
	 *
	 * None of the IDs to add may be an empty string. Providing an empty string or an array containing one or more
	 * empty strings will result in undefined behaviour. The IDs will be converted to lower-case for the blacklist, and
	 * duplicates will not be added.
	 *
	 * @param $ids array|string The ID(s) to add to the blacklist.
	 *
	 * @throws InvalidArgumentException if $ids is not a string or array, or if it contains a non-string or empty
	 * string
	 */
	public function blackListIds($ids): void {
		$this->addToList($this->m_ids->blackList, $ids);
	}

	/**
	 * Fetch the list of whitelisted tag names.
	 *
	 * @return array The whitelist.
	 */
	public function whiteListedTags(): array {
		return $this->m_tags->whiteList;
	}

	/**
	 * Fetch the list of blacklisted tag names.
	 *
	 * @return array The blacklist.
	 */
	public function blackListedTags(): array {
		return $this->m_tags->blackList;
	}

	/**
	 * Fetch the list of whitelisted class names.
	 *
	 * @return array The whitelist.
	 */
	public function whiteListedClasses(): array {
		return $this->m_classes->whiteList;
	}

	/**
	 * Fetch the list of blacklisted class names.
	 *
	 * @return array The blacklist.
	 */
	public function blackListedClasses(): array {
		return $this->m_classes->blackList;
	}

	/**
	 * Fetch the list of whitelisted IDs.
	 *
	 * @return array The whitelist.
	 */
	public function whiteListedIds(): array {
		return $this->m_ids->whiteList;
	}

	/**
	 * Fetch the list of blacklisted IDs.
	 *
	 * @return array The blacklist.
	 */
	public function blackListedIds(): array {
		return $this->m_ids->blackList;
	}

	/**
	 * Check whether a tag with a given name will pass the cleaner.
	 *
	 * The comparison is case-insensitive - tags on the blacklist (if enabled) or not on the whitelist (if enabled) will
	 * fail regardless of case.
	 *
	 * @param string $tagName
	 *
	 * @return bool `true` if the ID attribute value is allowed, `false` if not.
	 */
	public function isAllowedTag(string $tagName): bool {
		// all lists are in lower case
		$tagName = mb_convert_case($tagName, MB_CASE_LOWER, $this->m_userCharset);

		if(in_array($tagName, self::FixedBlackList)) {
			return false;
		}

		if($this->m_tagMode & self::BlackListMode && in_array($tagName, $this->m_tags->blackList)) {
			return false;
		}

		if($this->m_tagMode & self::WhiteListMode && !in_array($tagName, $this->m_tags->whiteList)) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether the value of a class attribute will pass the cleaner.
	 *
	 * The comparison is case-insensitive - classes named in the attribute that are on the blacklist (if enabled) or not
	 * on the whitelist (if enabled) will fail regardless of case.
	 *
	 * @param string $class
	 *
	 * @return bool `true` if the class attribute value is allowed, `false` if not.
	 */
	public function isAllowedClassAttribute(string $class): bool {
		// all lists are in lower case
		$class = mb_convert_case($class, MB_CASE_LOWER, $this->m_userCharset);
		$classes = preg_split("/[[:space:]]/", $class, 0, PREG_SPLIT_NO_EMPTY);
	
		if($this->m_classMode & self::BlackListMode) {
			foreach($classes as $class) {
				if(in_array($class, $this->m_classes->blackList)) {
					return false;
				}
			}
		}
	
		if($this->m_classMode & self::WhiteListMode) {
			foreach($classes as $class) {
				if(!in_array($class, $this->m_classes->whiteList)) {
					return false;
				}
			}
		}
	
		return true;
	}

	/**
	 * Check whether the value of an ID attribute will pass the cleaner.
	 *
	 * The comparison is case-insensitive - ids on the blacklist (if enabled) or not on the whitelist (if enabled) will
	 * fail regardless of case.
	 *
	 * @param string $id
	 *
	 * @return bool `true` if the ID attribute value is allowed, `false` if not.
	 */
	public function isAllowedId(string $id): bool {
		// all lists are in lower case
		$id = mb_convert_case($id, MB_CASE_LOWER, $this->m_userCharset);
	
		if($this->m_idMode & self::BlackListMode && in_array($id, $this->m_ids->blackList)) {
			return false;
		}
	
		if($this->m_tagMode & self::WhiteListMode && !in_array($id, $this->m_ids->whiteList)) {
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
	public function isAllowedNode(DOMNode $node): bool {
		if(XML_ELEMENT_NODE == $node->nodeType) {
			if(!$this->isAllowedTag($node->nodeName)) {
				return false;
			}
	
			if($node->hasAttributes()) {
				$attr = $node->attributes->getNamedItem("id");
	
				if(isset($attr) && !$this->isAllowedId($attr->nodeValue)) {
					return false;
				}
	
				$attr = $node->attributes->getNamedItem("class");
	
				if(isset($attr) && !$this->isAllowedClassAttribute($attr->nodeValue)) {
					return false;
				}
			}
		}
	
		return true;
	}

	/**
	 * Clean some HTML according to the rules set in the cleaner.
	 *
	 * @throws \Exception if the HTML cannot be parsed.
	 *
	 * @param string $html The HTML to clean.
	 *
	 * @return string The cleaned HTML.
	 */
	public final function clean(string $html): string {
		$doc = new DOMDocument("1.0", $this->m_userCharset);

		if(!$doc->loadXml("<htmlcleaner_root>$html</htmlcleaner_root>")) {
			throw new Exception("failed to parse HTML");
		}
		
		$cleanNode = function(DOMNode $node) use (&$cleanNode): void {
			if($node->hasChildNodes()) {
				// can't use foreach() because it will skip next node when a node is removed
				$idx = 0;

				while($idx < $node->childNodes->length) {
					$childNode = $node->childNodes[$idx];

					if(!$this->isAllowedNode($childNode)) {
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

		foreach($doc->firstChild->childNodes as $node) {
			$cleanHtml .= $doc->saveXML($node);
		}

		return $cleanHtml;
	}

	/**
	 * @var string The user's character set.
	 */
	private $m_userCharset = "UTF-8";

	/**
	 * @var int The mode of operation for tag name cleaning.
	 */
	private $m_tagMode = self::CombinedMode;

	/**
	 * @var int The mode of operation for class name cleaning.
	 */
	private $m_classMode = self::CombinedMode;

	/**
	 * @var int The mode of operation for ID cleaning.
	 */
	private $m_idMode = self::CombinedMode;

	/**
	 * @var \StdClass The tag black/white lists.
	 */
	private $m_tags = null;

	/**
	 * @var \StdClass The class name black/white lists.
	 */
	private $m_classes = null;

	/**
	 * @var \StdClass The ID black/white lists.
	 */
	private $m_ids = null;
}
