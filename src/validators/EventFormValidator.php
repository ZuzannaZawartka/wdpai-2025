<?php

class EventFormValidator {
    
    public static function validate(array $postData, ?int $currentParticipantsCount = null): array {
        $errors = [];
        
        // Extract and trim data
        $title = trim($postData['title'] ?? '');
        $datetime = $postData['datetime'] ?? '';
        $location = $postData['location'] ?? '';
        $locationText = trim((string)($postData['location_text'] ?? ''));
        $skill = $postData['skill'] ?? 'Intermediate';
        $sport = $postData['sport'] ?? '';
        $description = trim($postData['desc'] ?? '');
        $participantsType = $postData['participantsType'] ?? 'range';
        
        // Validate title
        if (empty($title)) {
            $errors[] = 'Event name is required';
        }
        
        // Validate sport
        $sportId = (int)$sport;
        if ($sportId <= 0) {
            $errors[] = 'Please select a sport';
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

        // Validate participants inputs
        if (!in_array($participantsType, ['specific', 'minimum', 'range'], true)) {
            $errors[] = 'Invalid participants type';
        } else {
            if ($participantsType === 'specific') {
                $raw = trim((string)($postData['playersSpecific'] ?? ''));
                if ($raw === '' || !ctype_digit($raw) || (int)$raw <= 0) {
                    $errors[] = 'Please provide a valid number of players';
                }
            } elseif ($participantsType === 'minimum') {
                $raw = trim((string)($postData['playersMin'] ?? ''));
                if ($raw === '' || !ctype_digit($raw) || (int)$raw <= 0) {
                    $errors[] = 'Please provide a valid minimum number of players';
                }
            } else { // range
                $rawMin = trim((string)($postData['playersRangeMin'] ?? ''));
                $rawMax = trim((string)($postData['playersRangeMax'] ?? ''));

                if ($rawMin === '' || $rawMax === '') {
                    $errors[] = 'Please provide both min and max players for the range';
                } elseif (!ctype_digit($rawMin) || !ctype_digit($rawMax)) {
                    $errors[] = 'Players range must be numeric';
                } else {
                    $minVal = (int)$rawMin;
                    $maxVal = (int)$rawMax;
                    if ($minVal <= 0 || $maxVal <= 0) {
                        $errors[] = 'Players range values must be greater than 0';
                    } elseif ($minVal > $maxVal) {
                        $errors[] = 'Players range min cannot be greater than max';
                    }
                }
            }
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
            $value = (int)trim((string)($postData['playersSpecific'] ?? '0'));
            $minNeeded = $value;
            $maxPlayers = $value;
        } elseif ($participantsType === 'minimum') {
            $minNeeded = (int)trim((string)($postData['playersMin'] ?? '0'));
            $maxPlayers = 0;
        } else { // range
            $minNeeded = (int)trim((string)($postData['playersRangeMin'] ?? '0'));
            $maxPlayers = (int)trim((string)($postData['playersRangeMax'] ?? '0'));
        }
        
        // Validate max_players is not less than current participants count (only when editing)
        if ($currentParticipantsCount !== null && $maxPlayers > 0 && $maxPlayers < $currentParticipantsCount) {
            $errors[] = "Nie można zmniejszyć limitu uczestników poniżej aktualnej liczby dołączonych osób ({$currentParticipantsCount})";
        }
        
        // If there are errors after this validation, return early
        if (!empty($errors)) {
            return [
                'errors' => $errors,
                'data' => null
            ];
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
                'sport_id' => $sportId,
                'start_time' => $startTime,
                'location_text' => ($locationText !== '' ? substr($locationText, 0, 255) : substr((string)$location, 0, 255)),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'level_id' => $skillLevelId,
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
