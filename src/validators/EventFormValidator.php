<?php

class EventFormValidator {
    
    public static function validate(array $postData): array {
        $errors = [];
        
        // Extract and trim data
        $title = trim($postData['title'] ?? '');
        $datetime = $postData['datetime'] ?? '';
        $location = $postData['location'] ?? '';
        $skill = $postData['skill'] ?? 'Intermediate';
        $description = trim($postData['desc'] ?? '');
        $participantsType = $postData['participantsType'] ?? 'range';
        
        // Validate title
        if (empty($title)) {
            $errors[] = 'Event name is required';
        }
        
        // Validate datetime
        if (empty($datetime)) {
            $errors[] = 'Date and time is required';
        } else {
            $dateError = self::validateDateTime($datetime);
            if ($dateError) {
                $errors[] = $dateError;
            }
        }
        
        // Validate location
        if (empty($location)) {
            $errors[] = 'Location is required - please choose on map';
        }
        
        // If there are errors, return early
        if (!empty($errors)) {
            return [
                'errors' => $errors,
                'data' => null
            ];
        }
        
        // Parse participants based on type
        $minNeeded = 0;
        $maxPlayers = 0;
        
        if ($participantsType === 'specific') {
            $raw = $postData['playersSpecific'] ?? '';
            $value = ($raw === '' ? 0 : (int)$raw);
            $minNeeded = $value;
            $maxPlayers = $value;
        } elseif ($participantsType === 'minimum') {
            $raw = $postData['playersMin'] ?? '';
            $minNeeded = ($raw === '' ? 0 : (int)$raw);
            $maxPlayers = 0;
        } else { // range
            $rawMin = $postData['playersRangeMin'] ?? '';
            $rawMax = $postData['playersRangeMax'] ?? '';
            $minNeeded = ($rawMin === '' ? 0 : (int)$rawMin);
            $maxPlayers = ($rawMax === '' ? 0 : (int)$rawMax);
        }
        
        // Parse location coords
        $latitude = null;
        $longitude = null;
        if (!empty($location) && str_contains($location, ',')) {
            $parts = array_map('trim', explode(',', $location));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $latitude = (float)$parts[0];
                $longitude = (float)$parts[1];
            }
        }
        
        // Format datetime for DB
        $startTime = null;
        try {
            $dt = new DateTime($datetime);
            $startTime = $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            // Already validated above
        }
        
        // Get skill level ID
        $skillLevelId = self::getSkillLevelId($skill);
        
        // Return validated data
        return [
            'errors' => [],
            'data' => [
                'title' => $title,
                'description' => $description,
                'start_time' => $startTime,
                'location_text' => 'Event Location',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'skill_level_id' => $skillLevelId,
                'min_needed' => $minNeeded,
                'max_players' => $maxPlayers,
            ]
        ];
    }
    

    private static function validateDateTime(string $datetime): ?string {
        try {
            $eventDateTime = new DateTime($datetime);
            if ($eventDateTime < new DateTime('now')) {
                return 'Event date must be in the future';
            }
        } catch (Throwable $e) {
            return 'Invalid date format';
        }
        
        return null;
    }
    
    private static function getSkillLevelId(string $skill): int {
        $skillMap = [
            'Beginner' => 1,
            'Intermediate' => 2,
            'Advanced' => 3,
        ];
        
        return $skillMap[$skill] ?? 2;
    }
}
