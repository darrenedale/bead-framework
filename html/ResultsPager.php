<?php

/**
 * Defines the ResultsPager class.
 *
 * ### Dependencies
 * - classes/equit/Application.php
 * - classes/equit/PageElement.php
 * - classes/equit/AppLog.php
 * - classes/equit/Page.php
 *
 * ### Todo
 * - flag to enable repetition of column titles when a new level-0 subheading is generated?
 *
 * ### Changes
 * - (2018-01) Methods to remove rows removed - they had not been updated to the new chunk-based caching scheme, were
 *   not used anywhere in the app, and don't fit the purpose of the pager, which is to display and navigate results.
 * - (2018-01) First results chunk cache file is always created even if the results are empty.
 * - (2017-05) Updated documentation.
 * - (2017-04) removed uses of func_get_args() in favour of PHP 5.6+ variadic ... args.
 * - (2015-11-02) Number of cache files per pager reduced - more stored in metadata cache file rather than individual
 *   files.
 * - (2015-11-02) Results are now chunked into separate files of 5000 rows each so that very large datasets can be
 *   handled more efficiently. Pagers will now never have more than 5000 rows of data in memory at a given time.
 * - (2015-05-21) Support for sub-heading grouping based on column values. All internal output formats honour groupings.
 * - (2013-12-10) First version of this file.
 *
 * @file ResultsPager.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\PageElement;
use Equit\Request;
use PDO;
use PDOStatement;

require_once("includes/string.php");

/**
 * Display or output a set of results as a table.
 *
 * ## Introduction
 * Given a set of results from a database query, an object of this class will output them in various tabulated formats.
 * This class supports the following formats, but other formats can be added by creating subclasses:
 * - HTML
 * - CSV
 *
 * The primary purpose of this class is to provide a generic framework for comfortably paging query results on web
 * pages. In their default mode of operation, objects of this class will logically chunk the results into sets of 50
 * rows and display one chunk at a time, along with an index to the pages that allows the user to move forward and
 * backward through the full set of results.
 *
 * Objects are created by providing the constructor with a _PDOStatement_ object from which to initialise the data it
 * pages. Once constructed, the results are cached so the result set can be used regardless of whether the original
 * _PDOStatement_ continues to exist (see \ref DataCaching "Data Caching" below). This means that ResultsPager objects
 * can persist beyond both the lifetime of the original database query and the lifetime of the current request.
 *
 * The class provides features for fine-tuning the output, including:
 * - display callbacks to format column data appropriately for the output MIME type
 * - check boxes to enable users to indicate rows they are interested in
 * - row grouping under subheadings according to changes in the values in one or more columns
 * - flags to control various features of the object during output
 *
 * ## Data caching
 * This class caches the raw data to disk. It also caches the list of selected row indices. The data is cached to disk
 * when it is set (in setResults()). This ensures that the pager's data can survive subsequent HTTP requests intact
 * (i.e. when the original _PDOStatement_ from which the data was retrieved is no longer valid). When the pager is
 * re-created (by calling fetchCachedPager() with the appropriate ID), the object just has its ID set; the actual data
 * is not fetched until it is required for output to conserve memory. The data only survives in memory while it is being
 * output, after which it is discarded and future renderings re-read the disk cache. This is done to conserve memory and
 * because the typical use-case is to output the data just once during any request.
 *
 * @note When a ResultsPager is created by fetching cached data, the results() method will return _null_ since the
 * original _PDOStatement_ object has long since departed.
 *
 * The cache of result data is separated into chunks of 5000 rows (maximum); each chunk is stored in a separate file.
 * This ensures that, for very large data sets, only at most 5000 rows' worth of data are stored in memory at any given
 * time. This helps objects of this class reduce their memory footprint by only ever having the chunk currently required
 * in memory. This means that pagers are much less likely to breach `memory_limit` and therefore much less likely to
 * cause the running PHP program to fail.
 *
 * The cache of selected row indices behaves slightly differently and is simpler. The set of rows is cached upon
 * creation (i.e. an empty set), and reloaded and stored with the object immediately upon re-creation (using
 * _fetchCachedPager()_). Every time the set of indices is altered (using _setRowChecked()_), it is cached again to
 * disk. Which is to say, the disk cache and the in-memory list are always synchronised.
 *
 * The class also offers some features which different output types may or may not support. Of the internally-provided
 * output types, only HTML output currently supports these features.
 *
 * ## Cache purging
 * The cache can be purged internally according to an interval and timeout set using class constants
 * (_ResultsCachePurgeInterval_ and _ResultsCacheExpiryTimeout_). Internal purging is switched on by default, but can be
 * turned of by setting the configuration directive `class.resultspager.cache.purge.enabled` to _false_ in your
 * application's configuration file. When purging is on, an attempt to purge the cache will be made every
 * _ResultsCachePurgeInterval_ seconds, and the purge will remove any files in the cache directory that have not been
 * accessed for _ResultsCacheExpiryTimeout_ seconds.
 *
 * @note
 * In future, it may be possible to set the interval and timeout in the configuration file, with the class using its
 * built-in defaults in cases where no interval and/or timeout is set in the configuration file.
 *
 * Access time testing is based on the _atime_ of the files. This will work even on filesystems mounted with the
 * _noatime_ option set because all ResultsPager cache files have their _atime_ set manually by the class for this
 * purpose. All genuine cache files therefore will have the correct _atime_. (On filesystems mounted _noatime_ the
 * _atime_ attribute is not automatically upated on file access as a way of reducing file access overhead. In such
 * cases, files usually report their _ctime_ as their _atime_. Manually setting the _atime_ ensures that cache files
 * don't expire on the basis of their creation, but on the basis of how recently they have been used.)
 *
 * \warning
 * The ResultsPager class assumes it owns all files in the cache directory and will delete anything it finds there that
 * it considers out of date, whether or not it is a genuine cache file.
 *
 * If you turn off internal purging of cache files, you are strongly advised to set up a cron job to do the task for
 * you, as cache files will quickly eat up your disk space. A sample command for a cron job to remove cache files not
 * accessed for at least 30 minutes is:
 *
 *     find /tmp/{app-uid}-pager-cache/ -amin +30 -name "*resultspager*" -delete
 *
 * ## Row checkboxes
 * Rows from the results can be output with checkboxes next to each row. This provides users with a facility to indicate
 * those rows that are of interest. The ResultsPager object keeps track of which rows the user has ticked in its cache
 * of the results it is displaying, which means the user's selections persist through subsequent HTTP requests and views
 * of different pages in the set of results. Some glue needs to be provided by a plugin to link the user's tick of a box
 * on the page to a call to the setRowChecked() method on the ResultsPager object. This should usually be an API call to
 * the application that is triggered when the user (un)ticks a checkbox, which retrieves the cached results, calls
 * setRowChecked() and reports back to the browser whether or not the attempt to (un)tick the row succeeded. (For an
 * example, see the @link ResultsPagerCheckboxes plugin in the AIO application.)
 *
 * ## Row grouping
 * There is also a facility to group rows according to the content of one or more columns. Grouping involves generating
 * a subheading for the results during output whenever the output code encounters a change in the value of one or more
 * columns.
 *
 * For example, in a list of payments that contains the name of the payee and the amount of the payment, the list could
 * be grouped by the name of the payee. In this case, whenever a row's payee is not the same as the previous row's
 * payee, a subheading containing the name of the payee will be output. This has the effect of grouping together the
 * payments for each payee under a subheading, and is often an efficient and space- saving way to present results where
 * multiple rows are related by a common value in one or more columns.
 *
 * So the results:
 * | payee  | amount |
 * | ------ | -----: |
 * | Darren |  20.00 |
 * | Darren |   0.50 |
 * | Darren |  10.00 |
 * | Darren |  11.00 |
 * | Darren |  30.00 |
 * | Susan  |  15.00 |
 * | Susan  |   4.00 |
 * | Susan  |  29.00 |
 * | Susan  |  19.00 |
 * | Susan  |   5.00 |
 *
 * Would be output as:
 * | Darren |
 * | -----: |
 * | 30.00  |
 * | 20.00  |
 * |  0.50  |
 * | 10.00  |
 * | 11.00  |
 *
 * | Susan  |
 * | -----: |
 * | 15.00  |
 * |  4.00  |
 * | 29.00  |
 * | 19.00  |
 * |  5.00  |
 *
 * This works best when the columns used for grouping were also used, in the same order, to sort the result set. It is
 * possible to use as many columns as you wish to group rows; however, in practice using more than two or three columns
 * in this way is likely to be confusing and often just one grouping column is best.
 *
 * When grouping is in use, the default behaviour of the ResultsPager object is to hide the columns used for grouping
 * from the data, hence in the above example the payment amounts are displayed without the payee in each row (because
 * the payee is clear from the subheading). This can be controlled at output time with the use of the _$flags_
 * parameter.
 *
 * ## Display callbacks
 * Any column can have a display callback set. During output, whenever a value for a column with a display callback is
 * output, the callback is first called with the value for the column, and the value returned by the callback is what is
 * actually output. This enables very tight control over the content that is actually output without having to
 * manipulate the source data directly. One common use-case for this is to turn values into hyperlinks for use in a web
 * page.
 *
 * Callbacks are given the value to format, the name of the column from which it came, the _MIME type_ being used for
 * output, and the data for the row to which it belongs (including any hidden columns). Using this information, the
 * callback has great flexibility in formatting the value: the callback can tune its output to the MIME type (for
 * example, only generating a hyperlink when the MIME type is text/html) and it can use the full context of the value's
 * record (i.e. the row data) to inform its formatting (for example, colouring the value in a name column red when the
 * value in an amount column is negative, green when it's positive).
 *
 * The signature of a callback function is as follows:
 *
 *     string callback( string $value, string $column, [string] $rowData, string $mimeType );
 *
 * The `$value` parameter is the value for the column that the callback is being asked to process. It is the raw value
 * as provided by the result set. The `$column` parameter is the name of the column from which the value was taken. This
 * is the name of the column as provided by the result set, and is unaffected by any title set with setColumnTitle().
 * The `$rowData` parameter is the full set of data for the row to which the value belongs. The array contains all data,
 * including values for columns set as hidden with hideColumn(), all data is the raw data as provided by the result set
 * (i.e. before any callbacks have processed it), and the array is indexed by the column names as provided by the result
 * set (i.e. the array indices are not affected by any column titles set with setColumnTitle()). The `$mimeType`
 * parameter is the MIME type being used for output. If the call to retrieve the output didn't specify a MIME type (e.g.
 * data() was called without a MIME type), the MIME type given to the callback will be the default MIME type for that
 * method (that is, the `$mimeType` parameter to a callback will never be _null_.
 *
 * All callbacks will be provided with all parameters; however, callbacks are not obliged to specify or use any of them,
 * so a callback can have this signature:
 *
 *     string callback();
 *
 * For versions of PHP that support them, closures can be provided as callbacks as long as they satisfy all the rules
 * for a callback.
 *
 * Callbacks **must** return a string or at least a value or object that can be implicitly converted to a string. The
 * returned value will be used verbatim in the output, so if the MIME type requires any specific escaping to be done for
 * special characters (e.g. HTML), the callback must ensure that this is done. If a callback does not provide a return
 * value, or returns _null_, empty output (or possibly corrupt, depending on the requirements of the MIME type) will be
 * the result.
 *
 * Multiple callbacks can be set for any given column simply by calling addColumnCallback() more than once with the same
 * column name. This even extends to the possibility of adding the same callback for the column more than once. When
 * multiple callbacks are in use for a single column, the callbacks are always called in the order in which they were
 * added. With the exception of the first callback called for each value, all callbacks for a value in a single column
 * will receive the _value_ parameter with the content returned by the previous callback for that value. This can
 * complicate issues around escaping and interpretation of values when using multiple callbacks with a single column. In
 * order to work effectively, callbacks may need to know whether the value they receive has been processed by previous
 * callbacks and what they have done to it, information which the ResultsPager object cannot provide. Primarily for this
 * reason, use of multiple callbacks for single columns is not recommended. In cases where you need several different
 * callbacks to work with single column values, the recommended approach is either to combine the functionality of all
 * the required callbacks into a single callback (in cases where each callback is relatively simple) or that you create
 * a "meta-callback" whose job is to call all the required actual callbacks and which can know what each does to the
 * provided value.
 *
 * ## Output flags
 * When an object of this class is asked to generate its output, flags are available to fine-tune the output. This
 * enables the same ResultsPager object, configured in the same way, to be used for multiple different output contexts
 * (such as display in a browser vs. download for offline viewing or printing). The internal output formats (HTML and
 * CSV) support all flags; this cannot be guaranteed for formats provided by subclasses - check the documentation for
 * those subclasses for details.
 *
 * The following flags are available in this class:
 * - _CheckedRowsOnly_
 *   Only output rows that the user has ticked using the checkboxes.
 *
 * - _NoColumnTitles_
 *   Don't generate any column titles.
 *
 * - _NoCheckedRowsEqualsAllRows_
 *   If only checked rows are being output and no rows have been checked, act as if all rows have been checked.
 *
 * - _DontHideColumns_
 *   Output all columns regardless of whether any have been marked as hidden.
 *
 * - _DontGroup_
 *   Don't do any grouping based on group columns. Basically, this means that if setGroupColumns() has been called it
 *   will be ignored and the results will be output as a single table.
 *
 * - _DontHideGroupColumns_
 *   If column grouping is being used (subheadings to group related rows together) the values of the columns used for
 *   grouping will still be included in the output. (Usually, the columns used for grouping are hidden in the output
 *   because of their use as a subheading.)
 *
 * Note that when the full result set is output (e.g. for download), very large result sets are likely to cause the PHP
 * script to fail (by breaching *memory_limit*) because the content of the output is always generated in memory before
 * being sent to the client. In future this is likely to change so that, for result sets of a certain size or larger,
 * the generated content is successively written to a temporary file on disk, which is then sent to the client, This
 * should resolve most of the remaining issues with this class and large data sets.
 *
 * ## Type-specific output options
 * Options to tailor output in a specific MIME type can be given in one of two ways. Either you can set the options on
 * the pager object or you can provide the options directly to the data() method or the method specific to the output
 * type you are using if you are calling it directly. Setting the options on the object before output has the following
 * advantages:
 *
 * - The options persist for all subsequent calls to output methods (e.g. data())
 * - The options can be specified in cases where you don't have control over when the output method (e.g. _data()_) is
 *   called, for example when adding a _ResultsPager_ object to a page layout where the page is ultimately responsible
 *   for when _data()_ is called.
 *
 * Passing the options directly to the _data()_ or other output method has the following advantages:
 *
 * - They can be used to customise the generated content for specific output scenarios regardless of the options set on
 *   the object (for example in cases where the code calling the output method (e.g. _data()_) does not "own" the
 *   _ResultsPager_ object and therefore should not alter its state.
 *
 * The options that are available for any given MIME type are dependent on where the output method for that MIME type is
 * implemented. The built-in *HTML* output method provides the following options:
 *
 * - **row_id_template** _string_
 *   A template to use to generate an ID for each row in the generated table. The template can contain {}-enclosed
 *   placeholders to insert data from the row being output into the generated ID.
 *
 * The built-in CSV output method provides the following options:
 *
 * - **charset** _string_
 *   The character set to use in the generated CSV data. It can be any character set supported by *iconv*.
 *
 * Subclasses that reimplement the HTML or CSV output methods are free to make their own options available, and are free
 * to ignore the options provided by the built-in implementations if they want to. Subclasses that implement their own
 * custom output MIME types are also free to invent their own sets of supported options.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This plugin provides the following API functions:
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
 * @class ResultsPager
 * @author Darren Edale
 * @package libequit
 */
