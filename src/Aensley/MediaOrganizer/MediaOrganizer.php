<?php

namespace Aensley\MediaOrganizer;

use Aensley\File\Directory;
use Aensley\File\File;

/**
 * Organizes images and videos (or any files) into date-based folders.
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
    private $defaults = [
        // Directory to search for files. Must be set. Ending slash required.
        'source_directory' => '',
        // Set to true to look in all subdirectories of source_directory for files.
        'search_recursive' => false,
        // Array of file extensions to search for. Leave empty to include all files.
        'valid_extensions' => ['jpg', 'jpeg'],
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
        'file_name_masks' => ['YYYYMMDD', 'YYYY-MM-DD'],
        // Whether or not to use the file's modified time if both scan_exif and file_name_masks are disabled or fail.
        'modified_time' => false,
    ];

    /**
     * Profiles array.
     *
     * @var array
     */
    private $profiles = [];

    /**
     * Map of file name mask strings to their corresponding regex patterns.
     *
     * @var array
     */
    private const MASK_PATTERNS = [
        'YYYYMMDD'   => '/(\d{4})(\d{2})(\d{2})/',
        'YYYY-MM-DD' => '/(\d{4})\-(\d{2})\-(\d{2})/',
    ];

    /**
     * Logger object of a class implementing Psr\Log\LoggerInterface.
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;


    /**
     * MediaOrganizer constructor.
     *
     * @param array                         $profiles An associative array of 'profile_name' => options pairs.
     *                                                The options themselves are an associative array
     *                                                overriding $this->defaults.
     * @param \Psr\Log\LoggerInterface|null $logger   Logger instance to handle messages.
     *                                                Defaults to EchoLogger if not provided.
     */
    public function __construct(array $profiles = [], ?\Psr\Log\LoggerInterface $logger = null)
    {
        if (!empty($profiles)) {
            $this->profiles = $profiles;
        }

        $this->setLogger($logger ?? new EchoLogger());
    }


    /**
     * Set the logger object.
     *
     * @param \Psr\Log\LoggerInterface $logger Logger instance to handle messages.
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }


    /**
     * Perform the work of organizing files.
     *
     * @param array[optional] $profiles Directly specify profiles to process.
     *                                  Otherwise, use profiles passed directly to the constructor (preferred).
     */
    public function organize($profiles = [])
    {
        if (empty($profiles)) {
            $profiles = $this->profiles;
        }

        foreach ($profiles as $name => $profile) {
            $this->log('info', 'Processing profile: ' . $name);
            $options = array_merge($this->defaults, $profile);
            if ($this->validOptions($options)) {
                $files = Directory::listFiles(
                    $options['source_directory'],
                    $options['search_recursive'],
                    $options['valid_extensions']
                );
                $count = count($files);
                $succeeded = 0;
                $filesWord = 'file' . ($count === 1 ? '' : 's');
                $this->log('debug', $count . ' ' . $filesWord . ' found.');
                foreach ($files as $file) {
                    $this->log('info', 'Processing: ' . $file);
                    if ($this->processFile($file, $options)) {
                        $succeeded++;
                    }
                }

                $this->log('info', $succeeded . ' of ' . $count . ' ' . $filesWord . ' moved.');
            }
        }
    }


    /**
     * Processes a single file: validates it, determines its date, and moves it.
     *
     * @param string $file    The absolute path of the file to process.
     * @param array  $options The merged profile options.
     *
     * @return bool True if the file was successfully moved. False otherwise.
     */
    private function processFile($file, $options)
    {
        if (!File::isReadable($file)) {
            $this->log('warning', $file . ' is unreadable or not a regular file.');
            return false;
        }

        $date = $this->getDate($file, $options);
        $this->log('debug', $file . ' date ' . $date);
        if (!$date) {
            $this->log('warning', 'Could not determine date of file: ' . $file);
            return false;
        }

        return (bool) $this->moveFile($file, $date, $options);
    }


    /**
     * Checks if profile options are valid and actionable.
     *
     * @param array $options The merged profile options.
     *
     * @return bool
     */
    private function validOptions($options)
    {
        foreach (['source_directory' => 'Source', 'target_directory' => 'Target'] as $key => $label) {
            $dir = $options[$key];
            if (!empty($dir) && !Directory::exists($dir)) {
                Directory::create($dir);
            }

            if (empty($dir) || !Directory::isWritable($dir)) {
                $this->log('error', $label . ' directory does not exist or is unwritable: ' . $dir);
                return false;
            }
        }

        $maskValid = $this->validMask($options['target_mask']);
        $scanValid = $options['scan_exif'] || $options['file_name_masks'] || $options['modified_time'];

        if (!$maskValid) {
            $this->log('error', 'Invalid or empty target mask.');
        } elseif (!$scanValid) {
            $this->log('error', 'No scanning options enabled. Please check the profile options.');
        }

        return $maskValid && $scanValid;
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
        return
            // Must not be empty.
            !empty($mask)
            // Must have at least one of: Y, y, m, or d.
            && (stripos($mask, 'y') !== false || strpos($mask, 'm') !== false || strpos($mask, 'd') !== false)
        ;
    }


    /**
     * Gets the date of the given file using methods enabled in $options.
     *
     * @param string $file    The absolute path of the file to check.
     * @param array  $options The merged profile options.
     *
     * @return string The file's date in YYYY-MM-DD format if found. Empty string if not.
     */
    private function getDate($file, $options)
    {
        $date = '';

        if ($options['scan_exif']) {
            $date = File::exifDateTime($file, 'Y-m-d');
            if ($date) {
                $this->log('debug', 'Date retrieved from EXIF data.');
            }
        }

        if (!$date && $options['file_name_masks']) {
            $date = $this->getFileNameDate($file, $options);
            if ($date) {
                $this->log('debug', 'Date retrieved from file name.');
            }
        }

        if (!$date && $options['modified_time']) {
            $this->log('debug', 'Date retrieved from modified time.');
            $date = File::modifiedDateTime($file, 'Y-m-d');
        }

        return $date;
    }


    /**
     * Gets the file date from file name patterns.
     *
     * @param string $file    The absolute path to the file to check.
     * @param array  $options The merged profile options.
     *
     * @return string The date in YYYY-MM-DD format if found. Empty string if not.
     */
    private function getFileNameDate($file, $options)
    {
        foreach ($options['file_name_masks'] as $fileNameMask) {
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
        $digitMask = self::MASK_PATTERNS[$mask] ?? self::MASK_PATTERNS['YYYYMMDD'];

        if (preg_match($digitMask, $file, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        return '';
    }


    /**
     * Moves the file based on the given options.
     *
     * @param string $file    The absolute path of the source file.
     * @param string $date    The date of the file in YYYY-MM-DD format.
     * @param array  $options The merged profile options.
     *
     * @return string The absolute path to where the file was moved on success. Empty string on failure.
     */
    private function moveFile($file, $date, $options)
    {
        $extension = File::extension($file);
        // Keep file name short enough to allow for up to 9,999 of the same file name without collision.
        $filename = substr(File::name($file), 0, (255 - (strlen($extension) + 6)));
        $directory = $options['target_directory'] . date($options['target_mask'], strtotime($date)) . '/';
        $target = $directory . $filename . '.' . $extension;
        $result = File::move($file, $target, !$options['overwrite']);
        if ($result) {
            $this->log('info', $file . ' moved to ' . $result);
        } else {
            $this->log('warning', 'Could not move ' . $file . ' to ' . $target);
        }

        return $result;
    }


    /**
     * Logs a message.
     *
     * @param string $level The log level of the message.
     * @param string $text  The message to log.
     */
    private function log(string $level, string $text): void
    {
        $this->logger->log($level, $text);
    }
}
