<?php

/**
 * Defines the IndexView class.
 *
 * @todo finish docs
 *
 * ### Dependencies
 * - Equit\Request
 *
 * ### Changes
 * - (2019-03) First version of this file.
 *
 * @file IndexView.php
 * @author Darren Edale
 * @version 0.9.2
 * @package bead-framework
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\Request;

/**
 * A view showing an index of items.
 *
 * The index shows all of a set of items, based either on a range from a start to an end point, or on a fixed array of
 * index points. Each item in the index links to a page based on a provided request.
 *
 * @package Equit\Html
 */
abstract class IndexView extends Element {
	/**
	 * Initialise a new alphabetical index.
	 *
	 * The object will fill in the items between the start and end when the index is output.
	 *
	 * @param \Equit\Request|null $req
	 * @param string $parameterName
	 */
	protected function __construct(?Request $req = null, string $parameterName = "item") {
		parent::__construct();
		$this->setRequest($req);
		$this->setParameterName($parameterName);
	}

	/**
	 * Fetch the request that clicking on entries in the index uses.
	 *
	 * @return Request|null The request in use, or `null` if no request has been set.
	 */
	public function request(): ?Request {
		return $this->m_req;
	}

	/**
	 * Set the request that clicking on entries in the index uses.
	 *
	 * @param Request|null $req The request to use.
	 */
	public function setRequest(?Request $req): void {
		$this->m_req = $req;
	}

	/**
	 * Fetch the name of the parameter used in the request when the user clicks an item.
	 *
	 * @return string The request parameter name.
	 */
	public function parameterName(): string {
		return $this->m_paramName;
	}

	/**
	 * Set the name of the parameter used in the request when the user clicks an item.
	 *
	 * @param $paramName string The request parameter name.
	 */
	public function setParameterName(string $paramName): void {
		assert(!empty($paramName), "cannot use an empty parameter name");
		$this->m_paramName = $paramName;
	}

	protected final function setRangeStart(string $start): void {
		$this->m_start = $start;
	}

	protected final function setRangeEnd(string $end): void {
		$this->m_end = $end;
	}

	public final function rangeStart(): string {
		return $this->m_start;
	}

	public final function rangeEnd(): string {
		return $this->m_end;
	}

	public final function range() {
		return $this->m_range;
	}

	/**
	 * Set a custom range of items to use in the index.
	 *
	 * The provided range will use the array indices as the URL parameter values (if a request is set) and the array
	 * values as the display items. Provide `null` to unset the custom range and use the start and end items instead.
	 *
	 * **Warning** You must ensure that either the range or the start and end items is set before html() is called.
	 *
	 * @param array|null $range The range of items to use.
	 */
	protected final function setRange(?array $range): void {
		$this->m_range = $range;
	}

	/**
	 * Generate the HTML for the index.
	 *
	 * @return string The HTML for the index.
	 */
	public function html(): string {
		$paramName = $this->parameterName();
		$indexHtml = "<ul {$this->emitAttributes()}>";
		$req = $this->request();
		$range = $this->range();

		if(empty($range)) {
			foreach(range($this->rangeStart(), $this->rangeEnd()) as $item) {
				$range[$item] = $item;
			}
		}

		if($req) {
			$req = clone $req;

			foreach($range as $paramValue => $label) {
				$req->setUrlParameter($paramName, $paramValue);
				$indexHtml .= "<li><a href=\"{$req->url()}\">" . html($label) . "</a></li>";
			}
		}
		else {
			foreach($range as $label) {
				$indexHtml .= "<li>" . html($label) . "</li>";
			}
		}

		$indexHtml .= "</ul>";
		return $indexHtml;
	}

	/**
	 * @var string|null The first item in the index.
	 */
	private $m_start = null;

	/**
	 * @var string|null The last item in the index.
	 */
	private $m_end = null;

	/**
	 * @var array|null The custom range of items to use for the index.
	 */
	private $m_range = null;

	/** @var Request The request submitted on clicking an alpha entry. */
	private $m_req = null;

	/** @var string The parameter to set in the request. */
	private $m_paramName = "";
}
