<?php

namespace Aensley\MediaOrganizer;

/**
 * Organizes images and videos (or any files, really) into date-based folders.
 *
 * @package Aensley/MediaOrganizer
 * @author  Andrew Ensley
 */
class MediaOrganizer
{

	/**
	 * Default profile settings. Override in individual profiles.
	 *
	 * @var array
	 */
	private $defaults = array(
		// Directory to search for files. Must be set. Ending slash required.
		'source_directory' => '',
		// Set to true to look in all subdirectories of source_directory for files.
		'search_recursive' => false,
		// Array of file extensions to search for. Leave empty to include all files.
		'valid_extensions' => array('jpg', 'jpeg'),
		// Parent directory to place moved files in. Must be set. Ending slash required.
		'target_directory' => '',
		// Directory structure to use for target. Must be set.
		// Y = 4-digit year, y = 2-digit year, m = 2-digit month, d = 2-digit day
		// Anything from http://php.net/date will work, except time-based options as they will not be consistent.
		'target_mask' => 'Y/Y-m-d',
		// true = overwrite same files that already exist in target.
		// false = add incrementing counter to same file names until there's no collision.
		'overwrite' => false,
		// Scan exif data for date? Only valid for JPEG or TIFF image files.
		'scan_exif' => true,
		// Pattern to search for in file names for date. Set to false to disable filename logic.
		// Only runs if scan_exif is disabled or fails.
		// Y = year digit, M = month digit, D = day digit. All are replaced with digits for regex search.
		'file_name_masks' => array('YYYYMMDD', 'YYYY-MM-DD'),
		// Whether or not to use the file's modified time if both scan_exif and file_name_masks are disabled or fail.
		'modified_time' => false,
	);

	/**
	 * Profiles array.
	 *
	 * @var array
	 */
	private $profiles = array();

	/**
	 * Valid log level names and their corresponding values.
	 *
	 * @var array
	 */
	private $logLevels = array('none' => 1000, 'error' => 400, 'warning' => 300, 'info' => 200, 'debug' => 100);

	/**
	 * The log level.
	 *
	 * @var int
	 */
	private $logLevel = 2;

	/**
	 * Logger object of a class implementing Psr\Log\LoggerInterface.
	 *
	 * @var object
	 */
	private $logger;

	/**
	 * Options. Temporary variable set by each profile in $this->organize()
	 *
	 * @var array
	 */
	private $options;


	/**
	 * MediaOrganizer constructor.
	 *
	 * @param array $profiles An associative array of 'profile_name' => options pairs.
	 *                        The options themselves are an associative array overriding $this->defaults.
	 * @param mixed $logger   Set the logger object (implementing Psr\Log\LoggerInterface) to handle messages.
	 *                        Otherwise, set to a valid log level string to use internal simple logger.
	 */
	public function __construct($profiles = array(), $logger = 'warning')
	{
		if (!empty($profiles) && is_array($profiles)) {
			$this->profiles = $profiles;
		}

		$this->setLogger($logger);
	}


	/**
	 * Set the logger object.
	 * The $logger is required and must be an object of a class implementing Psr\Log\LoggerInterface.
	 *
	 * @param mixed $logger Object of a class implementing Psr\Log\LoggerInterface to handle messages.
	 *                      Otherwise, a valid log level string to use internal simple logger.
	 */
	public function setLogger($logger)
	{
		if (is_object($logger)) {
			$this->logger = $logger;
			return;
		}

		if (is_string($logger) && isset($this->logLevels[$logger])) {
			$this->logLevel = $this->logLevels[$logger];
		}
	}


	/**
	 * Perform the work of organizing files.
	 *
	 * @param array[optional] $profiles Directly specify profiles to process.
	 *                                  Otherwise, use profiles passed directly to the constructor (preferred).
	 */
	public function organize($profiles = array())
	{
		if (empty($profiles)) {
			$profiles = $this->profiles;
		}

		foreach ($profiles as $name => $options) {
			$this->log('info', 'Processing profile: ' . $name);
			$this->options = array_merge($this->defaults, $options);
			if ($this->validOptions()) {
				$files = $this->listFiles($this->options['source_directory']);
				$count = count($files);
				$succeeded = 0;
				$this->log('debug', $count . ' file' . ($count === 1 ? '' : 's') . ' found.');
				foreach ($files as $file) {
					$this->log('info', 'Processing: ' . $file);
					if ($this->isReadableFile($file)) {
						$date = $this->getDate($file);
						$this->log('debug', $file . ' date ' . $date);
						if ($date) {
							if ($this->moveFile($file, $date)) {
								$succeeded++;
							}

							continue;
						}

						$this->log('warning', 'Could not determine date of file: ' . $file);
						continue;
					}

					$this->log('warning', $file . ' is unreadable or not a regular file.');
				}

				$this->log('info', $succeeded . ' of ' . $count . ' file' . ($count === 1 ? '' : 's') . ' moved.');
			}
		}
	}


