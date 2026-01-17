<?php

require_once __DIR__ . '/../../src/dto/EventSearchRequestDTO.php';

use PHPUnit\Framework\TestCase;

class EventSearchRequestDTOTest extends TestCase
{
    public function testFromRequestParsesCoordinates()
    {
        $request = [
            'loc' => ' 52.2297, 21.0122 ',
            'radius' => '25.5'
        ];

        $dto = EventSearchRequestDTO::fromRequest($request);

        $this->assertTrue($dto->hasCoordinates());
        $this->assertEquals(52.2297, $dto->latitude);
        $this->assertEquals(21.0122, $dto->longitude);
        $this->assertEquals(25.5, $dto->radius);
    }

    public function testFromRequestDefaults()
    {
        $dto = EventSearchRequestDTO::fromRequest([]);

        $this->assertFalse($dto->hasCoordinates());
        $this->assertEquals(1, $dto->page);
        $this->assertEquals(6, $dto->limit);
        $this->assertEquals('Any', $dto->level);
    }

    public function testPaginationBoundaries()
    {
        $request = [
            'page' => '-5',
            'limit' => 'abc'
        ];

        $dto = EventSearchRequestDTO::fromRequest($request);

        $this->assertEquals(1, $dto->page); 
        $this->assertEquals(6, $dto->limit); 
    }
}
