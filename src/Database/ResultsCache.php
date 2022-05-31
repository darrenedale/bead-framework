<?php

/**
 * Defines the ResultsCache class.
 *
 * @file ResultsCache.php
 * @author Darren Edale
 * @version 0.9.2
 * @package bead-framework
 * @version 0.9.2
 */

namespace Equit\Database;

use ArrayAccess;
use Countable;
use DirectoryIterator;
use Equit\Application;
use Equit\AppLog;
use Iterator;
use LogicException;
use OutOfBoundsException;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Cache a set of database results.
 *
 * The primary use-case for this class is to cache potentially large result sets so that they can subsequently be
 * accessed quickly (e.g. when paging) without having to re-query the database.
 */
class ResultsCache implements Iterator, ArrayAccess, Countable
{
	/**
	 * @var int The age that constitutes an expired cache file.
	 *
	 * During a purge, any file in the cache that was last accessed more than this number of minutes ago is considered
	 * to have expired.
	 */
	public const DefaultCacheExpiryTimeout = 30;

	/** @var int How many rows of results data to place in each results cache chunk file. */
	protected const ResultsCacheFileChunkSize = 5000;

	/** @var PDOStatement|null The results being paged. */
	private ?PDOStatement $m_results = null;

	/** @var string The UID for the results being paged. */
	private string $m_id;

	/** @var int The number of rows in results being paged. */
	private int $m_rowCount = 0;

	/** @var int|null The index of the current chunk in memory when performing array access. */
	private ?int $m_arrayAccessChunkIndex = null;

	/** @var array|null The current chunk in memory for array access. */
	private ?array $m_arrayAccessChunk = [];

	/** @var int The current row when iterating the results. */
	private int $m_iteratorCurrentRow = 0;

	/** @var int|null The index of the current chunk in memory when iterating the results. */
	private ?int $m_iteratorChunkIndex = null;

	/** @var array|null The current chunk in memory when iterating.  */
	private ?array $m_iteratorChunk = [];

	/**
	 * Create a new results pager.
	 *
	 * The constructor is internal only, use either create() or fetch() to create your cache objects.
	 *
	 * @param PDOStatement|null $results `optional` The results to page.
	 * @param string $id `optional` The ID for the results cache. If empty a unique ID will be generated.
	 */
	protected function __construct(?PDOStatement $results = null, string $id = "")
	{
		if (empty($id)) {
			$id = self::generateUid();
		}

		$this->m_id = $id;

		if (isset($results)) {
			$this->setResults($results);
		}
	}

	/**
	 * Create a new cached result set from a PDO statement.
	 *
	 * @param PDOStatement $results The statement with the results.
	 * @param string $id The optional ID for the results. If not specifed, or empty, a unique ID will be chosen.
	 *
	 * @return ResultsCache
	 */
	public static function create(PDOStatement $results, string $id = ""): ResultsCache
	{
		return new static($results, $id);
	}

	/**
	 * Retrieve a cached set of results.
	 *
	 * This method rebuilds and returns a ResultsCache object from the cache.
	 *
	 * @param $resultsId string The ID of the pager to fetch.
	 *
	 * @return ResultsCache|null The reconstituted cached pager, or `null` if the cache object could not be rebuilt
	 * (e.g. if the ID provided is not valid).
	 */
	public static function fetch(string $id): ResultsCache
	{
		$path = self::cacheFilePath($id);

		if(!is_readable("{$path}.0000.results")) {
			throw new RuntimeException("Cached results with ID \"{$id}\" (file \"{$path}.0000.results\") not found", __FILE__, __LINE__, __FUNCTION__);
		}

		$metaData = self::readCachedMetaData($id);

		if ($metaData["id"] !== $id) {
			throw new RuntimeException("Cached results meta-data file for results with ID \"{$id}\" does not contain the correct ID.");
		}

		$ret = new static(null, $id);
		$ret->m_id = $id;
		$ret->m_rowCount = $metaData["row-count"];
		return $ret;
	}

	/**
	 * Fetch the path to the directory where cache files are stored.
	 *
	 * If the cache directory does not exist, can't be created, or is not writable, an fatal application error is
	 * generated and the script terminates immediately. `ResultsCache` objects are unable to function without a working
	 * cache.
	 *
	 * @return string The cache file directory.
	 */
	public static function cacheDirectory(): string
	{
		static $s_dir = null;

		if (is_null($s_dir)) {
			$s_dir = Application::instance()->rootDir() . "/" . Application::instance()->config("app.cache.dir", "cache") . "/bead-resultscache";

			if(!file_exists($s_dir)) {
				@mkdir($s_dir, 0770, true);
			}

			if(!file_exists($s_dir) || !is_dir($s_dir) || !is_writable($s_dir)) {
				throw new RuntimeException("Can't find, create or write to results cache directory \"{$s_dir}\"");
			}
		}

		return $s_dir;
	}

