<?php

require_once __DIR__ . '/../../src/validators/EventFormValidator.php';

use PHPUnit\Framework\TestCase;

class EventFormValidatorTest extends TestCase
{
    public function testValidateMissingRequiredFields()
    {
        $postData = [
            'title' => '',
            'datetime' => '',
            'location' => '',
            'sport' => '0'
        ];

        $result = EventFormValidator::validate($postData);

        $this->assertNotEmpty($result['errors']);
        $this->assertContains('Event name is required', $result['errors']);
        $this->assertContains('Date and time is required', $result['errors']);
        $this->assertContains('Please select a sport', $result['errors']);
    }

    public function testValidateInvalidParticipantsRange()
    {
        $postData = [
            'title' => 'Test Event',
            'datetime' => date('Y-m-d H:i', strtotime('+1 day')),
            'location' => '52.2, 21.0',
            'sport' => '1',
            'participantsType' => 'range',
            'playersRangeMin' => '20',
            'playersRangeMax' => '10'
        ];

        $result = EventFormValidator::validate($postData);

        $this->assertContains('Players range min cannot be greater than max', $result['errors']);
    }

    public function testValidateSuccessfulParsing()
    {
        $nextWeek = date('Y-m-d H:i', strtotime('+7 days'));
        $postData = [
            'title' => 'Soccer Game ',
            'datetime' => $nextWeek,
            'location' => '52.2297, 21.0122',
            'sport' => '1',
            'skill' => 'Advanced',
            'desc' => 'Fun game ',
            'participantsType' => 'specific',
            'playersSpecific' => '12'
        ];

        $result = EventFormValidator::validate($postData);

        $this->assertEmpty($result['errors']);
        $this->assertEquals('Soccer Game', $result['data']['title']);
        $this->assertEquals(12, $result['data']['max_players']);
        $this->assertEquals(12, $result['data']['min_needed']);
        $this->assertEquals(3, $result['data']['level_id']); 
    }
}
