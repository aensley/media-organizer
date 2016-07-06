# MediaOrganizer

Organize images and videos (or any files, really) into date-based folders.

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/aensley/media-organizer/blob/master/LICENSE) [![Build Status](https://travis-ci.org/aensley/media-organizer.svg)](https://travis-ci.org/aensley/media-organizer) [![HHVM Test Status](https://img.shields.io/hhvm/aensley/media-organizer.svg)](http://hhvm.h4cc.de/package/aensley/media-organizer) [![GitHub Issues](https://img.shields.io/github/issues-raw/aensley/media-organizer.svg)](https://github.com/aensley/media-organizer/issues) [![GitHub Downloads](https://img.shields.io/github/downloads/aensley/media-organizer/total.svg)](https://github.com/aensley/media-organizer/releases) [![Packagist Downloads](https://img.shields.io/packagist/dt/aensley/media-organizer.svg)](https://packagist.org/packages/aensley/media-organizer)

[![Code Climate Grade](https://codeclimate.com/github/aensley/media-organizer/badges/gpa.svg)](https://codeclimate.com/github/aensley/media-organizer) [![Code Climate Issues](https://img.shields.io/codeclimate/issues/github/aensley/media-organizer.svg)](https://codeclimate.com/github/aensley/media-organizer) [![Codacy Grade](https://api.codacy.com/project/badge/grade/a3adfef59dca4d64bafaa84afc812bdf)](https://www.codacy.com/app/awensley/media-organizer) [![SensioLabsInsight](https://img.shields.io/sensiolabs/i/92979f61-8adf-4b59-bd0a-2ddd3169a63c.svg)](https://insight.sensiolabs.com/projects/92979f61-8adf-4b59-bd0a-2ddd3169a63c)

[![Code Climate Test Coverage](https://codeclimate.com/github/aensley/media-organizer/badges/coverage.svg)](https://codeclimate.com/github/aensley/media-organizer/coverage) [![Codacy Test Coverage](https://api.codacy.com/project/badge/coverage/a3adfef59dca4d64bafaa84afc812bdf)](https://www.codacy.com/app/awensley/media-organizer) [![Codecov.io Test Coverage](https://codecov.io/github/aensley/media-organizer/coverage.svg?branch=master)](https://codecov.io/github/aensley/media-organizer?branch=master) [![Coveralls Test Coverage](https://coveralls.io/repos/github/aensley/media-organizer/badge.svg?branch=master)](https://coveralls.io/github/aensley/media-organizer?branch=master)

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

* PHP >= 5.5

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

----

[![Supercharged by ZenHub.io](https://raw.githubusercontent.com/ZenHubIO/support/master/zenhub-badge.png)](https://zenhub.io)