	/**
	 * Purge expired entries from the results cache.
	 *
	 * A cache entry is considered expired when it hasn't been accessed for a given number of minutes, which defaults to
	 * the class constant `DefaultCacheExpiryTimeout`.
	 *
	 * When called, all the metadata files in the cache directory have their last access time checked against the expiry
	 * duration. If the file was last accessed more than the specified number of minutes ago, it is assumed to be no
	 * longer needed and the results are purged from the cache.
	 *
	 * In your app you should make sure this method is called on a schedule to ensure the cache does not grow
	 * uncontrolled. A cron job or request lottery are possible solutions.
	 *
	 * @param int $expiry The number of minutes of lack of use for a results cache to be considered expired.
	 */
	public static function purgeCache(int $expiry = self::DefaultCacheExpiryTimeout): void
	{
		assert (0 < $expiry, "Invalid expiry duration {$expiry}.");
		$thresholdTime = time() - ($expiry * 60);

		foreach (new DirectoryIterator(self::cacheDirectory()) as $file) {
			if ($file->isDot()) {
				continue;
			}

			if (!$file->isFile()) {
				AppLog::error("Unexpected entry in ResultsCache cache directory: '{$file->getPathname()}'", __FILE__, __LINE__, __FUNCTION__);
				continue;
			}

			$filePath = $file->getPathname();

			// only expire entries when the metadata file has expired - other files might not be touched even while the
			// user is still using the results. the meta data cache file is touched every time they're used
			if (!preg_match("/resultscache-([0-9a-f]{32})\\.meta$/", $filePath, $captures)) {
				continue;
			}

			$fileTime = $file->getATime();

			if (false === $fileTime) {
				AppLog::error("Could not determine last access time for ResultsCache file: '{$file->getPathname()}'", __FILE__, __LINE__, __FUNCTION__);
				continue;
			}

			if ($fileTime <= $thresholdTime) {
				static::removeCachedResults($captures[1]);
			}
		}
	}

	/**
	 * Helper to remove the cache files for a cached result set with a given id.
	 *
	 * @param string $resultsId The ID of the results to remove from the cache.
	 */
	protected static function removeCachedResults(string $id): void
	{
		foreach (glob(static::cacheDirectory() . "/resultscache-{$id}.*", GLOB_NOSORT) as $cacheFilePath) {
			if (!@unlink($cacheFilePath)) {
				AppLog::error("Could not delete ResultsCache cache file: '$cacheFilePath'", __FILE__, __LINE__, __FUNCTION__);
			}
		}
	}

	/**
	 * Helper to generate a unique ID for a cache object.
	 *
	 * @return string The unique ID.
	 * @throws \Exception If PHP's random byte generation is not functioning on the current platform.
	 */
	protected static function generateUid(): string
	{
		return randomString(32);
	}

	/**
	 * Fetch the ID of the results.
	 *
	 * @return string The ID.
	 */
	public final function id(): string
	{
		return $this->m_id;
	}

	/**
	 * Fetch the cache's results.
	 *
	 * This is only valid when the object is first built from the results of a database query. It is only provided as a
	 * customisation point for subclasses, if required.
	 *
	 * @return PDOStatement|null The results, or `null` if no results have been set (i.e. the object has been
	 * reconstituted from the cache files).
	 */
	protected function results(): ?PDOStatement
	{
		return $this->m_results;
	}

	/**
	 * Set the results to cache.
	 *
	 * This is only valid when the first creating a cache object from the results of a database query. It is only
	 * provided as a customisation point for subclasses, if required.
	 *
	 * @param $results PDOStatement|null The results to display.
	 */
	protected function setResults(PDOStatement $results): void
	{
		$this->m_results = $results;
		$this->m_rowCount = 0;
		$this->cacheResults();
	}

	/**
	 * Fetch the number of rows in the cached results.
	 *
	 * @return int The row count. This will be 0 if no results have been set.
	 */
	public function rowCount(): int
	{
		return $this->m_rowCount;
	}

