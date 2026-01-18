<?php

require_once __DIR__ . '/../../src/valueobject/EventMetadata.php';

use PHPUnit\Framework\TestCase;

class EventMetadataTest extends TestCase
{
    public function testConstructorValidData()
    {
        $metadata = new EventMetadata('Soccer Match', 'Friendly game at the park');
        $this->assertEquals('Soccer Match', $metadata->title());
        $this->assertEquals('Friendly game at the park', $metadata->description());
    }

    public function testConstructorThrowsOnEmptyTitle()
    {
        $this->expectException(InvalidArgumentException::class);
        new EventMetadata('', 'Description');
    }

    public function testShortDescription()
    {
        $metadata = new EventMetadata('Title', 'This is a long description that should be truncated.');
        $this->assertEquals('This is a long...', $metadata->short(14));
    }
}