	/**
	 * Checks if profile options are valid and actionable.
	 *
	 * @param array $options Associative array of profile options.
	 *
	 * @return bool
	 */
	private function validOptions()
	{
		if (!$this->directoryExistsAndIsWritable($this->options['source_directory'])) {
			$this->log(
				'error',
				'Source directory does not exist or is unwritable: ' . $this->options['source_directory']
			);
			return false;
		}

		if (!$this->directoryExistsAndIsWritable($this->options['target_directory'])) {
			$this->log(
				'error',
				'Target directory does not exist or is unwritable: ' . $this->options['target_directory']
			);
			return false;
		}

		if (!$this->validMask($this->options['target_mask'])) {
			$this->log('error', 'Invalid or empty target mask.');
			return false;
		}

		if (!$this->atLeastOneScanOption()) {
			$this->log('error', 'No scanning options enabled. Please check the profile options.');
			return false;
		}

		return true;
	}


	/**
	 * Checks if there is at least one scan option enabled.
	 *
	 * @return bool True if at least one scan option is enabled. False if not.
	 */
	private function atLeastOneScanOption()
	{
		return ($this->options['scan_exif'] || $this->options['file_name_masks'] || $this->options['modified_time']);
	}


	/**
	 * Checks if the given target mask is valid.
	 *
	 * @param string $mask The target mask to check.
	 *
	 * @return bool True if valid. False if not.
	 */
	private function validMask($mask = '')
	{
		return (
			// Must not be empty.
			!empty($mask)
			// Must have at least one of: Y, y, m, or d.
			&& (stripos($mask, 'y') !== false || strpos($mask, 'm') !== false || strpos($mask, 'd') !== false)
		);
	}


	/**
	 * Checks if the given directory exists and is writable.
	 * Optionally tries to create the directory if it doesn't exist (default). Can be disabled.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	private function directoryExistsAndIsWritable($directory = '')
	{
		return (
			!empty($directory)
			&& (file_exists($directory) || mkdir($directory, 0775, true))
			&& is_dir($directory)
			&& is_writable($directory)
		);
	}


	/**
	 * Scans a directory for files matching profile options. Returns what is found as an array of absolute file paths.
	 *
	 * @param string $directory The directory to scan. Must be an absolute path with a trailing slash.
	 *
	 * @return array
	 */
	private function listFiles($directory)
	{
		$returnFiles = array();
		$files = scandir($directory);
		foreach ($files as $file) {
			if (in_array($file, array('.', '..'))) {
				// Skip virtual paths.
				continue;
			}

			$file = $directory . $file;
			if (is_link($file)) {
				// Do not follow links.
				continue;
			} elseif (is_dir($file)) {
				if ($this->options['search_recursive']) {
					// Recursion.
					$dirFiles = $this->listFiles($file . '/');
					// Add files found in sub-directory.
					$returnFiles = array_merge($returnFiles, $dirFiles);
				}

				// Do not add directories.
				continue;
			}

			// We know it's a regular file at this point.
			if (!empty($this->options['valid_extensions'])) {
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				if (!in_array($ext, $this->options['valid_extensions'])) {
					// Invalid file extension.
					continue;
				}
			}

			// Passed all the exclusion tests. Add it to the list.
			$returnFiles[] = $file;
		}

		// Return what we found.
		return $returnFiles;
	}


	/**
	 * Gets the date of the given file using methods enabled in $this->options.
	 *
	 * @param string $file The absolute path of the file to check.
	 *
	 * @return string The file's date in YYYY-MM-DD format if found. Empty string if not.
	 */
	private function getDate($file = '')
	{
		if ($this->options['scan_exif']) {
			$date = $this->getExifDate($file);
			if ($date) {
				$this->log('debug', 'Date retrieved from EXIF data.');
				return $date;
			}
		}

		if ($this->options['file_name_masks']) {
			$date = $this->getFileNameDate($file);
			if ($date) {
				$this->log('debug', 'Date retrieved from file name.');
				return $date;
			}
		}

		if ($this->options['modified_time']) {
			$this->log('debug', 'Date retrieved from modified time.');
			$date = $this->getModifiedDate($file);
			if ($date) {
				return $date;
			}
		}

		return '';
	}


