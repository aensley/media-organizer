<?php

namespace Aensley\MediaOrganizer;

use Aensley\MediaOrganizer\MediaOrganizer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MediaOrganizerTest extends \PHPUnit\Framework\TestCase
{
    private $sourceDirectory;
    private $targetDirectory;
    private $mediaOrganizer;
    private $profiles = array(
        'images_exif' => array(
            'file_name_masks' => false,
        ),
        'images_fileNames' => array(
            'scan_exif' => false,
        ),
        'images_modifiedTime' => array(
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ),
        'images_recursive' => array(
            'search_recursive' => true,
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ),
        'videos_fileNames' => array(
            'valid_extensions' => array('mp4'),
            'scan_exif' => false,
        ),
        'videos_modifiedTime' => array(
            'valid_extensions' => array('mp4'),
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        ),
    );
    private $sourceFiles = array();
    private $targetFiles = array();

    protected function setUp(): void
    {
        $this->sourceDirectory = realpath(dirname(__FILE__) . '/../../../') . '/test_data/source/';
        $this->targetDirectory = realpath(dirname(__FILE__) . '/../../../') . '/test_data/target/';
        foreach (
            array(
                'images_exif',
                'images_fileNames',
                'images_modifiedTime',
                'images_recursive',
                'videos_fileNames',
                'videos_modifiedTime'
            ) as $profile
        ) {
            $this->profiles[$profile]['source_directory'] = $this->sourceDirectory;
            $this->profiles[$profile]['target_directory'] = $this->targetDirectory;
        }

        $this->sourceFiles = array(
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
        );

        $this->targetFiles = array(
            $this->targetDirectory . '2016/2016-07-05/test_exif_july_5_2016.jpg',
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
        );

        $this->mediaOrganizer = new MediaOrganizer($this->profiles, 'none');
    }

    public function testInstantiation()
    {
        $this->assertInstanceOf('\Aensley\MediaOrganizer\MediaOrganizer', $this->mediaOrganizer);
    }

    public function testBadOptions()
    {
        $this->expectNotToPerformAssertions();
        $this->mediaOrganizer->organize();
        $this->mediaOrganizer->organize(
            array('test_empty_target' => array('source_directory' => $this->sourceDirectory))
        );
    }

    public function testEmptyInstantiation()
    {
        $this->assertInstanceOf('\Aensley\MediaOrganizer\MediaOrganizer', new MediaOrganizer());
    }

    public function testExif()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(array('images_exif' => $this->profiles['images_exif']));
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
        $this->mediaOrganizer->organize(array('images_fileNames' => $this->profiles['images_fileNames']));
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
        $this->mediaOrganizer->organize(array('videos_fileNames' => $this->profiles['videos_fileNames']));
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
        $this->mediaOrganizer->organize(array('images_modifiedTime' => $this->profiles['images_modifiedTime']));
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
        $this->mediaOrganizer->organize(array('videos_modifiedTime' => $this->profiles['videos_modifiedTime']));
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
        $logger = new Logger('mediaOrganizer');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::EMERGENCY));
        $this->mediaOrganizer = new MediaOrganizer($this->profiles, $logger);
        $this->mediaOrganizer->organize(array('images_exif' => $this->profiles['images_exif']));
    }

    public function testSearchRecursive()
    {
        $this->resetTestFiles();
        $this->mediaOrganizer->organize(array('images_recursive' => $this->profiles['images_recursive']));
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
        $this->mediaOrganizer->organize(array('images_exif' => $this->profiles['images_exif']));
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
            $this->mediaOrganizer->organize(array('images_exif' => $this->profiles['images_exif']));
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

    public function testInvalidLoggerString()
    {
        $organizer = new MediaOrganizer(array(), 'not_a_valid_level');
        $this->assertInstanceOf('\Aensley\MediaOrganizer\MediaOrganizer', $organizer);
    }

    public function testInvalidTargetMask()
    {
        $this->resetTestFiles();
        $profile = $this->profiles['images_exif'];
        $profile['target_mask'] = 'abc';
        $this->mediaOrganizer->organize(array('invalid_mask' => $profile));
        $this->assertFileExists($this->sourceDirectory . 'test_exif_july_5_2016.jpg');
    }

    public function testNoScanOptions()
    {
        $this->resetTestFiles();
        $profile = array(
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => false,
        );
        $this->mediaOrganizer->organize(array('no_scan_options' => $profile));
        $this->assertFileExists($this->sourceDirectory . 'test_exif_july_5_2016.jpg');
    }

    public function testOverwrite()
    {
        $this->resetTestFiles();
        $profile = $this->profiles['images_exif'];
        $profile['overwrite'] = true;
        $this->mediaOrganizer->organize(array('images_exif' => $profile));
        $exifTarget = $this->targetDirectory . '2016/2016-07-05/test_exif_july_5_2016.jpg';
        $this->assertFileExists($exifTarget);

        $this->resetTestFiles(true);
        $this->mediaOrganizer->organize(array('images_exif' => $profile));
        $this->assertFileExists($exifTarget);
        $this->assertFileDoesNotExist($this->targetDirectory . '2016/2016-07-05/test_exif_july_5_2016_0.jpg');
    }

    public function testAllExtensions()
    {
        $this->resetTestFiles();
        $profile = array(
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'valid_extensions' => array(),
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => true,
        );
        $this->mediaOrganizer->organize(array('all_extensions' => $profile));
        $this->assertFileDoesNotExist($this->sourceDirectory . 'wrong.extension');
        $this->assertFileExists($this->targetDirectory . date('Y') . '/' . date('Y-m-d') . '/wrong.extension');
    }

    public function testSymlinkSkipped()
    {
        $this->resetTestFiles();
        $linkPath = $this->sourceDirectory . 'test_symlink.jpg';
        symlink($this->sourceDirectory . 'test_exif_july_5_2016.jpg', $linkPath);
        $this->mediaOrganizer->organize(array('images_exif' => $this->profiles['images_exif']));
        $this->assertTrue(is_link($linkPath));
        unlink($linkPath);
    }

    public function testEchoLogger()
    {
        $this->resetTestFiles();
        $profile = array(
            'source_directory' => $this->sourceDirectory,
            'target_directory' => $this->targetDirectory,
            'scan_exif' => false,
            'file_name_masks' => false,
            'modified_time' => false,
        );
        $organizer = new MediaOrganizer(array(), 'error');
        $this->expectOutputRegex('/ERROR/');
        $organizer->organize(array('no_scan_options' => $profile));
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
        $files = array_diff(scandir($directory), array('.', '..'));
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
