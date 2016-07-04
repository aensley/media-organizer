# MediaOrganizer

Organize images and videos (or any files, really) into date-based folders.

[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/aensley/media-organizer/blob/master/LICENSE) [![Build Status](https://travis-ci.org/aensley/media-organizer.svg)](https://travis-ci.org/aensley/media-organizer) [![HHVM Test Status](https://img.shields.io/hhvm/aensley/media-organizer.svg)](http://hhvm.h4cc.de/package/aensley/media-organizer) [![GitHub Issues](https://img.shields.io/github/issues-raw/aensley/media-organizer.svg)](https://github.com/aensley/media-organizer/issues) [![GitHub Downloads](https://img.shields.io/github/downloads/aensley/media-organizer/total.svg)](https://github.com/aensley/media-organizer/releases) [![Packagist Downloads](https://img.shields.io/packagist/dt/aensley/media-organizer.svg)](https://packagist.org/packages/aensley/media-organizer)

[![Code Climate Grade](https://codeclimate.com/github/aensley/media-organizer/badges/gpa.svg)](https://codeclimate.com/github/aensley/media-organizer) [![Code Climate Issues](https://img.shields.io/codeclimate/issues/github/aensley/media-organizer.svg)](https://codeclimate.com/github/aensley/media-organizer) [![Codacy Grade](https://api.codacy.com/project/badge/grade/a3adfef59dca4d64bafaa84afc812bdf)](https://www.codacy.com/app/awensley/media-organizer) [![SensioLabsInsight](https://img.shields.io/sensiolabs/i/92979f61-8adf-4b59-bd0a-2ddd3169a63c.svg)](https://insight.sensiolabs.com/projects/92979f61-8adf-4b59-bd0a-2ddd3169a63c)

[![Code Climate Test Coverage](https://codeclimate.com/github/aensley/media-organizer/badges/coverage.svg)](https://codeclimate.com/github/aensley/media-organizer/coverage) [![Codacy Test Coverage](https://api.codacy.com/project/badge/coverage/a3adfef59dca4d64bafaa84afc812bdf)](https://www.codacy.com/app/awensley/media-organizer) [![Codecov.io Test Coverage](https://codecov.io/github/aensley/media-organizer/coverage.svg?branch=master)](https://codecov.io/github/aensley/media-organizer?branch=master) [![Coveralls Test Coverage](https://coveralls.io/repos/github/aensley/media-organizer/badge.svg?branch=master)](https://coveralls.io/github/aensley/media-organizer?branch=master)

## What it does

Moves stuff around. More details to come...

## Requirements

 * PHP >= 5.5

## Example usage

```php
<?php

include 'MediaOrganizer.php';

$organizer = new \Aensley\MediaOrganizer\MediaOrganizer(
	array(
		'images' => array(
			'source_directory' => '/home/user1/unorganized_pictures/',
			'target_directory' => '/home/user1/organized_pictures/',
			'scan_exif' => false,
		),
	)
);

$organizer->organize();
```

----

[![Supercharged by ZenHub.io](https://raw.githubusercontent.com/ZenHubIO/support/master/zenhub-badge.png)](https://zenhub.io)