	/**
	 * Cache the results, if they are not cached already.
	 */
	protected function cacheResults(): void
	{
		if (empty($this->id())) {
			throw new \Exception("Can't cache results without an ID.");
		}

		$path = self::cacheFilePath($this->id());

		if(file_exists("{$path}.0000.results")) {
			throw new \Exception("Can't cache results ID {$this->id} - ID is already in use.");
		}

		// ensure cache file exists even if there are no results to write
		@touch("{$path}.0000.results");
		$results = $this->results();
		$results->setFetchMode(PDO::FETCH_ASSOC);

		if ($results instanceof PDOStatement) {
			$rowIndex = 0;
			$chunkIndex = 0;
			$chunkData = [];

			foreach ($results as $row) {
				$chunkData[] = $row;
				++$rowIndex;

				if (0 == $rowIndex % self::ResultsCacheFileChunkSize) {
					$this->writeCachedResultsData($chunkData, $chunkIndex);
					++$chunkIndex;
					$chunkData = [];
				}
			}

			// write any partial chunk left over after the end of the last chunk that was written to disk
			if(0 != $rowIndex % self::ResultsCacheFileChunkSize) {
				$this->writeCachedResultsData($chunkData, $chunkIndex);
			}

			$this->m_rowCount = $rowIndex;
			$this->cacheMetaData();
		}
	}

	/**
	 * Write the results to the cache files.
	 *
	 * @param $data array[string] the data to cache.
	 * @param $chunkIndex int The index number of the cache file chunk to write.
	 *
	 * Because the caches data could be huge, it is broken into chunks of 5000 (by default - see const
	 * `ResultsCacheFileChunkSize`) rows. This means that the pager can read sections of data from the cache as required
	 * rather than having to load all the data from the cache. In turn, this means that extremely large result sets are
	 * less likely to cause the PHP process to exhaust its `memory_limit` ini setting trying to store all the data for
	 * all rows in an array.
	 */
	private function writeCachedResultsData(array $data, int $chunkIndex): void
	{
		if(empty($this->id())) {
			return;
		}

		$path = self::cacheFilePath($this->id(), sprintf("%04d", $chunkIndex) . ".results");

		if(false === file_put_contents($path, serialize($data))) {
			AppLog::error("failed to write file to cache search results (data file, id = {$this->id()}, chunk = {$chunkIndex})", __FILE__, __LINE__, __FUNCTION__);
			return;
		}
	}

	/**
	 * Write the result set meta data to its cache file.
	 *
	 * The meta data written is:
	 * - the name of the result set
	 * - the number of rows in the result set
	 */
	protected function cacheMetaData(): void
	{
		$path = self::cacheFilePath($this->id(), "meta");

		if(false === file_put_contents($path, json_encode(["id" => $this->id(), "row-count" => $this->rowCount()]))) {
			AppLog::error("failed to write file to cache pager meta data (id = $resultsId)", __FILE__, __LINE__, __FUNCTION__);
			return;
		}
	}

	/**
	 * Read the contents of a cache file.
	 *
	 * This method will look for the provided file in the cache and return its contents if it exists.
	 *
	 * @param $fileName string The name of the cache file to read.
	 *
	 * @return string The contents of the cache file, or _null_ if the file could not be found or read.
	 * @throws RuntimeException if the cache file cannot be read.
	 */
	private static function readCacheFile(string $fileName): string
	{
		$cachePath = self::cacheFilePath($fileName);

		if(!is_readable($cachePath) || !is_file($cachePath)) {
			throw new RuntimeException("Invalid or unreadable cache file \"$cachePath\"", __FILE__, __LINE__, __FUNCTION__);
		}

		// ensure that the file atime is updated even if the fs is mounted noatime
		@touch($cachePath);
		return file_get_contents($cachePath);
	}

	/**
	 * Read the results from a cache file.
	 *
	 * Chunks are indexed from 0. Currently, each chunk contains 5000 rows, and chunks are ordered in the order in which
	 * the rows appear in the result set. See the class constant `ResultsCacheFileChunkSize` for the actual chunk size
	 * (in case this documentation has not been updated).
	 *
	 * @param $id string The ID of the results to read from the cache.
	 * @param $chunkIndex int The index of the results cache chunk file to read.
	 *
	 * @return array<string, mixed> The contents of the cache file.
	 */
	protected static function readCachedResults(string $id, int $chunkIndex): ?array
	{
		$content = self::readCacheFile("{$id}." . sprintf("%04d", $chunkIndex) . ".results");

		// cache file can be an empty file (i.e. not a serialised empty array) if results were empty when cached
		if (empty($content)) {
			return [];
		}

		return @unserialize($content);
	}

