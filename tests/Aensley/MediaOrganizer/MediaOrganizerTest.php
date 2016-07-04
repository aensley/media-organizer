<?php

use \Aensley\MediaOrganizer\MediaOrganizer;

class MediaOrganizerTest extends \PHPUnit_Framework_TestCase {

	public $dataDirectory;
	public $profiles;
	public $mediaOrganizer;

	protected function setUp() {
		$this->dataDirectory = getcwd() . '/data/';
		$this->profiles = array(
			'images1' => array(
				'source_directory' => $this->dataDirectory,
				'target_directory' => $this->dataDirectory . 'target/',
			),
			'images2' => array(
				'source_directory' => $this->dataDirectory,
				'target_directory' => $this->dataDirectory . 'target/',
				'modified_time' => true,
			),
			'images3' => array(
				'source_directory' => $this->dataDirectory,
				'target_directory' => $this->dataDirectory . 'target/',
				'created_time' => true,
			),
		);
		$this->createTestImages();
		$this->mediaOrganizer = new MediaOrganizer($this->profiles);
	}

	public function testInstantiation() {
		$this->assertInstanceOf('\Aensley\MediaOrganizer\MediaOrganizer', $this->mediaOrganizer);
	}

	public function testEmptyInstantiation() {
		$this->assertInstanceOf('\Aensley\MediaOrganizer\MediaOrganizer', new MediaOrganizer);
	}

	private function createTestImages()
	{
		mkdir($this->dataDirectory);
		touch($this->dataDirectory . 'matches_nothing.jpg');
		touch($this->dataDirectory . 'valid_' . date('Ymd') . '.jpg');
		touch($this->dataDirectory . 'valid_' . date('Y-m-d') . '.jpg');
		touch($this->dataDirectory . 'invalid_' . date('ymd') . '.jpg');
	}
}
