# aensley/media-organizer

[![Version](https://img.shields.io/packagist/v/aensley/media-organizer.svg?logo=packagist&logoColor=fff)][packagist]
[![License](https://img.shields.io/github/license/aensley/media-organizer.svg)](https://github.com/aensley/media-organizer/blob/master/LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/aensley/media-organizer.svg?logo=packagist&logoColor=fff)][packagist]
[![Tests](https://github.com/aensley/media-organizer/actions/workflows/test.yml/badge.svg)](https://github.com/aensley/media-organizer/actions/workflows/test.yml)<br>
[![Maintainability](https://qlty.sh/gh/aensley/projects/media-organizer/maintainability.svg)][qltysh]
[![Code Coverage](https://qlty.sh/gh/aensley/projects/media-organizer/coverage.svg)][qltysh]
[![Socket](https://badge.socket.dev/composer/package/aensley/media-organizer)](https://socket.dev/composer/package/aensley/media-organizer)

Organize image, video, and audio files (or any files) into date-based folders.

## What it does

**TL;DR**: This library moves files from one place to another.

`media-organizer` helps organize files into date-based folders. The date is retrieved from each file in a number of configurable ways. The structure of the date-based folders can be designed any way you want.

Although primarily written to organize JPG images, this library will work for files of any type.

### Date Retrieval Methods

Enabled date-retrieval methods run in the following order. When the file date is found by one method, the remaining methods are skipped for that file.

| Method              | Description                                                                                                                             | Supported File Types                                                                               |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| **EXIF**            | Retrieve the date from the file's EXIF data.                                                                                            | jpeg, jpg, heic, heif, webp, tiff, dng, raw, avif, png                                             |
| **XMP**             | Retrieve the date from the file's XMP data.                                                                                             | jpeg, jpg, png, gif, svg, heic, heif, webp, tiff, dng, raw, pdf, ai, eps, avif, psd, psb, mp4, mov |
| **ID3**             | Use [`getid3`](#getid3) for advanced file metadata retrieval methods.                                                                   | mp4, m4a, mov, mkv, webm, mp3, riff, avi, vorbis, flac, ogg, oga, mka                              |
| **File Name Masks** | Match date/time patterns in the name of the file.                                                                                       | all files                                                                                          |
| **Modified Time**   | Use the file's "last modified" time.<br><br>_This property is set by the operating system and is not as reliable as the other methods._ | all files                                                                                          |

## Installation

Install the latest version

```bash
composer require aensley/media-organizer
```

## Configuration

### Profiles

Profiles are defined in associative arrays.

```php
[
    'images' => [
        'source_directory' => '/data/unorganized_pictures/',
        'target_directory' => '/data/Organized/Pictures/',
        'valid_extensions' => ['jpg'],
    ],
]
```

You can specify any number of profiles. each is processed in order with its own options.

#### Profile Options

| Option               | Description                                                                                                                                                                                                                                                             | Default                      |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **source_directory** | **REQUIRED**: Directory to search for files.<br>Ending slash required.                                                                                                                                                                                                  | `""`                         |
| search_recursive     | Set to true to look in all subdirectories of source_directory for files.                                                                                                                                                                                                | `false`                      |
| valid_extensions     | Array of file extensions to search for.<br>Set to an empty array (`[]`) to include all files.                                                                                                                                                                           | `['jpg', 'jpeg']`            |
| **target_directory** | **REQUIRED**: Destination directory for organized files.<br>Ending slash required.                                                                                                                                                                                      | `""`                         |
| target_mask          | Directory structure to use for target.<br><br>**Y** = 4-digit year, **y** = 2-digit year, **m** = 2-digit month, **d** = 2-digit day.<br><br>Anything supported by [`date()`](http://php.net/date) will work, except time-based options as they will not be consistent. | `"Y/Y-m-d"`                  |
| overwrite            | Whether to overwrite destination files.<br>**true** = overwrite same files that already exist in target.<br>**false** = add incrementing counter to identical file names to avoid collisions.                                                                           | `false`                      |
| scan_exif            | **Date Retrieval Method**: Scan EXIF data for file date.<br><br>Supports image files (JPG, TIFF, HEIC, WEBP, etc.).                                                                                                                                                     | `true`                       |
| scan_xmp             | **Date Retrieval Method**: Scan XMP data for file date.<br><br>Supports mostly image files (JPG, PNG, GIF, SVG, etc.) and some video files (MP4, MOV, etc.).                                                                                                            | `false`                      |
| scan_id3             | **Date Retrieval Method**: Scan metadata via [getid3](#getid3) for file date.<br><br>Supports video files (MP4, MOV, MKV, AVI, etc.) and audio files (MP3, FLAC, OGG, etc.).                                                                                            | `false`                      |
| file_name_masks      | **Date Retrieval Method**: Patterns to search for in file names for date. Set to `false` to disable filename logic.<br><br>**Y** = year digit, **M** = month digit, **D** = day digit. All are replaced with digits for regex search.                                   | `['YYYY-MM-DD', 'YYYYMMDD']` |
| modified_time        | **Date Retrieval Method**: Whether or not to use the file's modified time.                                                                                                                                                                                              | `false`                      |

### GetID3

> [!IMPORTANT]
>
> Installing [james-heinrich/getid3][getid3] is strongly recommended to enable advanced metadata processing for video and audio files.
>
> This library is excluded by default due to its substantial size, providing the option to omit it if it does not apply to your use case.

Enable advanced video and audio file processing with `'scan_id3' => true` in your profile after installing the dependency.

```bash
composer require james-heinrich/getid3
```

With this library installed, the following additional date-retrieval methods are enabled via `scan_id3`.

| File Format     | Metadata                                               |
| --------------- | ------------------------------------------------------ |
| MP4 / MOV       | `quicktime.timestamps_unix.create['moov.mvhd']`        |
| MKV / WebM      | `matroska.info[n].DateUTC_unix`                        |
| ID3v2 (MP3)     | `comments.recording_time` or `comments.date`           |
| RIFF/AVI        | `comments.creationdate` or `comments.digitizationdate` |
| Vorbis/FLAC/OGG | `comments.date`                                        |
| ID3v1 year-only | `comments.year` (returns `YYYY-01-01`)                 |

### Logger

You can specify a logger object implementing the [PRS-3 Logger Interface](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) for handling of log messages.

> [!TIP]
>
> [monolog](https://github.com/Seldaek/monolog) is recommended for comprehensive logging options.
>
> [monolog-colored-line-formatter](https://github.com/bramus/monolog-colored-line-formatter) can be added for colored output in bash.

If no logger is provided, the package will simply echo all log messages directly.

## Requirements

- PHP >= 7.1

## Example usage

### Simple example

```php
<?php

require '/path/to/composer/autoload.php';

$organizer = new \Aensley\MediaOrganizer\MediaOrganizer(
    [
        'images' => [
            'source_directory' => '/data/unorganized_pictures/',
            'target_directory' => '/data/Organized/Pictures/',
            'valid_extensions' => ['jpg'],
        ],
        'videos' => [
            'source_directory' => '/data/unorganized_videos/',
            'target_directory' => '/data/Organized/Videos/',
            'valid_extensions' => ['mp4'],
            'scan_exif' => false,
        ],
    ]
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
    [
        'images' => [
            'source_directory' => '/data/unorganized_pictures/',
            'target_directory' => '/data/Organized/Pictures/',
            'valid_extensions' => ['jpg'],
            'scan_xmp' => true,
        ],
        'images_png' => [
            'source_directory' => '/data/unorganized_pictures/',
            'target_directory' => '/data/Organized/Pictures/',
            'valid_extensions' => ['png'],
            'scan_xmp' => true,
        ],
        'videos' => [
            'source_directory' => '/data/unorganized_videos/',
            'target_directory' => '/data/Organized/Videos/',
            'valid_extensions' => ['mp4'],
            'scan_exif' => false,
            'scan_id3' => true,
        ],
        'gifs' => [
            'source_directory' => '/data/unorganized_gifs/',
            'target_directory' => '/data/Organized/Gifs/',
            'valid_extensions' => ['gif'],
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
            'search_recursive' => true,
            'target_mask' => 'Y/F/d',
            'overwrite' => true,
        ],
    ],
    $logger
);

$organizer->organize();
```

[packagist]: https://packagist.org/packages/aensley/media-organizer
[qltysh]: https://qlty.sh/gh/aensley/projects/media-organizer
[getid3]: https://github.com/JamesHeinrich/getID3