	/**
	 * Read the pager metadata from a cache file.
	 *
	 * The metadata cache file for the pager is read and parsed. The metadata is returned as an array containing the
	 * following elements:
	 * - id `string` The cache ID for the results.
	 * - rowCount `int` The number of rows.
	 *
	 * @param $id string The ID of the results to read from the cache.
	 *
	 * @return array The metadata read from the cache file.
	 * @throws RuntimeException if the metadata cache file cannot be read.
	 * @throws \JsonException if the file's content is not valid JSON.
	 */
	protected static function readCachedMetaData(string $id): array
	{
		$content = self::readCacheFile("{$id}.meta");

		if(!isset($content)) {
			throw new RuntimeException("Cache meta-data file {$id}.meta could not be read.");
		}

		return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
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
	protected static function cacheFilePath(string $id, string $suffix = ""): string
	{
		return self::cacheDirectory() . "/resultscache-{$id}" . (empty($suffix) ? "" : ".{$suffix}");
	}

	/*
	 * Iterator interface implementation.
	 */

	/**
	 * Iterator interface method to fetch the current item.
	 *
	 * @return array|null
	 */
	public function current(): ?array
	{
		$chunkIndex = intval(floor($this->m_iteratorCurrentRow / self::ResultsCacheFileChunkSize));

		if ($chunkIndex !== $this->m_iteratorChunkIndex) {
			$this->m_iteratorChunk = self::readCachedResults($this->id(), $chunkIndex);
			$this->m_iteratorChunkIndex = $chunkIndex;
		}

		if(!is_array($this->m_iteratorChunk)) {
			AppLog::error("invalid content in results cache", __FILE__, __LINE__, __FUNCTION__);
			return null;
		}

		return $this->m_iteratorChunk[$this->m_iteratorCurrentRow - (self::ResultsCacheFileChunkSize * $this->m_iteratorChunkIndex)];
	}

	/**
	 * Iterator interface method to fetch the key for the current item.
	 *
	 * @return int
	 */
	public function key(): int
	{
		return $this->m_iteratorCurrentRow;
	}

	/**
	 * Iterator interface method to advance to the next item.
	 */
	public function next(): void
	{
		++$this->m_iteratorCurrentRow;
	}

	/**
	 * Iterator interface method to check the current item is valid.
	 *
	 * @return bool `true` if the iterator hasn't exhausted the result set, `false` otherwise.
	 */
	public function valid(): bool
	{
		return $this->m_iteratorCurrentRow < $this->rowCount();
	}

	/**
	 * Iterator interface method to restart iteration.
	 */
	public function rewind(): void
	{
		$this->m_iteratorCurrentRow = 0;
	}

	/*
	 * ArrayAccess interface implementation.
	 */
	/**
	 * Check an array index is valid.
	 *
	 * Only integer access is valid, 0 <= index < rowCount()
	 *
	 * @param int $offset The offset.
	 *
	 * @return bool `true` if the index is in bounds, `false` otherwise.
	 */
	public function offsetExists($offset)
	{
		return is_int($offset) && 0 <= $offset && $this->rowCount() > $offset;
	}

	/**
	 * @throws LogicException - ResultsCache instances are readon-only.
	 */
	public function offsetSet($offset, $value)
	{
		throw new LogicException("ResultsCache instances are not writable.");
	}

	/**
	 * @throws LogicException - ResultsCache instances are readon-only.
	 */
	public function offsetUnset($offset)
	{
		throw new LogicException("ResultsCache instances are not writable.");
	}

	/**
	 * Fetch the row of data at a given index.
	 *
	 * @param int $offset The index.
	 *
	 * @return array
	 * @throws OutOfBoundsException if the offset is not valid
	 * @throws RuntimeException if the data could not be loaded from the cache
	 */
	public function offsetGet($offset): ?array
	{
		if (!$this->offsetExists($offset)) {
			throw new OutOfBoundsException("The offset {$offset} is out of bounds.");
		}

		$chunkIndex = intval(floor($offset / self::ResultsCacheFileChunkSize));

		if ($chunkIndex !== $this->m_arrayAccessChunkIndex) {
			$this->m_arrayAccessChunk = self::readCachedResults($this->id(), $chunkIndex);
			$this->m_arrayAccessChunkIndex = $chunkIndex;
		}

		if(!is_array($this->m_iteratorChunk)) {
			throw new RuntimeException("The row at index {$offset} could not be fetched - potential cache corruption.");
		}

		return $this->m_arrayAccessChunk[$offset - (self::ResultsCacheFileChunkSize * $this->m_arrayAccessChunkIndex)];
	}

	/*
	 * Implementation of the Countable interface
	 */

	/**
	 * Count the number of items in the cache.
	 *
	 * @return int The row count.
	 */
	public function count(): int
	{
		return $this->rowCount();
	}
}