	/**
	 * Gets the file date from EXIF data.
	 *
	 * @param string $file The absolute path to the file to check.
	 *
	 * @return string The date in YYYY-MM-DD format if found. Empty string if not.
	 */
	private function getExifDate($file = '')
	{
		$exif = @exif_read_data($file, 'EXIF');
		// Fields in which to find the date, in order of preference.
		$exifFields = array('DateTime', 'DateTimeOriginal', 'DateTimeDigitized');
		if (!empty($exif)) {
			foreach ($exifFields as $exifField) {
				if (!empty($exif[$exifField])) {
					$dateTime = mb_split(' ', $exif[$exifField]);
					$date = trim($dateTime[0]);
					if (preg_match('/^\d{4}\:\d{2}\:\d{2}$/', $date)) {
						return str_ireplace(':', '-', $date);
					}
				}
			}
		}

		return '';
	}


	/**
	 * Gets the file date from file name patterns.
	 *
	 * @param string $file The absolute path to the file to check.
	 *
	 * @return string The date in YYYY-MM-DD format if found. Empty string if not.
	 */
	private function getFileNameDate($file = '')
	{
		foreach ($this->options['file_name_masks'] as $fileNameMask) {
			$match = $this->fileMask(pathinfo($file, PATHINFO_FILENAME), $fileNameMask);
			if ($match) {
				return $match;
			}
		}

		return '';
	}


	/**
	 * Matches a file's base name against a date-format mask and returns the matching part in YYYY-MM-DD format.
	 *
	 * @param string $file The basename (without extension or directory) of the file to check.
	 * @param string $mask The mask to check against the file name.
	 *
	 * @return string The date in YYYY-MM-DD format if found. Empty string if not.
	 */
	private function fileMask($file = '', $mask = '')
	{
		switch ($mask) {
			case 'YYYY-MM-DD':
				$digitMask = '/(\d{4})\-(\d{2})\-(\d{2})/';
				break;
			case 'YYYYMMDD':
			default:
				$digitMask = '/(\d{4})(\d{2})(\d{2})/';
				break;
		}

		if (preg_match($digitMask, $file, $matches) === 1) {
			return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
		}

		return '';
	}


	/**
	 * Gets the file date from file name patterns.
	 *
	 * @param string $file The absolute path to the file to check.
	 *
	 * @return string The date in YYYY-MM-DD format if found. Empty string if not.
	 */
	private function getModifiedDate($file = '')
	{
		$time = filemtime($file);
		if ($time) {
			return date('Y-m-d', $time);
		}

		return '';
	}


	/**
	 * Checks if the given string represents a file that exists, is a file (not a link or directory), and is readable.
	 *
	 * @param string $file The absolute path to the file to check.
	 *
	 * @return bool True if the file exists, is a file, and is readable. False if any are false.
	 */
	private function isReadableFile($file = '')
	{
		return (!empty($file) && is_file($file) && is_readable($file));
	}


	/**
	 * Moves the file based on parameters in $this->options.
	 *
	 * @param string $file The absolute path of the source file.
	 * @param string $date The date of the file in YYYY-MM-DD format.
	 *
	 * @return string The absolute path to where the file was moved on success. Empty string on failure.
	 */
	private function moveFile($file = '', $date = '')
	{
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		// Keep file name short enough to allow for up to 9,999 of the same file name without collision.
		$filename = substr(pathinfo($file, PATHINFO_FILENAME), 0, (255 - (strlen($extension) + 6)));

		$directory = $this->options['target_directory'] . date($this->options['target_mask'], strtotime($date)) . '/';
		if (!$this->directoryExistsAndIsWritable($directory)) {
			$this->log(
				'error',
				'Target directory does not exist or is unwritable: ' . $this->options['source_directory']
			);
			return '';
		}

		$target = $directory . $filename . '.' . $extension;
		if (!$this->options['overwrite'] && file_exists($target)) {
			$counter = 0;
			do {
				$target = $directory . $filename . '_' . $counter++ . '.' . $extension;
			} while (file_exists($target) && $counter < 10000);

			if (file_exists($target)) {
				$this->log(
					'warning',
					'Could not find an available target to move ' . $file . ' to (tried 10,000 variations).'
				);
				return '';
			}
		}

		if (rename($file, $target)) {
			$this->log('info', $file . ' moved to ' . $target);
			return $target;
		}

		$this->log('warning', 'Could not move ' . $file . ' to ' . $target);
		return '';
	}


	/**
	 * Logs a message.
	 *
	 * @param string $level The log level of the message.
	 * @param string $text  The message to log.
	 */
	private function log($level = 'info', $text = '')
	{
		if (isset($this->logger)) {
			$this->logger->log($level, $text);
			return;
		}

		if ($this->logLevel <= $this->logLevels[$level]) {
			echo strtoupper($level), ': ', $text, "\n";
		}
	}
}