class ResultsPager extends PageElement {
	/** @var int The default number of rows per page. */
	public const DefaultPageSize = 50;

	/** @var int The default page to show when no specific page number is given. */
	public const DefaultPageNumber = 1;

	/** @var int Seconds between purges of expired cache entries. */
	protected const ResultsCachePurgeInterval = 1800;    /* 30 mins */
// 	protected const ResultsCachePurgeInterval = 300;	/* 5 mins, for testing */

	/**
	 * @var int The age that constitutes an expired cache file.
	 *
	 * During a purge, any file in the cache that was last accessed more than
	 * this number of seconds ago is considered to have expired.
	 */
	protected const ResultsCacheExpiryTimeout = 1800;    /* 30 mins */
// 	protected const ResultsCacheExpiryTimeout = 60;	/* 1 min, for testing */

	/**
	 * @var int Only output checked rows.
	 *
	 * @output-flag
	 */
	public const CheckedRowsOnly = 0x01;

	/**
	 * @var int Don't show column titles
	 *
	 * @output-flag
	 */
	public const NoColumnTitles = 0x02;

	/** @var int Output flag: if no rows are checked, act as if all rows are checked.
	 *
	 * @output-flag
	 */
	public const NoCheckedRowsEqualsAllRows = 0x04;

	/**
	 * @var int Don't honour hidden columns, just output all columns.
	 *
	 * @output-flag
	 */
	public const DontHideColumns = 0x08;

	/**
	 * @var int Don't output subheadings based on column value grouping.
	 *
	 * @output-flag
	 */
	public const DontGroup = 0x10;

	/**
	 * @var int If there is any column-value-based grouping, don't hide the grouping columns.
	 *
	 * In the output, usually the grouped columns are hidden because the values are
	 * used in subheadings so generating the column data as well is needlessly
	 * repeating data. Using this flag that behaviour can be overriden and grouping
	 * column data can be shown.
	 *
	 * @output-flag
	 */
	public const DontHideGroupColumns = 0x20;

	/**
	 * @var int Default flags
	 *
	 * @output-flag
	 */
	public const DefaultOutputFlags = 0x00;

	/** @var int How many rows of results data to place in each results cache chunk file. */
	protected const ResultsCacheFileChunkSize = 5000;

	/** @var array The built-in supported output MIME types. */
	private static $s_supportedMimeTypes = [
		"text/html" => "html",
		"text/csv" => "csv",
	];

	/** @var PDOStatement|null The results being paged. */
	private $m_results = null;

	/** @var string The UID for the results being paged. */
	private $m_resultsId = "";

	/** @var string|null The name of the results being paged. */
	private $m_name = null;

	/** @var int The number of rows in results being paged. */
	private $m_rowCount = 0;

	/** @var int The number of rows per page. */
	private $m_pageSize = self::DefaultPageSize;

	/** @var int The number of the page to display. */
	private $m_pageNumber = 1;

	/** @var array The columns to group by. */
	private $m_groupColumns = [];

	/** @var array The callbacks for column data. */
	private $m_columnCallbacks = [];

	/** @var array(string) The hidden columns. */
	private $m_hiddenColumns = [];

	/** @var array The column display titles. */
	private $m_columnTitles = [];

	/** @var string|PageElement|null The content to display when there is no data. */
	private $m_noDataContent = null;

	/** @var Request|null The request to submit when a new page is requested. */
	private $m_pagingRequest = null;

	/** @var bool Whether or not check boxes are shown. */
	private $m_checkboxesVisible = true;

	/** @var array(int) The indices of the rows that have been checked. */
	private $m_checkedRows = [];

	/** @var array MIME-type-specific output options. */
	private $m_mimeTypeOptions = [];

