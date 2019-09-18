# MediaOrganizer

Organize images and videos (or any files) into date-based folders.

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/aensley/media-organizer/blob/master/LICENSE)
[![Build Status](https://travis-ci.org/aensley/media-organizer.svg)](https://travis-ci.org/aensley/media-organizer)
[![Maintainability](https://api.codeclimate.com/v1/badges/329a1fe38b276ae65c7e/maintainability)](https://codeclimate.com/github/aensley/media-organizer/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/329a1fe38b276ae65c7e/test_coverage)](https://codeclimate.com/github/aensley/media-organizer/test_coverage)
[![Latest Stable Version](https://poser.pugx.org/aensley/media-organizer/v/stable)](https://packagist.org/packages/aensley/media-organizer)
[![Packagist Downloads](https://img.shields.io/packagist/dt/aensley/media-organizer)](https://packagist.org/packages/aensley/media-organizer)

## What it does

Description for the impatient reader: This library moves files from one place to another.

Detailed description: This library helps organize files into date-based folders. The date is retrieved from each file in a number of configurable ways. The structure of the date-based folders can be designed any way you want.

This was primarily written to organize JPG images, but it will work for files of any type. Available date-retrieval methods are:

1. **EXIF** - Retrieve the date from the file's EXIF data (JPG and TIFF images only).
2. **File Name Masks** - Match date/time patterns in the name of the file.
3. **Modified Time** - Use the file's "last modified" time. This property is set by the operating system and is often not as reliable as the first two.

## Installation

Install the latest version with

```bash
composer require aensley/media-organizer
```

## Options

### Profiles

You can specify any number of profiles to process. They will be processed in order. Each profile can have its own separate options. Available options are [documented in the code](https://github.com/aensley/media-organizer/blob/master/src/Aensley/MediaOrganizer/MediaOrganizer.php#L14).

### Logger

You can specify a logger object implementing the [PRS-3 Logger Interface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) for custom handling of log messages. I recommend [Monolog](https://github.com/Seldaek/monolog) (and [monolog-colored-line-formatter](https://github.com/bramus/monolog-colored-line-formatter) for bonus points in bash).

Otherwise, you can specify a log level string (one of: 'none', 'error', 'warning', 'info', 'debug') to use the simple internal logger. The internal logger directly echoes messages followed by newline characters `\n`.

## Requirements

* PHP >= 7.1

## Example usage

### Simple example

```php
<?php

require '/path/to/composer/autoload.php';

$organizer = new \Aensley\MediaOrganizer\MediaOrganizer(
	array(
		'images' => array(
			'source_directory' => '/data/unorganized_pictures/',
			'target_directory' => '/data/Organized/Pictures/',
			'valid_extensions' => array('jpg'),
		),
		'videos' => array(
			'source_directory' => '/data/unorganized_videos/',
			'target_directory' => '/data/Organized/Videos/',
			'valid_extensions' => array('mp4'),
			'scan_exif' => false,
		),
	),
	'debug'
);

$organizer->organize();
```

### Advanced Usage

```php
<?php

require '/path/to/composer/autoload.php';

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Bramus\Monolog\Formatter\ColoredLineFormatter;
use \Aensley\MediaOrganizer\MediaOrganizer;

$logger = new Logger('mediaOrganizer');
// Colored output in Bash
$handler = new StreamHandler('php://stdout', Logger::DEBUG);
$handler->setFormatter(new ColoredLineFormatter());
$logger->pushHandler($handler);
// Put everything in a log file, too.
$logger->pushHandler(new StreamHandler('/var/log/mediaOrganizer/mediaOrganizer.log', Logger::DEBUG));

$organizer = new MediaOrganizer(
	array(
		'images' => array(
			'source_directory' => '/data/unorganized_pictures/',
			'target_directory' => '/data/Organized/Pictures/',
			'valid_extensions' => array('jpg'),
		),
		'videos' => array(
			'source_directory' => '/data/unorganized_videos/',
			'target_directory' => '/data/Organized/Videos/',
			'valid_extensions' => array('mp4'),
			'scan_exif' => false,
		),
		'gifs' => array(
			'source_directory' => '/data/unorganized_gifs/',
			'target_directory' => '/data/Organized/Gifs/',
			'valid_extensions' => array('gif'),
			'scan_exif' => false,
			'file_name_masks' => false,
			'modified_time' => true,
			'search_recursive' => true,
			'target_mask' => 'Y/F/d',
			'overwrite' => true,
		),
	),
	$logger
);

$organizer->organize();
```
