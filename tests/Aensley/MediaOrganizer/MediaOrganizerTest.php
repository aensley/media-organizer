<?php

namespace Aensley\MediaOrganizer;

use Aensley\MediaOrganizer\MediaOrganizer;
use Aensley\MediaOrganizer\XmpDateExtractor;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MediaOrganizerTest extends \PHPUnit\Framework\TestCase
{
    private const CLASS_NAME = '\Aensley\MediaOrganizer\MediaOrganizer';
    private const DATE_JULY_5_2016 = '2016-07-05';
    private const DEST_FILE_NAME = '2016/' . self::DATE_JULY_5_2016 . '/test_exif_july_5_2016.jpg';
    private const GETID3_STUB_PATH = __DIR__ . '/stubs/GetId3Stub.php';
    private const XMP_DATE_JULY_5_2016 = '2016:07:05 09:54:55';

    private $sourceDirectory;
    private $targetDirectory;
    private $mediaOrganizer;
    private $extractor;
    private $xmpExtractor;
    private $profiles = [
        'images_exif' => [
            'file_name_masks' => false,
        ],
        'images_fileNames' => [
            'scan_exif' => false,
        ],
        'images_modifiedTime' => [
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ],
        'images_recursive' => [
            'search_recursive' => true,
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ],
        'videos_fileNames' => [
            'valid_extensions' => ['mp4'],
            'scan_exif' => false,
        ],
        'videos_modifiedTime' => [
            'valid_extensions' => ['mp4'],
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ],
    ];
    private $sourceFiles = [];
    private $targetFiles = [];

    protected function setUp(): void
    {
        $this->sourceDirectory = realpath(dirname(__FILE__) . '/../../../') . '/test_data/source/';
        $this->targetDirectory = realpath(dirname(__FILE__) . '/../../../') . '/test_data/target/';
        foreach (
            [
                'images_exif',
                'images_fileNames',
                'images_modifiedTime',
                'images_recursive',
                'videos_fileNames',
                'videos_modifiedTime'
            ] as $profile
        ) {
            $this->profiles[$profile]['source_directory'] = $this->sourceDirectory;
            $this->profiles[$profile]['target_directory'] = $this->targetDirectory;
        }

        $this->sourceFiles = [
            $this->sourceDirectory . 'test_exif_july_5_2016.jpg',
            $this->sourceDirectory . 'modified_test.jpg',
            $this->sourceDirectory . 'wrong.extension',
            $this->sourceDirectory . 'sub_directory/search_recursive.jpg',
            $this->sourceDirectory . '_valid_fileNames_YYYYMMDD_' . date('Ymd') . '.jpg',
            $this->sourceDirectory . '_valid_fileNames_YYYY-MM-DD_' . date('Y-m-d') . '.jpg',
            $this->sourceDirectory . '_invalid_fileNames_YYMMDD_' . date('ymd') . '.jpg',
            $this->sourceDirectory . '_valid_fileNames_YYYYMMDD_' . date('Ymd') . '.mp4',
            $this->sourceDirectory . '_valid_fileNames_YYYY-MM-DD_' . date('Y-m-d') . '.mp4',
            $this->sourceDirectory . '_invalid_fileNames_YYMMDD_' . date('ymd') . '.mp4'
        ];

        $this->targetFiles = [
            $this->targetDirectory . self::DEST_FILE_NAME,
            $this->targetDirectory . date('Y') . '/' . date('Y-m-d') . '/modified_test.jpg',
            $this->targetDirectory . date('Y') . '/' . date('Y-m-d') . '/search_recursive.jpg',
            $this->targetDirectory . date('Y') . '/' . date('Y-m-d')
                . '/_valid_fileNames_YYYYMMDD_' . date('Ymd') . '.jpg',
            $this->targetDirectory . date('Y') . '/' . date('Y-m-d')
                . '/_valid_fileNames_YYYY-MM-DD_' . date('Y-m-d') . '.jpg',
            $this->targetDirectory . date('Y') . '/' . date('Y-m-d')
                . '/_valid_fileNames_YYYYMMDD_' . date('Ymd') . '.mp4',
            $this->targetDirectory . date('Y') . '/' . date('Y-m-d')
                . '/_valid_fileNames_YYYY-MM-DD_' . date('Y-m-d') . '.mp4',
        ];

        $this->mediaOrganizer = new MediaOrganizer($this->profiles, $this->createLogger());
        $this->extractor      = new GetId3DateExtractor();
        $this->xmpExtractor   = new XmpDateExtractor();
    }

    public function testInstantiation()
    {
        $this->assertInstanceOf(self::CLASS_NAME, $this->mediaOrganizer);
    }

    public function testBadOptions()
    {
        $this->expectNotToPerformAssertions();
        $this->mediaOrganizer->organize();
        $this->mediaOrganizer->organize(
            ['test_empty_target' => ['source_directory' => $this->sourceDirectory]]
        );
    }

    public function testAutoCreatesNonExistentDirectories(): void
    {
        $tempSource = sys_get_temp_dir() . '/mo_source_' . uniqid();
        $tempTarget = sys_get_temp_dir() . '/mo_target_' . uniqid();
        $this->mediaOrganizer->organize([
            'auto_create' => [
                'source_directory' => $tempSource,
                'target_directory' => $tempTarget,
                'scan_exif'        => false,
                'file_name_masks'  => false,
                'modified_time'    => false,
            ],
        ]);
        $this->assertDirectoryExists($tempSource);
        rmdir($tempSource);
        rmdir($tempTarget);
    }

    public function testEmptyInstantiation()
    {
        $this->assertInstanceOf(self::CLASS_NAME, new MediaOrganizer());
    }

    public function testEchoLogger()
    {
        $this->resetTestFiles();
        $profile = [
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => false,
        ];
        $organizer = new MediaOrganizer();
        $this->expectOutputRegex('/ERROR/');
        $organizer->organize(['no_scan_options' => $profile]);
    }

    public function testExif()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['images_exif' => $this->profiles['images_exif']]);
        foreach ($this->targetFiles as $targetFile) {
            if (strpos($targetFile, 'exif_') !== false) {
                // We should find all "exif_" files in their expected places in the target.
                $this->assertFileExists($targetFile);
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (strpos($sourceFile, 'exif_') !== false) {
                // We should not find any "exif_" files in the source anymore.
                $this->assertFileDoesNotExist($sourceFile);
            }
        }
    }

    public function testFileNames()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['images_fileNames' => $this->profiles['images_fileNames']]);
        foreach ($this->targetFiles as $targetFile) {
            if (substr($targetFile, -4) === '.jpg' && strpos($targetFile, '_valid_fileNames_') !== false) {
                // We should find all "valid_fileNames_" files in their expected places in the target.
                $this->assertFileExists($targetFile);
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (substr($sourceFile, -4) === '.jpg') {
                if (strpos($sourceFile, '_valid_fileNames_') !== false) {
                    // We should not find any "valid_fileNames_" files in the source anymore.
                    $this->assertFileDoesNotExist($sourceFile);
                } elseif (strpos($sourceFile, '_invalid_fileNames_') !== false) {
                    // We should still find all "invalid_fileNames_" files in the source.
                    $this->assertFileExists($sourceFile);
                }
            }
        }

        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['videos_fileNames' => $this->profiles['videos_fileNames']]);
        foreach ($this->targetFiles as $targetFile) {
            if (substr($targetFile, -4) === '.mp4' && strpos($targetFile, '_valid_fileNames_') !== false) {
                // We should find all "valid_fileNames_" files in their expected places in the target.
                $this->assertFileExists($targetFile);
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (substr($sourceFile, -4) === '.mp4') {
                if (strpos($sourceFile, '_valid_fileNames_') !== false) {
                    // We should not find any "valid_fileNames_" files in the source anymore.
                    $this->assertFileDoesNotExist($sourceFile);
                } elseif (strpos($sourceFile, '_invalid_fileNames_') !== false) {
                    // We should still find all "invalid_fileNames_" files in the source.
                    $this->assertFileExists($sourceFile);
                }
            }
        }
    }

    public function testModifiedTime()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['images_modifiedTime' => $this->profiles['images_modifiedTime']]);
        foreach ($this->targetFiles as $targetFile) {
            if (substr($targetFile, -4) === '.jpg') {
                // We should find all ".jpg" files in their expected places in the target.
                // Except exif_ and search_recursive
                if (strpos($targetFile, 'exif_') === false && strpos($targetFile, 'search_recursive') === false) {
                    $this->assertFileExists($targetFile);
                    continue;
                }

                // ...Except exif files won't be where we defined in $this->targetFiles.
                // They'll be in a folder with today's date.
                if (strpos($targetFile, 'exif_') !== false) {
                    $this->assertFileExists(
                        $this->targetDirectory . date('Y') . '/' . date('Y-m-d') . '/' . basename($targetFile)
                    );
                }
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (substr($sourceFile, -4) === '.jpg' && strpos($sourceFile, 'search_recursive') === false) {
                // There shouldn't be any .jpg files left in the source, except the search_recursive test.
                $this->assertFileDoesNotExist($sourceFile);
            }
        }

        $this->assertFileExists($this->sourceDirectory . 'wrong.extension');
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['videos_modifiedTime' => $this->profiles['videos_modifiedTime']]);
        foreach ($this->targetFiles as $targetFile) {
            if (substr($targetFile, -4) === '.mp4') {
                // We should find all ".mp4" files in their expected places in the target.
                $this->assertFileExists($targetFile);
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (substr($sourceFile, -4) === '.mp4') {
                // We should not find any "mp4" files in the source anymore.
                $this->assertFileDoesNotExist($sourceFile);
            }
        }

        $this->assertFileExists($this->sourceDirectory . 'wrong.extension');
    }

    public function testLoggerObject()
    {
        $this->expectNotToPerformAssertions();
        $this->mediaOrganizer = new MediaOrganizer($this->profiles, $this->createLogger());
        $this->mediaOrganizer->organize(['images_exif' => $this->profiles['images_exif']]);
    }

    public function testSearchRecursive()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['images_recursive' => $this->profiles['images_recursive']]);
        foreach ($this->targetFiles as $targetFile) {
            if (substr($targetFile, -4) === '.jpg') {
                // We should find all ".jpg" files in their expected places in the target.
                if (strpos($targetFile, 'exif_') === false) {
                    $this->assertFileExists($targetFile);
                    continue;
                }

                // ...Except exif files won't be where we defined in $this->targetFiles.
                // They'll be in a folder with today's date.
                if (strpos($targetFile, 'exif_') !== false) {
                    $this->assertFileExists(
                        $this->targetDirectory . date('Y') . '/' . date('Y-m-d') . '/' . basename($targetFile)
                    );
                }
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (substr($sourceFile, -4) === '.jpg') {
                // There shouldn't be any .jpg files left in the source.
                $this->assertFileDoesNotExist($sourceFile);
            }
        }
    }

    public function testFileRenaming()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(['images_exif' => $this->profiles['images_exif']]);
        foreach ($this->targetFiles as $targetFile) {
            if (strpos($targetFile, 'exif_') !== false) {
                // We should find all "exif_" files in their expected places in the target.
                $this->assertFileExists($targetFile);
            }
        }

        foreach ($this->sourceFiles as $sourceFile) {
            if (strpos($sourceFile, 'exif_') !== false) {
                // We should not find any "exif_" files in the source anymore.
                $this->assertFileDoesNotExist($sourceFile);
            }
        }

        for ($x = 0; $x < 20; $x++) {
            $this->resetTestFiles(true);
            $this->mediaOrganizer->organize(['images_exif' => $this->profiles['images_exif']]);
            foreach ($this->targetFiles as $targetFile) {
                if (strpos($targetFile, 'exif_') !== false) {
                    // We should find all "exif_" files in their expected places in the target.
                    $this->assertFileExists(substr($targetFile, 0, -4) . '_' . $x . '.jpg');
                }
            }

            foreach ($this->sourceFiles as $sourceFile) {
                if (strpos($sourceFile, 'exif_') !== false) {
                    // We should not find any "exif_" files in the source anymore.
                    $this->assertFileDoesNotExist($sourceFile);
                }
            }
        }
    }

    public function testInvalidTargetMask()
    {
        $this->resetTestFiles();
        $profile = $this->profiles['images_exif'];
        $profile['target_mask'] = 'abc';
        $this->mediaOrganizer->organize(['invalid_mask' => $profile]);
        $this->assertFileExists($this->sourceDirectory . 'test_exif_july_5_2016.jpg');
    }

    public function testNoScanOptions()
    {
        $this->resetTestFiles();
        $profile = [
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => false,
        ];
        $this->mediaOrganizer->organize(['no_scan_options' => $profile]);
        $this->assertFileExists($this->sourceDirectory . 'test_exif_july_5_2016.jpg');
    }

    public function testOverwrite()
    {
        $this->resetTestFiles();
        $profile = $this->profiles['images_exif'];
        $profile['overwrite'] = true;
        $this->mediaOrganizer->organize(['images_exif' => $profile]);
        $exifTarget = $this->targetDirectory . self::DEST_FILE_NAME;
        $this->assertFileExists($exifTarget);

        $this->resetTestFiles(true);
        $this->mediaOrganizer->organize(['images_exif' => $profile]);
        $this->assertFileExists($exifTarget);
        $this->assertFileDoesNotExist($this->targetDirectory . '2016/'
            . self::DATE_JULY_5_2016 . '/test_exif_july_5_2016_0.jpg');
    }

    public function testAllExtensions()
    {
        $this->resetTestFiles();
        $profile = [
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'valid_extensions' => [],
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ];
        $this->mediaOrganizer->organize(['all_extensions' => $profile]);
        $this->assertFileDoesNotExist($this->sourceDirectory . 'wrong.extension');
        $this->assertFileExists($this->targetDirectory . date('Y') . '/' . date('Y-m-d') . '/wrong.extension');
    }

    public function testSymlinkSkipped()
    {
        $this->resetTestFiles();
        $linkPath = $this->sourceDirectory . 'test_symlink.jpg';
        symlink($this->sourceDirectory . 'test_exif_july_5_2016.jpg', $linkPath);
        $this->mediaOrganizer->organize(['images_exif' => $this->profiles['images_exif']]);
        $this->assertTrue(is_link($linkPath));
        unlink($linkPath);
    }

    public function testScanId3MissingClass(): void
    {
        $profile = [
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'scan_id3'         => true,
            'scan_exif'        => false,
            'file_name_masks'  => false,
            'modified_time'    => false,
        ];
        $organizer = new MediaOrganizer();
        $this->expectOutputRegex('/getID3/');
        $organizer->organize(['scan_id3_missing' => $profile]);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testScanId3DateFound(): void
    {
        require_once self::GETID3_STUB_PATH;
        $this->resetTestFiles();
        $this->mediaOrganizer->organize([
            'scan_id3_found' => [
                'source_directory' => $this->sourceDirectory,
                'target_directory' => $this->targetDirectory,
                'valid_extensions' => ['jpg'],
                'scan_id3'         => true,
                'scan_exif'        => false,
                'file_name_masks'  => false,
                'modified_time'    => false,
            ],
        ]);
        $this->assertFileExists(
            $this->targetDirectory . self::DEST_FILE_NAME
        );
    }

    public function testFileMaskUnknownFallback(): void
    {
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokePrivate('fileMask', 'photo_20160705', 'UNKNOWN_MASK'));
    }

    public function testUnreadableFileSkipped(): void
    {
        if (posix_geteuid() === 0) {
            $this->markTestSkipped('Cannot test unreadable files as root.');
        }

        $this->resetTestFiles();
        $file = $this->sourceDirectory . 'test_exif_july_5_2016.jpg';
        chmod($file, 0000);
        $this->mediaOrganizer->organize(['images_exif' => $this->profiles['images_exif']]);
        $this->assertFileExists($file);
        $this->assertFileDoesNotExist($this->targetDirectory . self::DEST_FILE_NAME);
        chmod($file, 0644);
    }

    public function testSetLogger(): void
    {
        $this->expectNotToPerformAssertions();
        $this->mediaOrganizer->setLogger($this->createLogger());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testGetDate(): void
    {
        require_once self::GETID3_STUB_PATH;
        $extractor = new GetId3DateExtractor();
        $this->assertSame(self::DATE_JULY_5_2016, $extractor->getDate('/tmp/test.mp4'));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testGetDateFallsBackToMatroska(): void
    {
        require_once self::GETID3_STUB_PATH;
        \getID3::$result = ['matroska' => ['info' => [['DateUTC_unix' => mktime(0, 0, 0, 7, 5, 2016)]]]];
        $extractor = new GetId3DateExtractor();
        $this->assertSame(self::DATE_JULY_5_2016, $extractor->getDate('/tmp/test.mkv'));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testGetDateFallsBackToTaggedComment(): void
    {
        require_once self::GETID3_STUB_PATH;
        \getID3::$result = ['comments' => ['recording_time' => [self::DATE_JULY_5_2016]]];
        $extractor = new GetId3DateExtractor();
        $this->assertSame(self::DATE_JULY_5_2016, $extractor->getDate('/tmp/test.ogg'));
    }

    public function testGetQuickTimeDateEmpty(): void
    {
        $this->assertSame('', $this->invokeExtractor('getQuickTimeDate', []));
    }

    public function testGetQuickTimeDateMoovMvhd(): void
    {
        $ts   = mktime(0, 0, 0, 7, 5, 2016);
        $info = ['quicktime' => ['timestamps_unix' => ['create' => ['moov.mvhd' => $ts]]]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeExtractor('getQuickTimeDate', $info));
    }

    public function testGetQuickTimeDateFallbackKey(): void
    {
        $ts   = mktime(0, 0, 0, 7, 5, 2016);
        $info = ['quicktime' => ['timestamps_unix' => ['create' => ['trak.tkhd' => $ts]]]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeExtractor('getQuickTimeDate', $info));
    }

    public function testGetQuickTimeDateZeroTimestamp(): void
    {
        $info = ['quicktime' => ['timestamps_unix' => ['create' => ['moov.mvhd' => 0]]]];
        $this->assertSame('', $this->invokeExtractor('getQuickTimeDate', $info));
    }

    public function testGetMatroskaDateEmpty(): void
    {
        $this->assertSame('', $this->invokeExtractor('getMatroskaDate', []));
    }

    public function testGetMatroskaDateValid(): void
    {
        $ts   = mktime(0, 0, 0, 7, 5, 2016);
        $info = ['matroska' => ['info' => [['DateUTC_unix' => $ts]]]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeExtractor('getMatroskaDate', $info));
    }

    public function testGetMatroskaDateSkipsZeroSegment(): void
    {
        $ts   = mktime(0, 0, 0, 7, 5, 2016);
        $info = ['matroska' => ['info' => [['DateUTC_unix' => 0], ['DateUTC_unix' => $ts]]]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeExtractor('getMatroskaDate', $info));
    }

    public function testGetMatroskaDateNoValid(): void
    {
        $info = ['matroska' => ['info' => [['other_key' => 123]]]];
        $this->assertSame('', $this->invokeExtractor('getMatroskaDate', $info));
    }

    public function testGetTaggedCommentDateEmpty(): void
    {
        $this->assertSame('', $this->invokeExtractor('getTaggedCommentDate', []));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function taggedCommentProvider(): array
    {
        return [
            'recording_time'    => ['recording_time',    self::DATE_JULY_5_2016],
            'date'              => ['date',              self::DATE_JULY_5_2016],
            'creationdate'      => ['creationdate',      self::DATE_JULY_5_2016],
            'digitizationdate'  => ['digitizationdate',  self::DATE_JULY_5_2016],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('taggedCommentProvider')]
    public function testGetTaggedCommentDateKeys(string $tagKey, string $expected): void
    {
        $info = ['comments' => [$tagKey => [self::DATE_JULY_5_2016]]];
        $this->assertSame($expected, $this->invokeExtractor('getTaggedCommentDate', $info));
    }

    public function testGetTaggedCommentDateUnparseable(): void
    {
        $info = ['comments' => ['recording_time' => ['not-a-date']]];
        $this->assertSame('', $this->invokeExtractor('getTaggedCommentDate', $info));
    }

    public function testGetTaggedCommentDateFallsThrough(): void
    {
        $ts   = mktime(0, 0, 0, 7, 5, 2016);
        $info = ['comments' => ['recording_time' => ['not-a-date'], 'date' => [date('Y-m-d', $ts)]]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeExtractor('getTaggedCommentDate', $info));
    }

    public function testGetYearTagDateEmpty(): void
    {
        $this->assertSame('', $this->invokeExtractor('getYearTagDate', []));
    }

    public function testGetYearTagDateValid(): void
    {
        $info = ['comments' => ['year' => ['2016']]];
        $this->assertSame('2016-01-01', $this->invokeExtractor('getYearTagDate', $info));
    }

    public function testGetYearTagDateNonFourDigit(): void
    {
        $this->assertSame('', $this->invokeExtractor('getYearTagDate', ['comments' => ['year' => ['16']]]));
        $this->assertSame('', $this->invokeExtractor('getYearTagDate', ['comments' => ['year' => ['abc']]]));
    }

    public function testScanXmpDateFound(): void
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize([
            'scan_xmp_found' => [
                'source_directory' => $this->sourceDirectory,
                'target_directory' => $this->targetDirectory,
                'valid_extensions' => ['jpg'],
                'scan_xmp'         => true,
                'scan_exif'        => false,
                'file_name_masks'  => false,
                'modified_time'    => false,
            ],
        ]);
        $this->assertFileExists($this->targetDirectory . self::DEST_FILE_NAME);
    }

    public function testScanXmpNoXmpData(): void
    {
        // A touch'd empty file has no XMP packet; extractor should return empty string.
        $this->assertSame('', $this->xmpExtractor->getDate($this->sourceDirectory . 'modified_test.jpg'));
    }

    public function testScanXmpRealFile(): void
    {
        // The test asset has xmp:CreateDate = 2016-07-05T09:54:55.920
        $file = realpath(dirname(__FILE__) . '/../../../assets/') . '/test_exif_july_5_2016.jpg';
        $this->assertSame(self::DATE_JULY_5_2016, $this->xmpExtractor->getDate($file));
    }

    public function testScanXmpScanValidOption(): void
    {
        // scan_xmp alone should satisfy the "at least one scan method" requirement.
        $this->resetTestFiles();
        $file = realpath(dirname(__FILE__) . '/../../../assets/') . '/test_exif_july_5_2016.jpg';
        copy($file, $this->sourceDirectory . 'test_exif_july_5_2016.jpg');
        $this->mediaOrganizer->organize([
            'xmp_only' => [
                'source_directory' => $this->sourceDirectory,
                'target_directory' => $this->targetDirectory,
                'valid_extensions' => ['jpg'],
                'scan_xmp'         => true,
                'scan_exif'        => false,
                'scan_id3'         => false,
                'file_name_masks'  => false,
                'modified_time'    => false,
            ],
        ]);
        $this->assertFileExists($this->targetDirectory . self::DEST_FILE_NAME);
    }

    public function testGetDateFromResultsEmpty(): void
    {
        $this->assertSame('', $this->invokeXmpExtractor('getDateFromResults', []));
    }

    public function testGetDateFromResultsExifOriginal(): void
    {
        $results = ['xmp-exif' => ['DateTimeOriginal' => self::XMP_DATE_JULY_5_2016]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeXmpExtractor('getDateFromResults', $results));
    }

    public function testGetDateFromResultsDeprecatedOriginal(): void
    {
        $results = ['xmp-deprecated' => ['DateTimeOriginal' => self::XMP_DATE_JULY_5_2016]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeXmpExtractor('getDateFromResults', $results));
    }

    public function testGetDateFromResultsExifDigitized(): void
    {
        $results = ['xmp-exif' => ['DateTimeDigitized' => self::XMP_DATE_JULY_5_2016]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeXmpExtractor('getDateFromResults', $results));
    }

    public function testGetDateFromResultsGeneralDigitized(): void
    {
        // xmp:CreateDate maps to xmp-general.DateTimeDigitized
        $results = ['xmp-general' => ['DateTimeDigitized' => self::XMP_DATE_JULY_5_2016]];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeXmpExtractor('getDateFromResults', $results));
    }

    public function testGetDateFromResultsPriority(): void
    {
        // xmp-exif.DateTimeOriginal should win over xmp-general.DateTimeDigitized
        $results = [
            'xmp-exif'    => ['DateTimeOriginal' => self::XMP_DATE_JULY_5_2016],
            'xmp-general' => ['DateTimeDigitized' => '2020:01:01 00:00:00'],
        ];
        $this->assertSame(self::DATE_JULY_5_2016, $this->invokeXmpExtractor('getDateFromResults', $results));
    }

    public function testGetDateFromResultsUnparseable(): void
    {
        $results = ['xmp-exif' => ['DateTimeOriginal' => 'not-a-date']];
        $this->assertSame('', $this->invokeXmpExtractor('getDateFromResults', $results));
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->mediaOrganizer, $method);
        return $ref->invoke($this->mediaOrganizer, ...$args);
    }

    private function invokeExtractor(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->extractor, $method);
        return $ref->invoke($this->extractor, ...$args);
    }

    private function invokeXmpExtractor(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->xmpExtractor, $method);
        return $ref->invoke($this->xmpExtractor, ...$args);
    }

    private function createLogger(): Logger
    {
        $logger = new Logger('test');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::EMERGENCY));
        return $logger;
    }

    private function resetTestFiles($sourceOnly = false)
    {
        if (!is_dir($this->sourceDirectory)) {
            mkdir($this->sourceDirectory, 0777, true);
        }

        if (!is_dir($this->sourceDirectory . 'sub_directory/')) {
            mkdir($this->sourceDirectory . 'sub_directory/', 0777, true);
        }

        if (!$sourceOnly && is_dir($this->targetDirectory)) {
            $this->deleteDirectory($this->targetDirectory);
        }

        if (!is_dir($this->targetDirectory)) {
            mkdir($this->targetDirectory, 0777, true);
        }

        $testExifFile = realpath(dirname(__FILE__) . '/../../../assets/') . '/test_exif_july_5_2016.jpg';
        copy($testExifFile, $this->sourceDirectory . 'test_exif_july_5_2016.jpg');
        foreach ($this->sourceFiles as $sourceFile) {
            touch($sourceFile);
        }
    }

    private function deleteDirectory($directory = '')
    {
        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $file = $directory . '/' . $file;
            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }

        rmdir($directory);
    }
}