	/**
	 * Create a new results pager.
	 *
	 * If _$id_ is not provided or is empty, a unique id will be generated internally.
	 *
	 * @param $results PDOStatement|null _optional_ The results to page.
	 * @param $id string _optional_ The ID for the results pager.
	 */
	public function __construct(?PDOStatement $results = null, $id = "") {
		// returns immediately if constructor has previously been called from fetchCachedPager() already. While not
		// perfectly efficient, it's sufficiently so
		self::purgeExpiredCacheEntries();

		if(empty($id)) {
			$id = self::generateUid();
		}

		parent::__construct($id);
		$this->setResults($results);
	}

	/**
	 * Purge expired entries from the results cache.
	 *
	 * A cache entry is considered expired when it hasn't been accessed for a specific number of seconds. The number of
	 * seconds is given by the class constant _ResultsCacheExpiryTimeout_.
	 *
	 * This method is called from the constructor every time a new pager is created. It only actually executes at most
	 * once per program execution. On the first call during a program execution (e.g. a user request is received by the
	 * web server), an internal flag is set which causes future calls to exit immediately without doing anything. This
	 * ensures that during a user request, whenever a pager is used the cache is first purged of old entries if
	 * necessary, whilst also ensuring that unnecessary purge cycles do not take place and slow down program execution.
	 *
	 * On the first call, the method checks when the last purge was carried out, and if it was more than a specific
	 * number of seconds ago, it does another purge. The number of seconds is specified in the class constant
	 * _ResultsCachePurgeInterval_.
	 *
	 * When a purge is executed, all files in the results cache directory have their last access time assessed. If the
	 * file was last accessed more than _ResultsCacheExpiryTimeout_ seconds ago, it is assumed to be no longer needed
	 * and the file is deleted.
	 *
	 * ### Warning
	 * This method assumes that the cache directory is used exclusively for pager cache files. It will indiscriminately
	 * delete files that it considers out of date from the cache directory, whether they are actually cache files or
	 * not.
	 *
	 * The current purge schedule is to remove all cache files that have not been accessed for 30 minutes, every 30
	 * minutes.
	 */
	private static function purgeExpiredCacheEntries(): void {
		// only purge once per run - multiple ResultsPager objects created during processing of a request will result in
		// at most one cache purge
		static $s_done = false;

		if($s_done) {
			return;
		}

		$s_done = true;

		/* check whether internal purging turned off in config file */
		if(defined("class.resultspager.cache.purge.enabled") && !constant("class.resultspager.cache.purge.enabled")) {
			return;
		}

		$cachePath         = self::cacheDirectory();
		$lastPurgeTimePath = "$cachePath/lastpurgetime";

		if(file_exists($lastPurgeTimePath)) {
			$lastPurgeTime = @file_get_contents($lastPurgeTimePath);

			if(false === $lastPurgeTime) {
				AppLog::error("file \"$lastPurgeTimePath\" could not be read", __FILE__, __LINE__, __FUNCTION__);
				$lastPurgeTime = 0;
			}
			else if(!is_numeric($lastPurgeTime)) {
				AppLog::error("file \"$lastPurgeTimePath\" contains invalid content", __FILE__, __LINE__, __FUNCTION__);
				$lastPurgeTime = 0;
			}
			else {
				$lastPurgeTime = intval($lastPurgeTime);
			}
		}
		else {
			AppLog::warning("file \"$lastPurgeTimePath\" not found", __FILE__, __LINE__, __FUNCTION__);
			$lastPurgeTime = 0;
		}

		$thisPurgeTime = microtime(true);

		if($lastPurgeTime < ($thisPurgeTime - self::ResultsCachePurgeInterval)) {
			// do a purge
			$paths = glob("$cachePath/*.meta", GLOB_NOSORT);

			foreach($paths as $filePath) {
				if(!preg_match("/resultspager-([0-9a-f]{32})\.meta$/", $filePath, $caps)) {
					AppLog::error("rogue file found in results pager cache directory: \"$filePath\"", __FILE__, __LINE__, __FUNCTION__);
					continue;
				}

				$fileTime = fileatime($filePath);

				if($fileTime < $thisPurgeTime - self::ResultsCacheExpiryTimeout) {
					$id = $caps[1];

					$entryFiles = glob("$cachePath/*$id.*", GLOB_NOSORT);

					foreach($entryFiles as $entryFilePath) {
						if(!@unlink($entryFilePath)) {
							AppLog::error("...cache file \"$entryFilePath\" for cache entry \"$id\" could not be deleted", __FILE__, __LINE__, __FUNCTION__);
						}
					}
				}
			}

			// update the last purge time to be now
			@file_put_contents($lastPurgeTimePath, intval($thisPurgeTime));
		}
	}

	/**
	 * Fetch the list of MIME types that the pager supports.
	 *
	 * This method can be reimplemented in subclasses to support more output MIME types. Reimplementations should
	 * probably include the list of built-in types supported by this base class in their returned array by calling this
	 * base class method, unless they wish to "disable" some of the built-in types. (Note that this will not actually
	 * disable the support for those types, it will just not list them as supported.
	 *
	 * @return array[string] The list of supported MIME types.
	 */
	public function supportedMimeTypes(): array {
		return array_keys(self::$s_supportedMimeTypes);
	}

	/**
	 * Fetch the request that will be used to navigate through the pages.
	 *
	 * @return Request The paging request, or _null_ if no paging request has been set.
	 */
	public function pagingRequest(): ?Request {
		return $this->m_pagingRequest;
	}

	/**
	 * Set the request that will be used to navigate through the pages.
	 *
	 * The request can be set to _null_ to remove the existing paging request.
	 *
	 * The paging request is used in the navigation panel that enables users to
	 * switch between pages in the results. The pager object will add some of
	 * its own URL parameters to the request that indicate the ID of the results
	 * (so that the pager can be retrieved from the cache), the page size and
	 * the page index. Any POST data in the request is ignored.
	 *
	 * @param $req ?Request The request to use.
	 */
	public function setPagingRequest(?Request $req): void {
		$this->m_pagingRequest = $req;
	}

	/**
	 * Fetch the page size.
	 *
	 * @return int The page size.
	 */
	public function pageSize(): int {
		return $this->m_pageSize;
	}

