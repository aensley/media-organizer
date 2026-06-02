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
        // REQUIRED: Directory to search for files. Ending slash required.
        'source_directory' => '',
        // Set to true to look in all subdirectories of source_directory for files.
        'search_recursive' => false,
        // Array of file extensions to search for. Set to an empty array to include all files.
        'valid_extensions' => ['jpg', 'jpeg'],
        // REQUIRED: Destination directory for organized files. Ending slash required.
        'target_directory' => '',
        // REQUIRED: Directory structure to use for target.
        // Y = 4-digit year, y = 2-digit year, m = 2-digit month, d = 2-digit day
        // Anything supported by http://php.net/date will work, except time-based options which will not be consistent.
        'target_mask' => 'Y/Y-m-d',
        // Whether to overwrite destination files.
        // true = overwrite same files that already exist in target.
        // false = add incrementing counter to identical file names to avoid collisions.
        'overwrite' => false,
        // DATE RETRIEVAL METHOD: Scan EXIF data for file date.
        // Supports image files (JPG, TIFF, HEIC, WEBP, etc.).
        'scan_exif' => true,
        // DATE RETRIEVAL METHOD: Scan XMP metadata for file date.
        // Supports any file format with an embedded XMP packet, typically images (JPG, PNG, GIF, SVG, PDF, etc.),
        // including files edited in Adobe Lightroom or Photoshop where EXIF may be absent.
        'scan_xmp' => false,
        // DATE RETRIEVAL METHOD: Scan metadata via getid3 for file date.
        // Supports video files (MP4, MOV, MKV, AVI, etc.) and
        // audio files (MP3, FLAC, OGG, etc.).
        'scan_id3' => false,
        // DATE RETRIEVAL METHOD: Patterns to search for in file names for date. Set to false to disable filename logic.
        // Y = year digit, M = month digit, D = day digit. All are replaced with digits for regex search.
        'file_name_masks' => ['YYYY-MM-DD', 'YYYYMMDD'],
        // DATE RETRIEVAL METHOD: Whether or not to use the file's modified time.
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
            $this->processProfile($name, $profile);
        }
    }


    /**
     * Processes a single profile: validates options, lists files, and moves each one.
     *
     * @param string $name    The profile name, used for logging.
     * @param array  $profile The raw profile options (merged with defaults internally).
     */
    private function processProfile(string $name, array $profile): void
    {
        $this->logger->log('info', 'Processing profile: ' . $name);
        $options = array_merge($this->defaults, $profile);
        if (!$this->validOptions($options)) {
            return;
        }

        $files     = Directory::listFiles(
            $options['source_directory'],
            $options['search_recursive'],
            $options['valid_extensions']
        );
        $count     = count($files);
        $succeeded = 0;
        $filesWord = 'file' . ($count === 1 ? '' : 's');
        $this->logger->log('debug', $count . ' ' . $filesWord . ' found.');
        foreach ($files as $file) {
            $this->logger->log('info', 'Processing: ' . $file);
            if ($this->processFile($file, $options)) {
                $succeeded++;
            }
        }

        $this->logger->log('info', $succeeded . ' of ' . $count . ' ' . $filesWord . ' moved.');
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
            $this->logger->log('warning', $file . ' is unreadable or not a regular file.');
            return false;
        }

        $date = $this->getDate($file, $options);
        $this->logger->log('debug', $file . ' date ' . $date);
        if (!$date) {
            $this->logger->log('warning', 'Could not determine date of file: ' . $file);
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
    private function validOptions(array &$options)
    {
        foreach (['source_directory' => 'Source', 'target_directory' => 'Target'] as $key => $label) {
            $dir = $options[$key];
            if (!empty($dir) && !Directory::exists($dir)) {
                Directory::create($dir);
            }

            if (empty($dir) || !Directory::isWritable($dir)) {
                $this->logger->log('error', $label . ' directory does not exist or is unwritable: ' . $dir);
                return false;
            }
        }

        if ($options['scan_id3'] && !GetId3DateExtractor::isAvailable()) {
            $this->logger->log('error', 'getID3 class not found. Install james-heinrich/getid3 to use scan_id3.');
            $options['scan_id3'] = false;
        }

        $mask = $options['target_mask'];
        $maskValid = !empty($mask)
            && (stripos($mask, 'y') !== false || strpos($mask, 'm') !== false || strpos($mask, 'd') !== false);
        $scanOptions = ['scan_exif', 'scan_xmp', 'scan_id3', 'file_name_masks', 'modified_time'];
        $scanValid = (bool) array_filter($scanOptions, fn ($key) => $options[$key]);

        if (!$maskValid) {
            $this->logger->log('error', 'Invalid or empty target mask.');
        } elseif (!$scanValid) {
            $this->logger->log('error', 'No scanning options enabled. Please check the profile options.');
        }

        return $maskValid && $scanValid;
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
                $this->logger->log('debug', 'Date retrieved from EXIF data.');
            }
        }

        if (!$date && $options['scan_xmp']) {
            $date = (new XmpDateExtractor())->getDate($file);
            if ($date) {
                $this->logger->log('debug', 'Date retrieved from XMP metadata.');
            }
        }

        if (!$date && $options['scan_id3']) {
            $date = (new GetId3DateExtractor())->getDate($file);
            if ($date) {
                $this->logger->log('debug', 'Date retrieved from getid3 metadata.');
            }
        }

        if (!$date && $options['file_name_masks']) {
            $date = $this->getFileNameDate($file, $options);
            if ($date) {
                $this->logger->log('debug', 'Date retrieved from file name.');
            }
        }

        if (!$date && $options['modified_time']) {
            $this->logger->log('debug', 'Date retrieved from modified time.');
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
            $this->logger->log('info', $file . ' moved to ' . $result);
        } else {
            $this->logger->log('warning', 'Could not move ' . $file . ' to ' . $target);
        }

        return $result;
    }
}
