<?php

/**
 * Defines the DataValidationReportDialogue class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/Dialogue.php
 * - classes/equit/DataValidationReport.php
 *
 * ### Changes
 * - (2017-05) Updated documentation.
 * - (2014-03-04) First version of this file.
 *
 * @file DataValidationReportDialogue.php
 * @author Darren Edale
 * @version 0.9.1 * @version 0.9.1
 * @package libequit
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\DataValidationReport;

/**
 * A dialogue to show the content of a DAO validation report.
 *
 * This class represents a specialised dialogue to show the content of a DAO's validation report. It will show an
 * optional title, a list of errors (if any) and optionally a list of warnings (if any). If there are no errors a custom
 * _no errors_ message will be shown instead. If this is empty and there are no warnings (or warnings are not being
 * displayed), the dialogue will not be displayed as it will have no content.
 *
 * The report that will be used as the basis of the dialogue's content is set using _setReport()_ and fetched using
 * _report()_. Setting the report does not immediately update the dialogue's message - for performance reasons, this is
 * not created until the dialogue is output. The content displayed in the dialogue is formatted HTML following this
 * template:
 *
 * ~~~{.html}
 * <h2>{title}</h2>
 * <p>The following errors were found:</p>
 * <ul>
 * <li>Error 1</li>
 * <li>Error 2</li>
 * ...
 * </ul>
 * <p>The following warnings were also found:</p>
 * <ul>
 * <li>Warning 1</li>
 * <li>Warning 2</li>
 * ...
 * </ul>
 * ~~~
 *
 * If the dialogue is not set to show warnings, the warnings section of the dialogue is omitted. If the report does not
 * contain any errors but does contain warnings and warnings are set to be shown, the introductory text for the warnings
 * section is changed to "The following warnings were found:". The introductions to the errors and warnings sections are
 * translated according to the language currently set in the application. The errors and warnings themselves are not run
 * through the translator because these strings are owned by the DAO that created the report and it is that class's
 * responsibility to ensure they are correctly translated.
 *
 * If the dialogue has no title set, the title _h2_ element is omitted.
 *
 * The dialogue's _no-errors_ message can be set with _setNoErrorsMessage()_ and retrieved with _noErrorsMessage()_. The
 * message must be plain text, or _null_ or empty if no message is desired.
 *
 * @note
 * The dialogue's message and flags are only set when _html()_ is called, so calling _message()_ or _flags()_ between
 * setting the report and calling _html()_ will not return the expected content, and calling _setMessage()_ or
 * _setFlags()_ will have no effect on the output of the dialogue.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide an API.
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
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 *
 * @class DataValidationReportDialogue
 * @author Darren Edale
 * @package libequit
 */
class DataValidationReportDialogue extends Dialogue {
	/** @var \Equit\DataValidationReport|null The report to display in the dialogue. */
	private $m_report = null;

	/** @var string The dialogue title. */
	private $m_title = "";

	/** @var string The message to display when there are no errors. */
	private $m_noErrorsMessage = "";

	/** @var bool Whether or not to display warnings as well as errors in the dialogue. */
	private $m_doWarnings = false;

	/**
	 * Create a new DataValidationReportDialogue.
	 *
	 * If either _$noErrorsMessage_ or _$title_ is not provided (or is empty), they are considered not to apply when the
	 * dialogue is output and their respective HTML will be absent. If a report contains no errors and no _no-errors_
	 * message is set, the dialogue will be completely suppressed and no HTML will be output at all (not even an empty
	 * container).
	 *
	 * @param $report DataValidationReport|null The report to display in the dialogue.
	 * @param $noErrorsMessage string _optional_ A message to display if there are no errors in the report.
	 * @param $title string _optional_ A title for the dialogue.
	 */
	public function __construct(DataValidationReport $report, string $noErrorsMessage = "", string $title = "") {
		parent::__construct("");
		$this->setReport($report);
		$this->setNoErrorsMessage($noErrorsMessage);
		$this->setTitle($title);
	}

	/**
	 * Set the report that the dialogue will display.
	 *
	 * The report can be _null_ to unset the existing report.
	 *
	 * @param $report DataValidationReport|null The report to display.
	 */
	public function setReport(?DataValidationReport $report): void {
		$this->m_report = $report;
	}

	/**
	 * Fetch the report that the dialogue will display.
	 *
	 * @return DataValidationReport|null The report to display, or _null_ if no report is set.
	 */
	public function report(): ?DataValidationReport {
		return $this->m_report;
	}

	/**
	 * Set the title for the dialogue.
	 *
	 * While this is called a title, it is more like an introductory text for the list of errors/warnings contained in
	 * the report.
	 *
	 * @param $title string The dialogue title.
	 */
	public function setTitle(string $title): void {
		$this->m_title = $title;
	}

	/**
	 * Fetch the dialogue title.
	 *
	 * @return string The dialogue title. This will be an empty string if no title is set.
	 */
	public function title(): string {
		return $this->m_title;
	}

	/**
	 * Set the message to show when there are no errors in the report.
	 *
	 * The message must be plain text, and must be pre-translated into the appropriate language. If it is an empty
	 * string, no message will be displayed if the report contains no errors.
	 *
	 * @param $msg string The message to show.
	 */
	public function setNoErrorsMessage(string $msg): void {
		$this->m_noErrorsMessage = $msg;
	}

	/**
	 * Fetch the _no-errors_ message.
	 *
	 * @return string The _no-errors_ message.
	 */
	public function noErrorsMessage(): string {
		return $this->m_noErrorsMessage;
	}

	/**
	 * Fetch the HTML for the dialogue.
	 *
	 * The HTML will be empty if there are no errors or warnings for the dialogue to display and there is no _no-errors_
	 * message either.
	 *
	 * @see
	 * The DataValidationReportDialogue class documentation has details of the HTML template used for the dialogue
	 * content.
	 *
	 * @return string The dialogue HTML.
	 */
	public function html(): string {
		if(empty($this->m_report)) {
			AppLog::warning("no validation report - creating empty output", __FILE__, __LINE__, __FUNCTION__);
			return "";
		}

		$errors  = $this->m_report->errors();
		$content = "";
		$class   = "message";

		if(0 < count($errors)) {
			$class   = "error";
			$content .= "<p>" . html(tr("The following errors were found:")) . "</p><ul>";

			foreach($errors as $error) {
				if(!empty($error)) {
					$content .= "<li>" . html($error) . "</li>";
				}
			}

			$content .= "</ul>";
		}

		if($this->m_doWarnings) {
			$warnings = $this->m_report->warnings();

			if(0 < count($warnings)) {
				$content .= "<p>";

				if(0 < count($errors)) {
					$content .= html(tr("The following warnings were also found:"));
				}
				else {
					$content .= html(tr("The following warnings were found:"));
					$class   = "warning";
				}

				$content .= "</p><ul>";

				foreach($warnings as $warning) {
					if(!empty($warning)) {
						$content .= "<li>" . html($warning) . "</li>";
					}
				}

				$content .= "</ul>";
			}
		}

		if(empty($content) && !empty($this->m_noErrorsMessage)) {
			$content = "<p>" . html($this->m_noErrorsMessage) . "</p>";
		}

		if(!empty($content) && !empty($this->m_title)) {
			$content = "<h2>" . html($this->m_title) . "</h2>$content";
		}

		if(!empty($content)) {
			if("message" != $class) {
				$this->removeClassName("message");
				$this->addClassName($class);
			}

			$this->setMessage($content, self::MessageIsHtml | self::NoChildElements);
			return parent::html();
		}

		return "";
	}
}