	/**
	 * Set the number of rows that appear on each page.
	 *
	 * This can be set to 0 to indicate that the page size is unlimited, i.e. output should show all rows on a single
	 * page.
	 *
	 * @param $size int The number of rows.
	 *
	 * @return bool _true_ if the page size was set, _false_ if the size was invalid.
	 */
	public function setPageSize(int $size): bool {
		if(0 > $size) {
			AppLog::error("invalid page size: $size", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_pageSize = $size;
		return true;
	}

	/**
	 * Fetch the page number that will be displayed.
	 *
	 * @return int The page number.
	 */
	public function pageNumber(): int {
		return $this->m_pageNumber;
	}

	/**
	 * Set the page number that will be displayed.
	 *
	 * When an output method is called with the flags set appropriately, only the page set will be displayed.
	 *
	 * @param $number int The number of the page to display when the pager is output.
	 *
	 * @return bool _true_ if the page number was set, _false_ otherwise.
	 */
	public function setPageNumber(int $number): bool {
		if(1 > $number) {
			AppLog::error("invalid page number: $number", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_pageNumber = $number;
		return true;
	}

	/**
	 * Fetch the ID of the results.
	 *
	 * The ID might be empty if the pager is invalid.
	 *
	 * @return string The ID.
	 */
	public function resultsId(): string {
		return $this->m_resultsId;
	}

	/**
	 * Set the name.
	 *
	 * The name can be used for setting the filename for downloading, etc. It has no internal significance.
	 *
	 * @param $name string The name to use.
	 */
	public function setName(string $name): void {
		$this->m_name = $name;
		$this->cacheMetaData();
	}

	/**
	 * Fetch the pager's name.
	 *
	 * @return string The name.
	 */
	public function name(): string {
		return $this->m_name;
	}

	/**
	 * Fetch the pager's results.
	 *
	 * @return PDOStatement|null The results, or _null_ if no results have been
	 * set.
	 */
	public function results(): ?PDOStatement {
		return $this->m_results;
	}

	/**
	 * Set the results that the pager will display.
	 *
	 * The results can be _null_ to remove the existing results.
	 *
	 * All features of the pager will be reset to default values, except the name. If the results provided are a valid
	 * _PDOStatement_, it will be traversed and all the rows will be cached.
	 *
	 * @param $results PDOStatement|null The results to display.
	 */
	public function setResults(?PDOStatement $results): void {
		if(is_null($results)) {
			$this->m_resultsId       = "";
			$this->m_results         = $results;
			$this->m_rowCount        = 0;
			$this->m_checkedRows     = [];
			$this->m_hiddenColumns   = [];
			$this->m_columnTitles    = [];
			$this->m_groupColumns    = [];
			$this->m_columnCallbacks = [];
			$this->cacheResults();
			$this->cacheMetaData();
			$this->cacheCheckedRows();
			$this->cacheHiddenColumns();
			$this->cacheColumnTitles();
		}
		else {
			$this->m_resultsId       = md5(rand() . "" . microtime(true));
			$this->m_results         = $results;
			$this->m_rowCount        = 0;
			$this->m_checkedRows     = [];
			$this->m_hiddenColumns   = [];
			$this->m_columnTitles    = [];
			$this->m_groupColumns    = [];
			$this->m_columnCallbacks = [];
			/* cacheResults() ensures m_rowCount is populated, so *must* call
			 * cacheResults() before cacheMetaData() */
			$this->cacheResults();
			$this->cacheMetaData();
			$this->cacheCheckedRows();
			$this->cacheHiddenColumns();
			$this->cacheColumnTitles();
		}
	}

	/**
	 * Fetch the number of rows in the result set the pager presents.
	 *
	 * @return int The row count. This will be 0 if no results have been set.
	 */
	public function rowCount(): int {
		return $this->m_rowCount;
	}

	/**
	 * Test whether check boxes are visible.
	 *
	 * @return bool _true_ if check boxes are visible, _false_ otherwise.
	 */
	public function checkboxesVisible(): bool {
		return $this->m_checkboxesVisible;
	}

	/**
	 * Set whether check boxes will be visible for rows in the pager.
	 *
	 * @param $v boolean Whether or not the check boxes should be visible.
	 */
	public function setCheckboxesVisible(bool $v): void {
		$this->m_checkboxesVisible = $v;
	}

	/**
	 * Mark a row as checked or unchecked.
	 *
	 * Row indices are 0-based.
	 *
	 * @param $i int The index of the row to check.
	 * @param $checked bool _true_ if the row is to be checked, _false_ if it is to be unchecked.
	 *
	 * @return bool _true_ if the row was checked/unchecked as requested, _false_ if the index was invalid or could not
	 * be marked as requested.
	 */
	public function setRowChecked(int $i, bool $checked): bool {
		if($checked) {
			if(!in_array($i, $this->m_checkedRows)) {
				$this->m_checkedRows[] = $i;
				$this->cacheCheckedRows();
			}
		}
		else {
			$pos = array_search($i, $this->m_checkedRows);

			if(false !== $pos) {
				array_splice($this->m_checkedRows, $pos, 1);
				$this->cacheCheckedRows();
			}
		}

		return true;
	}

	/**
	 * Fetch the list of checked rows.
	 *
	 * @return array(int) The checked rows. The array will be empty if no rows are checked.
	 */
	public function checkedRows(): array {
		return $this->m_checkedRows;
	}

	/**
	 * Test whether a row is marked as checked.
	 *
	 * Indices are 0-based.
	 *
	 * @param $i int The index of the row to test.
	 *
	 * @return bool _true_ if the row is checked, _false_ if not or if the row index is invalid or out of bounds.
	 */
	public function isRowChecked(int $i): bool {
		return in_array($i, $this->m_checkedRows);
	}

	/**
	 * Hide a column when displaying the pager.
	 *
	 * @param $col string The name of the column to hide.
	 */
	public function hideColumn(string $col): void {
		if(!in_array($col, $this->m_hiddenColumns)) {
			$this->m_hiddenColumns[] = $col;
			$this->cacheHiddenColumns();
		}
	}

	/**
	 * Mark a column as not being hidden.
	 *
	 * @param $col string The name of the column to unhide.
	 */
	public function unhideColumn(string $col): void {
		$changed = false;

		while(false !== ($i = array_search($col, $this->m_hiddenColumns))) {
			array_splice($this->m_hiddenColumns, $i, 1);
			$changed = true;
		}

		if($changed) {
			$this->cacheHiddenColumns();
		}
	}

	/**
	 * Fetch the list of hidden columns.
	 *
	 * @return array[string] The list of hidden columns.
	 */
	protected function hiddenColumns(): array {
		return $this->m_hiddenColumns;
	}

	/** Set all columns to be visible. */
	public function unhideAllColumns(): void {
		$this->m_hiddenColumns = [];
		$this->cacheHiddenColumns();
	}

	/**
	 * Set the title for a column.
	 *
	 * The title can be _null_ to unset a custom title for a column and revert to the default title (the column name).
	 * Note that the empty string _""_ is a valid custom title (i.e. you use it to force the column to have no title),
	 * and is distinct from _null_ which causes the column to have its default title.
	 *
	 * @param $col string The column whose title should be set.
	 * @param $title string|PageElement|null The title to set.
	 *
	 * @return bool _true_ if the column title was set (or unset), _false_ if not.
	 */
	public function setColumnTitle(string $col, $title) {
		if(is_null($title) || is_string($title) || $title instanceof PageElement) {
			$this->m_columnTitles[$col] = $title;
			$this->cacheColumnTitles();
			return true;
		}

		AppLog::error("invalid title", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set the columns whose values will be used to group the data.
	 *
	 * The columns to group by can be provided as a single array of strings containing each column to group by, or as a
	 * variable-length set of parameter strings (i.e. `setGroupColumns( $column, ... )`).
	 *
	 * Grouping causes output of the data to emit subheadings whenever the value of one of the specified columns in the
	 * row being output is different from the value in the same column in the previous row. This has the effect of
	 * grouping rows with like values in those columns under a subheading. Grouping by a column will usually have the
	 * effect of causing that column to be hidden from the tabulated data (i.e. its value will only be shown in the
	 * subheading). This behaviour can be controlled when the data is output with the use of the _DontHideGroupColumns_
	 * output flag.
	 *
	 * Any columns provided that are found not to be present in the data when it is output will be silently ignored.
	 *
	 * Set this to an empty array (i.e. provide _[]_ or call with no arguments) to disable grouping by column values.
	 * There is also an output flag that can be used to temporarily ignore this feature while the data is being output.
	 *
	 * @param string ...$columns The columns to use for grouping.
	 */
	public function setGroupColumns(string ... $columns): void {
		// variadic enforces type so no need to check array content
		$this->m_groupColumns = $columns;
	}

	/**
	 * Fetch the list of columns to group by.
	 *
	 * @return array The list of columns.
	 */
	public function groupColumns(): array {
		return $this->m_groupColumns;
	}

	/**
	 * Fetch the list of column titles.
	 *
	 * In the returned array, the keys are the column names, the values the column titles. Any column that has not had a
	 * custom title set will not have an entry in the array.
	 *
	 * @return array[string=>string] The column titles.
	 */
	protected function columnTitles(): array {
		return $this->m_columnTitles;
	}

	/**
	 * Add a callback for a column.
	 *
	 * All callbacks should expect to receive the following parameters, in this order:
	 * - **$value** _mixed_ The value in the column to process.
	 * - **$column** _string_ The column the value comes from.
	 * - **$row** array[string->mixed] The data from the row the value comes from.
	 * - **$mimeType** _string_ The MIME type being used for output.
	 *
	 * Callbacks should process the value and return a value that can be coerced into a string (preferably actually a
	 * string). If the callback does not want to process the value, it should return it unmodified.
	 *
	 * Note that callbacks are invoked as variable functions, not by using call_user_func(). This means that callbacks
	 * of the following forms are not supported (even though they satisfy _callable_):
	 * - ['MyClass', 'parent::myMethod']
	 * - 'MyClass::myStaticMethod'
	 *
	 * @param $columnName string The column for which to add a callback.
	 * @param $callback callable The callback to use.
	 */
	public function addColumnCallback(string $columnName, callable $callback): void {
		if(!isset($this->m_columnCallbacks[$columnName])) {
			$this->m_columnCallbacks[$columnName] = [];
		}

		$this->m_columnCallbacks[$columnName][] = $callback;
	}

	/**
	 * Set MIME-type specific output options.
	 *
	 * Any previously-set options are wiped out by a call to this method. The options supported are specific to the MIME
	 * type and the (sub)class that implements it.
	 *
	 * @param $type string The MIME type.
	 * @param $options array[string=>mixed] The options.
	 */
	public function setMimeTypeOptions(string $type, array $options): void {
		$this->m_mimeTypeOptions[$type] = $options;
	}

	/**
	 * Set a MIME-type specific output option.
	 *
	 * Any previously-set option with the same name is replaced by a call to this method. The options supported are
	 * specific to the MIME type and the (sub)class that implements it.
	 *
	 * @param $type string The MIME type.
	 * @param $option string The option name.
	 * @param $value mixed The value for the option.
	 */
	public function setMimeTypeOption(string $type, string $option, $value): void {
		if(!isset($this->m_mimeTypeOptions[$type])) {
			$this->m_mimeTypeOptions[$type] = [$option => $value];
		}
		else {
			$this->m_mimeTypeOptions[$type][$option] = $value;
		}
	}

	/**
	 * Unset a MIME-type specific output option.
	 *
	 * Any option with the given name is removed by a call to this method.
	 *
	 * @param $type string The MIME type.
	 * @param $option string The option name.
	 */
	public function unsetMimeTypeOption(string $type, string $option): void {
		if(isset($this->m_mimeTypeOptions[$type])) {
			unset($this->m_mimeTypeOptions[$type][$option]);
		}
	}

	/**
	 * Fetch the value of a MIME-type specific output option.
	 *
	 * @param $type string The MIME type.
	 * @param $option string The option name.
	 *
	 * @return mixed The option value if the option was found, _null_ if not.
	 */
	public function mimeTypeOption(string $type, string $option){
		return (isset($this->m_mimeTypeOptions[$type][$option]) ? $this->m_mimeTypeOptions[$type][$option] : null);
	}

	/**
	 * Fetch the output options for a specific MIME type.
	 *
	 * If there are no options set for the provided MIME type, an empty array is returned.
	 *
	 * @param $type string The MIME type.
	 *
	 * @return array[string=>mixed] The options.
	 */
	public function mimeTypeOptions(string $type): array {
		return (isset($this->m_mimeTypeOptions[$type]) ? $this->m_mimeTypeOptions[$type] : []);
	}

	/**
	 * Call all the callbacks registered for a value in a column.
	 *
	 * This method calls all of the callbacks registered for a column, with the other parameters provided. The callbacks
	 * are called in the order in which they were added.
	 *
	 * The MIME type defaults to *text/html*.
	 *
	 * @param $value mixed The value in the column.
	 * @param $column string The name of the column.
	 * @param $row array[string=>mixed] All the data in the row from which the value comes.
	 * @param $mimeType string _optional_ The MIME type being used for output.
	 *
	 * @return mixed The value resulting from the processing carried out by all of the callbacks.
	 */
	protected function doColumnCallbacks($value, string $column, array $row, string $mimeType = "text/html") {
		if(isset($this->m_columnCallbacks[$column])) {
			foreach($this->m_columnCallbacks[$column] as $callback) {
				if(is_callable($callback, false)) {
					$value = $callback($value, $column, $row, $mimeType);
				}
				else {
					AppLog::error("found uncallable callback: " . stringify($callback), __FILE__, __LINE__, __FUNCTION__);
				}
			}

			return $value;
		}

		return ("text/html" == $mimeType ? html("$value") : $value);
	}

	/**
	 * Remove the cache files for the pager.
	 *
	 * All cached information about the results and the pager's state is removed.
	 */
	protected function clearCache() {
		$this->removeCacheFile("results");
		$this->removeCacheFile("checked");
		$this->removeCacheFile("classname");
		$this->removeCacheFile("hidden");
		$this->removeCacheFile("columntitles");
		$this->removeCacheFile("meta");
	}

	/**
	 * Remove some cached information about the result set.
	 *
	 * The type is really just the cache file's extension. There is one special
	 * case, the "results" type, which removes all the chunk files for the
	 * cached results.
	 *
	 * @param $ext string The type of the cache file to remove.
	 */
	protected function removeCacheFile(string $ext): void {
		if(false !== strpos("..", $ext)) {
			AppLog::error("aborted cache file removal - found \"..\" in extension", __FILE__, __LINE__, __FUNCTION__);
			return;
		}

		if("results" == $ext) {
			$pathBase = self::cacheFilePath($this->resultsId() . ".");
			$i        = 0;

			// keep attempting to delete cache chunk files until an index is not found.
			while(true) {
				$path = $pathBase . sprintf("%04d.results", $i);

				if(!file_exists($path)) {
					break;
				}

				if(is_file($path) && is_writable($path)) {
					@unlink($path);
				}

				++$i;
			};
		}
		else {
			$path = self::cacheFilePath($this->resultsId(), $ext);

			if(is_file($path) && is_writable($path)) {
				@unlink($path);
			}
		}
	}

	/** Cache the results, if they are not cached already. */
	protected function cacheResults(): void {
		$resultsId = $this->resultsId();

		if(empty($resultsId)) {
			AppLog::warning("no ID - can't cache results", __FILE__, __LINE__, __FUNCTION__);
			return;
		}

		$path = self::cacheFilePath($resultsId);

		if(file_exists("$path.0000.results")) {
			AppLog::warning("cache path \"$path\" already in use", __FILE__, __LINE__, __FUNCTION__);
			return;
		}

		// ensure cache file exists even if there are no results to write
		@touch("$path.0000.results");
		$results = $this->results();

		if($results instanceof PDOStatement) {
			$rowIndex   = 0;
			$chunkIndex = 0;
			$myData     = [];

			while(!!($myRow = $results->fetch(PDO::FETCH_ASSOC))) {
				$myData[] = $myRow;
				++$rowIndex;

				if(0 == $rowIndex % self::ResultsCacheFileChunkSize) {
					$this->writeCachedResultsData($myData, $chunkIndex);
					++$chunkIndex;
					$myData = [];
				}
			}

			// write any partial chunk left over after the end of the last chunk that was written to disk
			if(0 != $rowIndex % self::ResultsCacheFileChunkSize) {
				$this->writeCachedResultsData($myData, $chunkIndex);
			}

			$this->m_rowCount = $rowIndex;

			// cache the class name, row count and name
			$this->cacheMetaData();
		}
	}

	/**
	 * Write the results to the cache files.
	 *
	 * @param $data array[string] the data to cache.
	 * @param $chunkIndex int The index number of the cache file chunk to write.
	 *
	 * Because the data for a pager can be huge, it is broken into chunks of 5000 (by default - see const
	 * _ResultsCacheFileChunkSize_) rows. This means that the pager can read sections of data from the cache as required
	 * rather than having to load all the data from the cache. In turn, this means that extremely large result sets are
	 * less likely to cause the PHP process to exhaust its *memory_limit* ini setting trying to store all the data for
	 * all rows in an array.
	 */
	private function writeCachedResultsData(array $data, int $chunkIndex): void {
		$resultsId = $this->resultsId();

		if(empty($resultsId)) {
			return;
		}

		$path = self::cacheFilePath($resultsId, sprintf("%04d", $chunkIndex) . ".results");

		if(false === file_put_contents($path, serialize($data))) {
			AppLog::error("failed to write file to cache search results (data file, id = $resultsId, chunk = $chunkIndex)", __FILE__, __LINE__, __FUNCTION__);
			return;
		}

		if(!@chmod($path, 0770)) {
			AppLog::warning("failed to set permissions for search results checked rows cache file (id = $resultsId, chunk = $chunkIndex)", __FILE__, __LINE__, __FUNCTION__);
		}
	}

	/** Write the list of checked rows to its cache file. */
	protected function cacheCheckedRows(): void {
		$resultsId = $this->resultsId();

		if(empty($resultsId)) {
			return;
		}

		if(is_array($this->m_checkedRows)) {
			$path = self::cacheFilePath($resultsId, "checked");

			if(false === file_put_contents($path, serialize($this->m_checkedRows))) {
				AppLog::error("failed to write file to cache search results checked rows (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
				return;
			}

			if(!@chmod($path, 0770)) {
				AppLog::warning("failed to set permissions for search results checked rows cache file (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
			}
		}
	}

	/**
	 * Write the result set meta data to its cache file.
	 *
	 * The meta data written is:
	 * - the name of the result set
	 * - the number of rows in the result set
	 */
	protected function cacheMetaData(): void {
		$resultsId = $this->resultsId();

		if(empty($resultsId)) {
			return;
		}

		$path = self::cacheFilePath($resultsId, "meta");

		if(false === file_put_contents($path, serialize([get_class($this), $this->m_name, $this->m_rowCount]))) {
			AppLog::error("failed to write file to cache pager meta data (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
			return;
		}

		if(!@chmod($path, 0770)) {
			AppLog::warning("failed to set permissions for search results meta data cache file (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
		}
	}

	/** Write the list of hidden columns to its cache file. */
	protected function cacheHiddenColumns(): void {
		$resultsId = $this->resultsId();

		if(empty($resultsId)) {
			return;
		}

		if(is_array($this->m_hiddenColumns)) {
			$path = self::cacheFilePath($resultsId, "hidden");

			if(false === file_put_contents($path, serialize($this->m_hiddenColumns))) {
				AppLog::error("failed to write file to cache hidden columns (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
				return;
			}

			if(!@chmod($path, 0770)) {
				AppLog::warning("failed to set permissions for search results hidden columns cache file (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
			}
		}
	}

	/** Write the list of column titles to its cache file. */
	protected function cacheColumnTitles(): void {
		$resultsId = $this->resultsId();

		if(empty($resultsId)) {
			return;
		}

		if(is_array($this->m_columnTitles)) {
			$path = self::cacheFilePath($resultsId, "columntitles");

			if(false === file_put_contents($path, serialize($this->m_columnTitles))) {
				AppLog::error("failed to write file to cache column titles (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
				return;
			}

			if(!@chmod($path, 0770)) {
				AppLog::warning("failed to set permissions for search results column titles cache file (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
			}
		}
	}

	/**
	 * Retrieve a cached pager.
	 *
	 * Results pagers are cached for future use, based on a unique ID, so that paging through the results does not need
	 * to re-query the database. This makes them very fast to page, at the possible expense of the data being fixed at
	 * the time of the original query (i.e. the result set does not keep up with subsequent changes to the database it
	 * was drawn from).
	 *
	 * This method rebuilds and returns a ResultsPager object from the cache.
	 *
	 * If no _$className_ is provided, or it is _null_ or not a subclass of the _ResultsPager_ base class, the original
	 * _ResultsPager_ (sub) class used to create the pager is used.
	 *
	 * @param $resultsId string The ID of the pager to fetch.
	 * @param $className string _optional_ Force the pager to be of a specific class, if it is a subclass of this base
	 * class, rather than the original class that was used to create the pager.
	 *
	 * @return ResultsPager|null The reconstituted cached pager, or _null_ if the pager could not be rebuilt (e.g. if
	 * the ID provided is not valid).
	 */
	public static function fetchCachedPager(string $resultsId, ?string $className = ""): ?ResultsPager {
		/* we make this call here so that the cache is purged before the meta
		 * data is read, since reading the meta data to retrieve the class
		 * name will update the atime on the metadata cache file, which will in
		 * turn mean that the metadata cache file will remain while the rest of
		 * the cache files for the same pager will potentially be removed by the
		 * call to purgeExpiredCacheEntries() made from the constructor.
		 */
		self::purgeExpiredCacheEntries();

		if(empty($className)) {
			[$className, , ] = self::readCachedMetaData($resultsId);
		}

		// TODO use reflection API here?
		// calling is_subclass_of() automatically invokes __autoload()
		if(empty($className) || (ResultsPager::class != $className && !is_subclass_of($className, ResultsPager::class))) {
			AppLog::error("invalid pager class \"$className\", using base class " . ResultsPager::class, __FILE__, __LINE__, __FUNCTION__);
			$className = ResultsPager::class;
		}

		/** @var ResultsPager $ret */
		$ret = new $className();

		if(!$ret->fromResultsId($resultsId)) {
			return null;
		}

		return $ret;
	}

	/**
	 * Read the contents of a cache file.
	 *
	 * This method will look for the provided file in the cache and return its contents if it exists.
	 *
	 * @param $fileName string The name of the cache file to read.
	 *
	 * @return string|null The contents of the cache file, or _null_ if the file could not be found or read.
	 */
	private static function readCacheFile(string $fileName): ?string {
		$cachePath = self::cacheFilePath($fileName);

		if(!is_readable($cachePath) || !is_file($cachePath)) {
			AppLog::error("invalid or unreadable cache file \"$cachePath\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		// ensure that the file atime is updated even if the fs is mounted noatime
		@touch($cachePath);
		return file_get_contents($cachePath);
	}

	/**
	 * Read the results from a cache file.
	 *
	 * By default (i.e. when no $id is provided) this method attempts to read the cached results for the current object.
	 *
	 * Chunks are indexed from 0. Currently, each chunk contains 5000 rows, and chunks are ordered in the order in which
	 * the rows appear in the result set. See the class constant _ResultsCacheFileChunkSize_ for the actual chunk size
	 * (in case this documentation has not been updated).
	 *
	 * @param $id string The ID of the results to read from the cache.
	 * @param $chunkIndex int The index of the results cache chunk file to read.
	 *
	 * @return array[string=>mixed]|null The contents of the cache file.
	 */
	protected static function readCachedResults(string $id, int $chunkIndex): ?array {
		$content = self::readCacheFile("$id." . sprintf("%04d", $chunkIndex) . ".results");

		if(is_null($content)) {
			AppLog::error("failed to read content of cache file \"$id." . sprintf("%04d", $chunkIndex) . ".results\"", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		// cache file can be an empty file (i.e. not a serialised empty array) if results were empty when cached
		if(empty($content)) {
			return [];
		}

		return @unserialize($content);
	}

	/**
	 * Read the list of checked rows from a cache file.
	 *
	 * The checked rows cache file for the pager is read and parsed.
	 *
	 * @param $id string The ID of the results to read from the cache.
	 *
	 * @return array[int] The checked rows list read from the cache file.
	 */
	protected static function readCachedCheckedRows(string $id): array {
		return @unserialize(self::readCacheFile("$id.checked"));
	}

// 	protected function readCachedName() {
// 		return self::readCacheFile($this->resultsId() . '.name');
// 	}

	/**
	 * Read the pager metadata from a cache file.
	 *
	 * The metadata cache file for the pager is read and parsed. The metadata is returned as an array containing the
	 * following elements:
	 * - className _string_
	 * - name _string_
	 * - rowCount _int_
	 *
	 * The recommended way of reading the returned metadata is something like this:
	 *     [$className, $name, $rowCount] = self::readCachedMetaData($id);
	 *
	 * @param $id string The ID of the results to read from the cache.
	 *
	 * @return array The metadata read from the cache file.
	 */
	protected static function readCachedMetaData(string $id): array {
		return @unserialize(self::readCacheFile("$id.meta"));
	}

	/**
	 * Read the list of hidden columns from a cache file.
	 *
	 * The hidden columns cache file for the pager is read and parsed.
	 *
	 * @param $id string The ID of the results to read from the cache.
	 *
	 * @return array[string] The hidden columns list read from the cache file.
	 */
	protected function readCachedHiddenColumns(string $id): array {
		return @unserialize(self::readCacheFile("$id.hidden"));
	}

	/**
	 * Read the list of hidden columns from a cache file.
	 *
	 * The column titles cache file for the pager is read and parsed.
	 *
	 * @param $id string The ID of the results to read from the cache.
	 *
	 * @return array[string=>string] The column titles read from the cache
	 * file.
	 */
	protected static function readCachedColumnTitles(string $id): array {
		return @unserialize(self::readCacheFile("$id.columntitles"));
	}

	/**
	 * Fetch the path to the directory where cache files are stored.
	 *
	 * If the cache directory does not exist, can't be created, or is not writable, an fatal application error is
	 * generated and the script terminates immediately. _ResultsPager_ objects are unable to function without a working
	 * cache.
	 *
	 * @return string The cache file directory.
	 */
	protected static function cacheDirectory(): string {
		static $s_dir = null;

		if(is_null($s_dir)) {
			if(!defined("app.uid")) {
				$appUid = "unknownapp";
			}
			else {
				$appUid = constant("app.uid");
			}

			$s_dir = sys_get_temp_dir() . "/$appUid-pager-cache";

			if(!file_exists($s_dir)) {
				@mkdir($s_dir, 0770);
			}

			if(!file_exists($s_dir) || !is_dir($s_dir) || !is_writable($s_dir)) {
				AppLog::error("can't find, create or write to results pager cache directory \"$s_dir\"", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("A fatal application error occurred (%1). Please contact the administrator.", __FILE__, __LINE__, "ERR_RESULTSPAGER_NO_CACHE_DIR"), E_USER_ERROR);
			}
		}

		return $s_dir;
	}

	/**
	 * Fetch the path to a cache file for a set of results.
	 *
	 * The base path for cache files belonging to the pager identified by _$resultsId_ is constructed. If _$suffix_ is
	 * provided, it is appended to the path with a preceding dot (i.e. _$cacheFilePath.$suffix_).
	 *
	 * This method does not validate either parameter, so it is your subclass's responsibility to ensure that non-valid
	 * characters do not appear in IDs or suffixes (in general, avoid any character sequence that is not valid in a file
	 * name, but especially avoid ":", "/" and ".."). A future version of this function may enforce validation rules.
	 *
	 * This method uses _cacheDirectory()_ and therefore will generate a fatal application error if the cache directory
	 * does not exist, can't be created or is not writable.
	 *
	 * If no suffix is given, an empty suffix is used.
	 *
	 * @param $resultsId string The ID of the results whose cache file is sought.
	 * @param $suffix string _optional_ The suffix for the cache file.
	 *
	 * @return string The path to the cache file.
	 */
	protected static function cacheFilePath(string $resultsId, string $suffix = ""): string {
		return self::cacheDirectory() . "/resultspager-$resultsId" . (empty($suffix) ? "" : ".$suffix");
	}

	/**
	 * Populate a pager from a cache entry.
	 *
	 * @param $resultsId string The ID of the cached pager to fetch.
	 *
	 * @return bool _true_ if the pager was repopulated from the cache, _false_ if it could not be rebuilt (e.g. the ID
	 * was not valid).
	 */
	private function fromResultsId(string $resultsId): bool {
		$path = self::cacheFilePath($resultsId);

		if(!is_readable("$path.0000.results")) {
			AppLog::error("cached results pager with ID \"$resultsId\" (file \"$path.0000.results\") not found", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_resultsId   = $resultsId;
		$this->m_checkedRows = self::readCachedCheckedRows($resultsId);
		[, $this->m_name, $this->m_rowCount] = self::readCachedMetaData($resultsId);
		$this->m_hiddenColumns = self::readCachedHiddenColumns($resultsId);
		$this->m_columnTitles  = self::readCachedColumnTitles($resultsId);
		return true;
	}

	/**
	 * Fetch the content to display when the pager has no data.
	 *
	 * @return string|PageElement|null The content to display (_null_ if none has been set).
	 */
	public function noDataContent() {
		return $this->m_noDataContent;
	}

	/**
	 * Set the content to display when the pager has no data.
	 *
	 * @param $content string|PageElement|null The content to show.
	 *
	 * @return bool _true_ if the content was set, _false_ otherwise.
	 */
	public function setNoDataContent($content): bool {
		if(is_string($content) || is_null($content) || $content instanceof PageElement) {
			$this->m_noDataContent = $content;
			return true;
		}

		AppLog::error("invalid content", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Generate the page index.
	 *
	 * The page index always includes the first page and the last page. Between those, it includes a consecutive series
	 * of page numbers centred on the current page (as indicated by _pageNumber()_). So, for example, if the pager
	 * contains 100 pages and is currently set to display page 20, calling _emitPageIndex(1, 100, 7)_ will produce the
	 * following index:
	 * 1 ... 13 14 15 16 17 18 19 20 21 22 23 24 25 26 26 27 ... 100
	 *
	 * The default distance is 5.
	 *
	 * @param $start int The first page number to include in the index.
	 * @param $end int The last page number to include in the index.
	 * @param $distance int _optional_ The number of pages either side of the current page to include in the index.
	 *
	 * @return string The HTML for the page index.
	 */
	protected function emitPageIndex(int $start, int $end, int $distance = 5): string {
		$html = "";
		$req  = $this->pagingRequest();

		if($req instanceof Request) {
			$pageSize   = $this->pageSize();
			$pageNumber = $this->pageNumber();

			if(!$pageSize) {
				$pageSize = self::DefaultPageSize;
			}

			if(!$pageNumber) {
				$pageNumber = self::DefaultPageNumber;
			}

			$html = "<div class=\"resultspager-controls\"><ul>";
			$req->setUrlParameter("pager_resid", "" . $this->resultsId());
			$req->setUrlParameter("pager_pagesize", "$pageSize");
			$doneEllipsis = false;

			$req->setUrlParameter("pager_pagenumber", "" . ($pageNumber - 1));
			$html .= "<li class=\"previous\">&nbsp;";

			if(1 < $pageNumber) {
				$html .= "<a href=\"" . $req->url() . "\" title=\"" . html(tr("Show the previous page of results.")) . "\"><img class=\"icon\" src=\"images/icons/previous.png\" alt=\"&lt;\" /></a>";
			}
			else {
				/** @noinspection HtmlUnknownTarget */
				$html .= "<img class=\"disabled-icon\" src=\"images/icons/previous.png\" alt=\"&lt;\" />";
			}

			$html .= "&nbsp;</li>\n";

			for($i = $start; $i <= $end; ++$i) {
				if($i == $pageNumber) {
					$html .= "<li class=\"current\">[&nbsp;$i&nbsp;]</li>\n";

					// ensure we do ellipsis for distant pages after current page even if we've also done one before
					// current page
					$doneEllipsis = false;
				}
				// always show first, last and n pages either side of current page; otherwise elide "distant" pages
				else if($start != $i && $end != $i && abs($i - $pageNumber) > $distance) {
					if(!$doneEllipsis) {
						$html         .= "<li>&hellip;</li>\n";
						$doneEllipsis = true;
					}
				}
				else {
					$req->setUrlParameter("pager_pagenumber", "$i");
					$html .= "<li>[&nbsp;<a href=\"" . $req->url() . "\">$i</a>&nbsp;]</li>\n";
				}
			}

			$req->setUrlParameter("pager_pagenumber", "" . ($pageNumber + 1));
			$html .= "<li class=\"next\">&nbsp;";

			if($end > $pageNumber) {
				$html .= "<a href=\"" . $req->url() . "\" title=\"" . html(tr("Show the next page of results.")) . "\"><img class=\"icon\" src=\"images/icons/next.png\" alt=\"&gt;\" /></a>";
			}
			else {
				/** @noinspection HtmlUnknownTarget */
				$html .= "<img class=\"disabled-icon\" src=\"images/icons/next.png\" alt=\"&gt;\" />";
			}

			$html .= "&nbsp;</li>\n</ul></div>";
		}

		return $html;
	}

	/**
	 * Generate the HTML for a row's checkbox.
	 *
	 * @param $i int The index of the row whose checkbox is required.
	 *
	 * @return string The HTML for the checkbox.
	 */
	protected function emitCheckbox(int $i): string {
		if(!is_numeric($i)) {
			AppLog::error("invalid row index", __FILE__, __LINE__, __FUNCTION__);
			return "";
		}

		// note resultsId() is always internally generated and guaranteed to be HTML-safe
		return "<input type=\"checkbox\" class=\"resultspager-checkbox\" data-resultsId=\"" . $this->resultsId() . "\" data-rowindex=\"$i\" name=\"\" value=\"$i\"" . (in_array($i, $this->m_checkedRows) ? " checked=\"checked\"" : "") . " />";
	}

	/**
	 * Generate a HTML row ID based on the option provided in the HTML MIME type options.
	 *
	 * This method supports the text/html row_id_template option.
	 *
	 * @param $template string The template to use to generated the ID.
	 * @param $row array The data that is being displayed in the row.
	 *
	 * @return string The HTML-escaped ID.
	 */
	protected static function generateHtmlRowId(string $template, array $row): string {
		$from = [];
		$to   = [];

		foreach($row as $field => $value) {
			$from[] = "{$field}";
			$to[]   = $value;
		}

		return html(str_replace($from, $to, $template));
	}

	/**
	 * Get the HTML for the element.
	 *
	 * The following options are supported:
	 * - **row_id_template** _string_ A template to use to create an ID attribute value for each row in the output
	 *   table. It can contain placeholders for data from the row being displayed to be included in the generated ID.
	 *   Placeholders are the field name enclosed in **{}**. For example, if the result set has an _id_ field, putting
	 *   **{id}** in the template will include the value of the _id_ field in the row's ID attribute.
	 * - **page_index_location** _string_ "top", "bottom" or "both" to place the paging index with the results. The
	 *   default is both.
	 *
	 * Any option specified in the _$options_ array provided will override the same option set via setMimeTypeOption(s).
	 *
	 * By default, the _DefaultOutputFlags_ are used with an empty set of options.
	 *
	 * @param $flags int _optional_ Flags controlling the output.
	 * @param $options `[string=>string]` _optional_ Options specific to HTML output.
	 *
	 * @return string The element HTML.
	 */
	public function html(int $flags = self::DefaultOutputFlags, array $options = []): string {
		// m_rowCount is set to the correct value either when setResults() is called or when the cached meta data is
		// read in fromResultsId(). either way, if we have some results, m_rowCount is correct (unless the cache is
		// corrupt)
		$max                 = $this->m_rowCount;
		$resultsId           = $this->resultsId();
		$checkedRowsOnly     = ($flags & self::CheckedRowsOnly);
		$noneCheckedMeansAll = ($flags & self::NoCheckedRowsEqualsAllRows);
		$checkedRowCount     = 0;
		$doGroups            = !($flags & self::DontGroup);
		$hideGroupColumns    = $doGroups && !($flags & self::DontHideGroupColumns);

		if(is_array($options) && !empty($options)) {
			$options = array_merge($this->mimeTypeOptions("text/html"), $options);
		}
		else {
			$options = $this->mimeTypeOptions("text/html");
		}

		$haveRowIdTemplate = isset($options["row_id_template"]) && is_string($options["row_id_template"]);

		if(!$checkedRowsOnly) {
			$pageSize   = $this->pageSize();
			$pageNumber = $this->pageNumber();

			if(is_null($pageSize)) {
				$pageSize = self::DefaultPageSize;
			}
			else if(0 == $pageSize) {
				$pageSize = max(1, $max);
			}

			if(!$pageNumber) {
				$pageNumber = self::DefaultPageNumber;
			}

			// calculate which rows to display
			$end   = $pageNumber * $pageSize;
			$start = $end - $pageSize;

			if($end > $max) {
				$end = $max;
			}

			if($start > $max) {
				$start = $max;
			}

			$maxPage = ceil(doubleval($max) / doubleval($pageSize));
		}
		else {
			$start           = 0;
			$end             = $max;
			$maxPage         = 1;
			$checkedRowCount = count($this->m_checkedRows);
		}

		$chunkIndex = intval(floor($start / self::ResultsCacheFileChunkSize));
		$data       = self::readCachedResults($resultsId, $chunkIndex);

		if(!is_array($data)) {
			AppLog::error("invalid content in results cache", __FILE__, __LINE__, __FUNCTION__);
			return "";
		}

		if($start < $end) {
			$doCheckboxes = (!$checkedRowsOnly && $this->checkboxesVisible());

			if($doGroups) {
				// ensure we only have column names in our set to group by that actually exist
				$myGroupColumns = [];

				foreach($this->m_groupColumns as $column) {
					if(array_key_exists($column, $data[$start % self::ResultsCacheFileChunkSize])) {
						$myGroupColumns[] = $column;
					}
				}

				$doGroups = 0 < count($myGroupColumns);

				if($doGroups) {
					// initialise the array of current grouping values for each grouping column
					$currentGroupValues = [];
					$groupLevels        = count($myGroupColumns);

					for($i = 0; $i < $groupLevels; ++$i) {
						$currentGroupValues[$i] = null;
					}

					// work out how many columns our display table has
					$groupHeadingColSpan = 0;

					foreach($data[$start] as $column => $value) {
						if(in_array($column, $this->m_hiddenColumns)) {
							continue;
						}

						if($hideGroupColumns && in_array($column, $myGroupColumns)) {
							continue;
						}

						++$groupHeadingColSpan;
					}
				}
			}

			$myClassName = "resultspager" . ($doGroups ? " grouped" : "");
			$this->addClassName($myClassName);
			$tableHtml = "<table" . $this->emitAttributes() . ">";
			$this->removeClassName($myClassName);

			if(!($flags & self::NoColumnTitles)) {
				$tableHtml .= "<thead><tr>";

				if($doCheckboxes) {
					$tableHtml .= "<th></th>";
				}

				foreach($data[$start % self::ResultsCacheFileChunkSize] as $column => $value) {
					if(!($flags & self::DontHideColumns) && in_array($column, $this->m_hiddenColumns)) {
						continue;
					}

					if($doGroups && $hideGroupColumns && in_array($column, $myGroupColumns)) {
						continue;
					}

					$tableHtml .= "<th>";

					if(array_key_exists($column, $this->m_columnTitles)) {
						if(is_string($this->m_columnTitles[$column])) {
							$tableHtml .= html($this->m_columnTitles[$column]);
						}
						else if($this->m_columnTitles[$column] instanceof PageElement) {
							$tableHtml .= $this->m_columnTitles[$column]->html();
						}
						else {
							$tableHtml .= html($column);
						}
					}
					else {
						$tableHtml .= html($column);
					}

					$tableHtml .= "</th>";
				}

				$tableHtml .= "</tr></thead>\n";
			}

			$tableHtml .= "<tbody>\n";

			for($i = $start; $i < $end; ++$i) {
				// when there are no checked rows, do all rows
				if($checkedRowsOnly && (!$noneCheckedMeansAll || 0 != $checkedRowCount) && !in_array($i, $this->m_checkedRows)) {
					continue;
				}

				if($i >= ($chunkIndex + 1) * self::ResultsCacheFileChunkSize) {
					++$chunkIndex;
					$data = self::readCachedResults($resultsId, $chunkIndex);

					if(!is_array($data)) {
						AppLog::error("invalid content in results cache (id = " . $this->resultsId() . "; chunk = $chunkIndex)", __FILE__, __LINE__, __FUNCTION__);
						return "";
					}
				}

				// calculate the index into the chunk of data we've currently got loaded
				$dataRowIndex = $i % self::ResultsCacheFileChunkSize;

				if($doGroups) {
					/* work out if the value of any of our grouping columns has changed */
					for($groupLevel = 0; $groupLevel < $groupLevels; ++$groupLevel) {
						if($data[$dataRowIndex][$myGroupColumns[$groupLevel]] != $currentGroupValues[$groupLevel]) {
							break;
						}
					}

					for(; $groupLevel < $groupLevels; ++$groupLevel) {
						$currentGroupValues[$groupLevel] = $data[$dataRowIndex][$myGroupColumns[$groupLevel]];
						$tableHtml                       .= "<tr class=\"groupheading\"><td class=\"groupheading-level-$groupLevel\" colspan=\"$groupHeadingColSpan\">" . $this->doColumnCallbacks($currentGroupValues[$groupLevel], $myGroupColumns[$groupLevel], $data[$dataRowIndex], "text/html") . "</td></tr>";
					}
				}

				if($haveRowIdTemplate) {
					$tableHtml .= "<tr id=\"" . self::generateHtmlRowId($options["row_id_template"], $data[$dataRowIndex]) . "\">";
				}
				else {
					$tableHtml .= "<tr>";
				}

				if($doCheckboxes) {
					$tableHtml .= "<td>" . $this->emitCheckbox($i) . "</td>";
				}

				foreach($data[$dataRowIndex] as $column => $value) {
					if(!($flags & self::DontHideColumns) && in_array($column, $this->m_hiddenColumns)) {
						continue;
					}

					if($doGroups && $hideGroupColumns && in_array($column, $myGroupColumns)) {
						continue;
					}

					$tableHtml .= "<td>" . $this->doColumnCallbacks($value, $column, $data[$dataRowIndex], "text/html") . "</td>";
				}

				$tableHtml .= "</tr>\n";
			}

			// where do the pager controls go?
			if(1 < $maxPage) {
				$indexTop    = 0x01;
				$indexBottom = 0x02;

				if(isset($options["page_index_location"])) {
					switch($options["page_index_location"]) {
						case "top":
							$pageIndexLocation = $indexTop;
							break;

						case "bottom":
							$pageIndexLocation = $indexBottom;
							break;

						default:
							AppLog::warning("invalid argument for \"page_index_location\" option, defaulting to \"both\"", __FILE__, __LINE__, __FUNCTION__);
							// Note: intentional fallthrough

						case "both":
							$pageIndexLocation = $indexTop | $indexBottom;
							break;
					}
				}
				else {
					$pageIndexLocation = $indexTop | $indexBottom;
				}

				if($pageIndexLocation & $indexTop) {
					$pageIndexTopHtml = $this->emitPageIndex(1, $maxPage);
				}
				else {
					$pageIndexTopHtml = "";
				}

				if($pageIndexLocation & $indexBottom) {
					$pageIndexBottomHtml = (!empty($pageIndexTopHtml) ? $pageIndexTopHtml : $this->emitPageIndex(1, $maxPage));
				}
				else {
					$pageIndexBottomHtml = "";
				}
			}
			else {
				$pageIndexTopHtml    = "";
				$pageIndexBottomHtml = "";
			}

			return "$pageIndexTopHtml$tableHtml</tbody></table>$pageIndexBottomHtml\n";
		}

		$noData = $this->noDataContent();

		if($noData instanceof PageElement) {
			return $noData->html();
		}

		return "";
	}

	/**
	 * Escape content for use as the data in a CSV cell.
	 *
	 * @param $content string The content to escape.
	 *
	 * @return string The escaped content.
	 */
	public static function escapeCsvCell(string $content): string {
		return str_replace("\"", "\\\"", $content);
	}

	/**
	 * Output the pager's results as CSV data.
	 *
	 * By default, the DefaultOutputFlags are used with an empty set of options.
	 *
	 * @param $flags int _optional_ The flags to switch on or off features of the output.
	 * @param $options array(string=>mixed) _optional_ CSV-specific output options.
	 *
	 * @return string The CSV output.
	 */
	public function csv(int $flags = self::DefaultOutputFlags, array $options = []): string {
		// m_rowCount is set to the correct value either when setResults() is called or when the cached meta data is
		// read in fromResultsId(). either way, if we have some results, m_rowCount is correct (unless cache is corrupt)
		$max       = $this->m_rowCount;

		if(1 > $max) {
			return "";
		}

		// all data from db uses UTF-8. if charset is null, the data are passed on unmodified
		if(is_array($options)) {
			if(array_key_exists("charset", $options)) {
				// if charset is not recognised by iconv, a PHP Notice is emitted and false is returned; so in such
				// cases, the encode function will return the original string umodified
				$charset = "{$options["charset"]}//TRANSLIT";

				if("ASCII//TRANSLIT" == strtoupper($charset)) {
					// if we don't ensure that the LC_CTYPE is not C or POSIX then transliteration will not work as
					// expected. LC_CTYPE tells the locale system how to interpret character classes. since ASCII is
					// based on US English letters, en_US.utf8 is the appropriate value because it will ensure that
					// character types are interpreted according to US conventions.
					//
					// for our purposes here, this ensures that iconv treats diacritics and so on as expected for ASCII
					// data (e.g. "é" is * transliterated as "e").
					//
					// the locale is returned to its previous value before this function exits
					$oldLocale = setlocale(LC_CTYPE, "0");
					setlocale(LC_CTYPE, "en_US.utf8");
				}

				$charEncode = function($s) use ($charset) {
					$c = iconv("UTF-8", $charset, $s);
					return (is_string($c) ? $c : $s);
				};
			}
		}

		if(!isset($charEncode)) {
			$charEncode = function($s) {
				return $s;
			};
		}

		$resultsId = $this->resultsId();

		$checkedRowsOnly     = ($flags & self::CheckedRowsOnly);
		$noneCheckedMeansAll = ($flags & self::NoCheckedRowsEqualsAllRows);
		$checkedRowCount     = 0;
		$doGroups            = !($flags & self::DontGroup);
		$hideGroupColumns    = $doGroups && !($flags & self::DontHideGroupColumns);

		if(!$checkedRowsOnly) {
			$pageSize   = $this->pageSize();
			$pageNumber = $this->pageNumber();

			if(is_null($pageSize)) {
				$pageSize = self::DefaultPageSize;
			}
			else if(0 == $pageSize) {
				$pageSize = $max;
			}

			if(!$pageNumber) {
				$pageNumber = self::DefaultPageNumber;
			}

			// calculate which rows to display
			$end   = $pageNumber * $pageSize;
			$start = $end - $pageSize;

			if($end > $max) {
				$end = $max;
			}

			if($start > $max) {
				$start = $max;
			}
		}
		else {
			$start           = 0;
			$end             = $max;
			$maxPage         = 1;
			$checkedRowCount = count($this->m_checkedRows);
		}

		$chunkIndex = intval(floor($start / self::ResultsCacheFileChunkSize));

		// we read the first chunk cache file because this is used to check for the existence of grouping columns and to
		// emit column headings
		$data = self::readCachedResults($resultsId, $chunkIndex);

		if(!is_array($data)) {
			AppLog::error("invalid content in results cache", __FILE__, __LINE__, __FUNCTION__);
			return "";
		}

		if($doGroups) {
			// ensure we only have column names in our set to group by that actually exist
			$myGroupColumns = [];

			foreach($this->m_groupColumns as $column) {
				if(array_key_exists($column, $data[$start % self::ResultsCacheFileChunkSize])) {
					$myGroupColumns[] = $column;
				}
			}

			$doGroups = 0 < count($myGroupColumns);

			if($doGroups) {
				// initialise the array of current grouping values for each grouping column
				$currentGroupValues = [];
				$groupLevels        = count($myGroupColumns);

				for($i = 0; $i < $groupLevels; ++$i) {
					$currentGroupValues[$i] = null;
				}
			}
		}

		$content = "";
		$csvRow  = [];

		if(!($flags & self::NoColumnTitles)) {
			foreach($data[$start % self::ResultsCacheFileChunkSize] as $column => $value) {
				if(!($flags & self::DontHideColumns) && in_array($column, $this->m_hiddenColumns)) {
					continue;
				}

				if($doGroups && $hideGroupColumns && in_array($column, $myGroupColumns)) {
					continue;
				}

				if(array_key_exists($column, $this->m_columnTitles)) {
					if(is_string($this->m_columnTitles[$column])) {
						$csvRow[] = $charEncode(self::escapeCsvCell($this->m_columnTitles[$column]));
					}
					else {
						$csvRow[] = $charEncode(self::escapeCsvCell($column));
					}
				}
				else {
					$csvRow[] = $charEncode(self::escapeCsvCell($column));
				}
			}

			$content .= "\"" . implode("\",\"", $csvRow) . "\"\n";
		}

		for($i = $start; $i < $end; ++$i) {
			if($checkedRowsOnly && (!$noneCheckedMeansAll || 0 != $checkedRowCount) && !in_array($i, $this->m_checkedRows)) {
				continue;
			}

			if($i >= ($chunkIndex + 1) * self::ResultsCacheFileChunkSize) {
				++$chunkIndex;
				$data = self::readCachedResults($resultsId, $chunkIndex);

				if(!is_array($data)) {
					AppLog::error("invalid content in results cache (id = " . $this->resultsId() . "; chunk = $chunkIndex)", __FILE__, __LINE__, __FUNCTION__);
					return "";
				}
			}

			// calculate the index into the chunk of data we've currently got loaded
			$dataRowIndex = $i % self::ResultsCacheFileChunkSize;

			if($doGroups) {
				// work out if the value of any of our grouping columns has changed
				for($groupLevel = 0; $groupLevel < $groupLevels; ++$groupLevel) {
					if($data[$dataRowIndex][$this->m_groupColumns[$groupLevel]] != $currentGroupValues[$groupLevel]) {
						break;
					}
				}

				for(; $groupLevel < $groupLevels; ++$groupLevel) {
					$currentGroupValues[$groupLevel] = $data[$dataRowIndex][$this->m_groupColumns[$groupLevel]];
					$content                         .= "\"" . $charEncode($this->doColumnCallbacks($currentGroupValues[$groupLevel], $myGroupColumns[$groupLevel], $data[$dataRowIndex], "text/csv")) . "\"\n";
				}
			}

			$csvRow = [];

			foreach($data[$dataRowIndex] as $column => $value) {
				if(!($flags & self::DontHideColumns) && in_array($column, $this->m_hiddenColumns)) {
					continue;
				}

				if($doGroups && $hideGroupColumns && in_array($column, $myGroupColumns)) {
					continue;
				}

				$csvRow[] = $charEncode($this->doColumnCallbacks($value, $column, $data[$dataRowIndex], "text/csv"));
			}

			$content .= "\"" . implode("\",\"", $csvRow) . "\"\n";
		}

		if(isset($oldLocale)) {
			setlocale(LC_CTYPE, $oldLocale);
		}

		return $content;
	}

	/**
	 * Fetch the pager output in a given MIME type.
	 *
	 * By default, HTML output is produced using the DefaultOutputFlags and no options.
	 *
	 * @param $mimeType string _optional_ The MIME type to generate.
	 * @param $flags int _optional_ Flags controlling the output.
	 * @param $options array _optional_ MIME-type specific output options.
	 *
	 * @return string The MIME type data, or an empty string if the MIME type is not supported.
	 */
	public function pagedData(string $mimeType = "text/html", int $flags = self::DefaultOutputFlags, array $options = []): string {
		if(array_key_exists($mimeType, self::$s_supportedMimeTypes)) {
			$method = self::$s_supportedMimeTypes[$mimeType];
			return $this->$method($flags, $options);
		}

		return "";
	}
}
