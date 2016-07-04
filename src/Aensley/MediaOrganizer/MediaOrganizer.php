<?php

namespace Aensley\MediaOrganizer;

/**
 * Organizes images and videos (or any files, really) into date-based folders.
 *
 * @package	Aensley/MediaOrganizer
 * @author	Andrew Ensley
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
	private $logLevels = array('none' => 0, 'error' => 1, 'warn' => 2, 'info' => 3, 'debug' => 4);

	/**
	 * The log level.
	 *
	 * @var int
	 */
	private $logLevel = 2;

	/**
	 * Options. Temporary variable set by each profile in $this->organize()
	 *
	 * @var array
	 */
	private $options;


	/**
	 * MediaOrganizer constructor.
	 *
	 * @param array  $profiles An associative array of 'profile_name' => options pairs.
	 *                         The options themselves are an associative array overriding $this->defaults.
	 * @param string $logLevel Set the log level.
	 */
	public function __construct($profiles = array(), $logLevel = 'warn')
	{
		if (!empty($profiles) && is_array($profiles)) {
			$this->profiles = $profiles;
		}

		$this->setLogLevel($logLevel);
	}


	/**
	 * Sets the log level.
	 *
	 * @param string $logLevel The log level.
	 */
	public function setLogLevel($logLevel = '') {
		if (!empty($logLevel) && isset($this->logLevels[$logLevel])) {
			$this->logLevel = $this->logLevels[$logLevel];
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
			$this->info('Processing profile: ' . $name);
			$this->options = array_merge($this->defaults, $options);
			if ($this->validOptions()) {
				$files = $this->listFiles($this->options['source_directory']);
				$count = count($files);
				$this->debug($count . ' file' . ($count === 1 ? '' : 's') . ' found.');
				foreach ($files as $file) {
					$this->info('Processing: ' . $file);
					if ($this->isReadableFile($file)) {
						$date = $this->getDate($file);
						$this->debug($file . ' date ' . $date);
						if ($date) {
							$this->moveFile($file, $date);
						} else {
							$this->warn('Could not determine date of file: ' . $file);
						}
					} else {
						$this->warn($file . ' is unreadable or not a regular file.');
					}
				}
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
			$this->error('Source directory does not exist or is unwritable: ' . $this->options['source_directory']);
			return false;
		}

		if (!$this->directoryExistsAndIsWritable($this->options['target_directory'])) {
			$this->error('Target directory does not exist or is unwritable: ' . $this->options['target_directory']);
			return false;
		}

		if (
			empty($this->options['target_mask'])
			|| (
				stripos($this->options['target_mask'], 'y') === false
				&& strpos($this->options['target_mask'], 'm') === false
				&& strpos($this->options['target_mask'], 'd') === false
			)
		) {
			$this->error('Invalid or empty target mask.');
			return false;
		}

		if (
			!$this->options['scan_exif']
			&& !$this->options['file_name_masks']
			&& !$this->options['modified_time']
		) {
			$this->error('No scanning options enabled. Please check the profile options.');
			return false;
		}

		return true;
	}


	/**
	 * Checks if the given directory exists and is writable.
	 * Optionally tries to create the directory if it doesn't exist (default). Can be disabled.
	 *
	 * @param string         $directory The directory to check.
	 * @param bool[optional] $create    Set to false to disable automatically creating the directory.
	 *
	 * @return bool
	 */
	private function directoryExistsAndIsWritable($directory = '', $create = true)
	{
		return (
			!empty($directory)
			&& (file_exists($directory) || ($create && mkdir($directory, 0775, true)))
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
		if (!empty($directory) && is_dir($directory)) {
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
						$dirFiles = $this->listFiles($file);
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
				$this->debug('Date retrieved from EXIF data.');
				return $date;
			}
		}

		if ($this->options['file_name_masks']) {
			$date = $this->getFileNameDate($file);
			if ($date) {
				$this->debug('Date retrieved from file name.');
				return $date;
			}
		}

		if ($this->options['modified_time']) {
			$this->debug('Date retrieved from modified time.');
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
		$exif = exif_read_data($file, 'EXIF');
		if (!empty($exif['DateTime'])) {
			$dateTime = mb_split(' ', $exif['DateTime']);
			return str_ireplace(':', '-', $dateTime[0]);
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
		switch ($mask)
		{
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
			$this->error('Target directory does not exist or is unwritable: ' . $this->options['source_directory']);
			return '';
		}

		$target = $directory . $filename . '.' . $extension;
		if (!$this->options['overwrite'] && file_exists($target)) {
			$x = 0;
			do {
				$target = $directory . $filename . '_' . $x++ . '.' . $extension;
			} while (file_exists($target) && $x < 10000);

			if (file_exists($target)) {
				$this->warn('Could not find an available target to move ' . $file . ' to (tried 10,000 variations).');
				return '';
			}
		}

		if (rename($file, $target)) {
			$this->info($file . ' moved to ' . $target);
			return $target;
		}

		$this->warn('Could not move ' . $file . ' to ' . $target);
		return '';
	}


	/**
	 * Echoes a single line to output.
	 *
	 * @param string $text The text to echo followed by a line feed ("\n").
	 */
	private function line($text = '')
	{
		echo $text, "\n";
	}


	/**
	 * Handles an error message.
	 *
	 * @param string $text The error message.
	 */
	private function error($text = '')
	{
		if ($this->logLevel >= $this->logLevels['error']) {
			$this->line('ERROR:   ' . $text);
		}
	}


	/**
	 * Handles a warning message.
	 *
	 * @param string $text The warning message.
	 */
	private function warn($text = '')
	{
		if ($this->logLevel >= $this->logLevels['warn']) {
			$this->line('WARNING: ' . $text);
		}
	}


	/**
	 * Handles an info message.
	 *
	 * @param string $text The info message.
	 */
	private function info($text = '')
	{
		if ($this->logLevel >= $this->logLevels['info']) {
			$this->line('INFO:    ' . $text);
		}
	}


	/**
	 * Handles a debug message.
	 *
	 * @param string $text The debug message.
	 */
	private function debug($text = '')
	{
		if ($this->logLevel >= $this->logLevels['debug']) {
			$this->line('DEBUG:   ' . $text);
		}
	}
}
