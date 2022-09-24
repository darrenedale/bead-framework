<?php

namespace Equit\Html;

/**
 * Allows arbitrary HTML to be added to the page.
 *
 * This class is just a thin LibEquit\PageElement wrapper around a string containing pre-validated HTML.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes. It will be
 * removed before the version 2.0 release.
 */
class HtmlLiteral extends Element {
	/** @var string The HTML. */
	private $m_html = "";

	/**
	 * Create a new LibEquit\HtmlLiteral.
	 *
	 * By default, an empty HTML literal object is created.
	 *
	 * @param $html string _optional_ The HTML to wrap in the object.
	 */
	public function __construct(string $html = "") {
		parent::__construct();
		$this->setHtml($html);
	}

	/**
	 * Set the HTML to wrap in the LibEquit\PageElement.
	 *
	 * The HTML is used verbatim with no modifications and is not validated. It must therefore be provided as valid
	 * HTML. To unset the existing HTML, provide an empty string.
	 *
	 * @param $html string The HTML to use.
	 *
	 * @return void.
	 */
	public function setHtml(string $html): void {
		$this->m_html = $html;
	}

	/**
	 * Fetch the HTML.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return $this->m_html;
	}
}
