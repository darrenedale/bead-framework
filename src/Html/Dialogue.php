<?php

namespace Equit\Html;

/**
 * Represents a dialogue presented to the user on the page.
 *
 * Objects of this class can be used to generate dialogues for output on the page. Use of this class is encouraged to
 * enable a consistent look for all messages that need to be presented to the user.
 *
 * Dialogues are a specialisation of a page section. All have the class _dialogue_, and can have any other classes added
 * just like any other page element. The constructor, for convenience, accepts an optional class parameter that can be
 * used to set an additional class for the dialogue. This is commonly used to differentiate between message, warning and
 * error dialogues, but you can use any valid class name you wish.
 *
 * As a subclass of _PageSection_, it is possible to add child elements to Dialogue objects. Dialogues can have a flag
 * set that ensures when generated that child objects are not output. By default, dialogues will output any child
 * elements that are added to them after (usually below) the message.
 *
 * There is also a flag to enable the dialogue message to contain HTML content. If this flag is set, the message in the
 * dialogue is assumed to be pre-formatted, valid HTML content and is output verbatim rather than being escaped. The
 * default is for the dialogue to assume its message is plain text, which means that it will escape the message for HTML
 * when generating its output.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes. It will be
 * removed before the version 2.0 release.
 */
class Dialogue extends Division
{
	/** @var int Flag indicating that the client-provided message is already in HTML format. */
	const MessageIsHtml = 0x01;

	/** @var int Flag indicating that child elements should not be output. */
	const NoChildElements = 0x10;

	/** @var int The default flags. */
	const DefaultFlags = 0x00;

	/** @var int A mask to filter for flags that concern the format of the client-provided message. */
	const MessageFormatFlagsMask = 0x01;

	/** @var string|null The dialogue's message. */
	private $m_message = null;

	/** @var int The dialogue's flags. */
	private $m_flags = self::DefaultFlags;

	/**
	 * Create a new dialogue.
	 *
	 * The message is assumed to be plain text. To change this, call the setFlags() method after the dialogue has been
	 * created. By default, the class _message_ is used.
	 *
	 * The message can be set to _null_ to unset the existing message. In such cases, the message flags are still
	 * altered according to the provided flags.
	 *
	 * @param $message string|null _optional_ The message to display in the dialogue.
	 * @param $class string _optional_ An optional class for the dialogue.
	 */
	public function __construct(?string $message = null, string $class = "message") {
		parent::__construct();
		$this->setMessage($message);
		$this->addClassName("dialogue");
		$this->addClassName($class);
	}

	/**
	 * Set the dialogue's message.
	 *
	 * By default, the message is assumed to be plain text. Omitting the flags parameter will reset the flags to the
	 * _DefaultFlags_ rather than retaining the current flags.
	 *
	 * ### Note
	 * This method only alters flags that relate to the type of message, not to child element handling or anything else.
	 *
	 * The message can be set to _null_ to unset the existing message. In such cases, the message flags are still
	 * altered according to the provided flags.
	 *
	 * @param $message string|null The message.
	 * @param $flags int The message flags.
	 */
	public function setMessage(?string $message, int $flags = self::DefaultFlags): void {
		/* filter out any flags that are not about the message format */
		$flags = $flags & self::MessageFormatFlagsMask;

		$this->m_message = $message;

		/* only change the message flags that are about the message format */
		$this->m_flags &= ~self::MessageFormatFlagsMask;
		$this->m_flags |= $flags;
	}

	/**
	 * Fetch the dialogue's message.
	 *
	 * @return string The message, or _null_ if none is set.
	 */
	public function message(): string {
		return $this->m_message;
	}

	/**
	 * Set the flags.
	 *
	 * Any and all flags can be set.
	 *
	 * @param $flags int The flags to set.
	 */
	public function setFlags(int $flags): void {
		$this->m_flags = $flags;
	}

	/**
	 * Fetch the dialogue's current flags.
	 *
	 * This method can be useful when you want to add flags to the dialogue without necessarily altering other flags.
	 *
	 * @return int The dialogue's flags.
	 */
	public function flags(): int {
		return $this->m_flags;
	}

	/**
	 * Clear the dialogue.
	 *
	 * The message is set to _null_ and the flags are reset to default.
	 */
	public function clearChildElements(): void {
		parent::clearChildElements();
		$this->m_message = null;
		$this->m_flags   = self::DefaultFlags;
	}

	/**
	 * Generate the HTML for the dialogue.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		$ret = $this->emitDivisionStart();

		if($this->m_flags & self::MessageIsHtml) {
			$ret .= $this->m_message;
		}
		else {
			$ret .= html($this->m_message);
		}

		if(!($this->m_flags & self::NoChildElements)) {
			$ret .= $this->emitChildElements();
		}

		$ret .= $this->emitDivisionEnd();
		return $ret;
	}
}
