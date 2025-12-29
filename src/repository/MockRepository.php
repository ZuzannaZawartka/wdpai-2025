<?php

class MockRepository {
    private const CURRENT_USER_ID = 1001;

    // Mock users catalog to resolve organizer by ownerId
    public static function users(): array {
        return [
            1001 => [ 'name' => 'John Doe', 'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8' ],
            2001 => [ 'name' => 'Alex Smith', 'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8' ],
            2002 => [ 'name' => 'Jamie Lee', 'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8' ],
            2003 => [ 'name' => 'Taylor King', 'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8' ],
        ];
    }

    // Catalogs
    public static function levels(): array {
        return [
            1 => 'Beginner',
            2 => 'Intermediate',
            3 => 'Advanced',
        ];
    }

    private static function levelColors(): array {
        return [
            1 => '#22c55e',
            2 => '#eab308',
            3 => '#ef4444',
        ];
    }

    public static function sportsCatalog(): array {
        return [
            1 => 'Soccer',
            2 => 'Basketball',
            3 => 'Tennis',
            4 => 'Running',
            5 => 'Cycling',
        ];
    }

    // Unified events dataset with owner and normalized level/sport references
    public static function events(): array {
        return [
            [
                'id' => 1,
                'title' => 'Riverfront 7v7 Soccer',
                'isoDate' => '2025-11-02T09:30:00',
                'dateText' => 'Sun, Nov 2, 9:30 AM',
                'location' => 'Riverfront Field, New York, NY',
                'coords' => '40.7005, -74.0120',
                'sportId' => 1,
                'levelId' => 2,
                'imageUrl' => 'https://picsum.photos/seed/river-soccer/800/600',
                'desc' => 'Friendly small-sided match by the river.',
                'ownerId' => 2001,
                'maxPlayers' => 12,
                'minNeeded' => 6
            ],
            [
                'id' => 2,
                'title' => 'Midtown Hoops Night',
                'isoDate' => '2025-11-03T19:30:00',
                'dateText' => 'Mon, Nov 3, 7:30 PM',
                'location' => 'Midtown Court, New York, NY',
                'coords' => '40.7540, -73.9845',
                'sportId' => 2,
                'levelId' => 3,
                'imageUrl' => 'https://picsum.photos/seed/midtown-hoops/800/600',
                'desc' => 'Fast-paced 5-on-5 under the lights.',
                'ownerId' => 2002,
                'maxPlayers' => 10,
                'minNeeded' => 8
            ],
            [
                'id' => 3,
                'title' => 'Beginner Tennis Social',
                'isoDate' => '2025-11-04T15:00:00',
                'dateText' => 'Tue, Nov 4, 3:00 PM',
                'location' => 'Community Courts, New York, NY',
                'coords' => '40.7308, -73.9973',
                'sportId' => 3,
                'levelId' => 1,
                'imageUrl' => 'https://picsum.photos/seed/tennis-social/800/600',
                'desc' => 'No pressure hitting session for new players.',
                'ownerId' => 2003,
                'maxPlayers' => 4,
                'minNeeded' => 2
            ],
            [
                'id' => 4,
                'title' => 'Central Park Morning Kickabout',
                'isoDate' => '2025-10-28T10:00:00',
                'dateText' => 'Sat, Oct 28, 10:00 AM',
                'location' => 'Central Park, New York, NY',
                'coords' => '40.7829, -73.9654',
                'sportId' => 1,
                'levelId' => 2,
                'imageUrl' => 'https://picsum.photos/seed/central-kickabout/800/600',
                'desc' => 'Casual morning run; all friendly vibes.',
                'ownerId' => 2001,
                'maxPlayers' => 12,
                'minNeeded' => 6
            ],
            [
                'id' => 5,
                'title' => 'City Arena Evening Run',
                'isoDate' => '2025-10-30T19:00:00',
                'dateText' => 'Mon, Oct 30, 7:00 PM',
                'location' => 'City Arena, New York, NY',
                'coords' => '40.7484, -73.9857',
                'sportId' => 2,
                'levelId' => 3,
                'imageUrl' => 'https://picsum.photos/seed/arena-run/800/600',
                'desc' => 'High-energy 5v5 session; quick subs.',
                'ownerId' => 2002,
                'maxPlayers' => 10,
                'minNeeded' => 8
            ],
            [
                'id' => 6,
                'title' => 'My Futsal Scrimmage',
                'isoDate' => '2025-11-05T18:00:00',
                'dateText' => 'Wed, Nov 5, 6:00 PM',
                'location' => 'Chelsea Indoor, New York, NY',
                'coords' => '40.7420, -73.9920',
                'sportId' => 1,
                'levelId' => 2,
                'imageUrl' => 'https://picsum.photos/seed/futsal-scrimmage/800/600',
                'desc' => 'Small court futsal, bring indoor shoes.',
                'ownerId' => self::CURRENT_USER_ID,
                'maxPlayers' => 10,
                'minNeeded' => 6
            ],
            [
                'id' => 7,
                'title' => 'My Casual Tennis Rally',
                'isoDate' => '2025-11-06T14:00:00',
                'dateText' => 'Thu, Nov 6, 2:00 PM',
                'location' => 'Hudson Courts, New York, NY',
                'coords' => '40.7280, -74.0059',
                'sportId' => 3,
                'levelId' => 1,
                'imageUrl' => 'https://picsum.photos/seed/casual-tennis/800/600',
                'desc' => 'Relaxed rally; practice serves and volleys.',
                'ownerId' => self::CURRENT_USER_ID,
                'maxPlayers' => 4,
                'minNeeded' => 2
            ],
        ];
    }

    // Separate participants mapping (join-table style)
    public static function eventParticipants(): array {
        return [
            1 => [1002, 1003, 1004, 1006],
            2 => [1007, 1008, 1009, 1010, 1011, 1012, 1013],
            3 => [1015, 1016],
            4 => [1002, 1003, 1004, 1005, 1006, 1001],
            5 => [1003, 1007, 1008, 1009, 1010, 1011, 1012, 1013, 1001],
            6 => [1002, 1003, 1004],
            7 => [1015],
        ];
    }

    // Dashboard upcoming = nearest two joined
    public static function upcomingEvents(): array {
        $joined = self::joinedMatches(self::CURRENT_USER_ID);
        usort($joined, function($a, $b) {
            $da = isset($a['isoDate']) ? strtotime($a['isoDate']) : 0;
            $db = isset($b['isoDate']) ? strtotime($b['isoDate']) : 0;
            return $da <=> $db;
        });
        $nearestTwo = array_slice($joined, 0, 2);
        return array_map(function($ev) {
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'dateText' => $ev['dateText'],
                'location' => $ev['location'],
                'coords' => $ev['coords'] ?? null,
                'imageUrl' => $ev['imageUrl'] ?? ''
            ];
        }, $nearestTwo);
    }

    public static function suggestions(): array {
        return [
            [
                'id' => 101,
                'title' => 'Weekly Running Club',
                'sport' => 'Running',
                'distanceText' => 'River Trail (2km away)',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDBN4upX-dD3omhsHhGASurq2bQv8P05U2al7p0_6T8GJyXb5vvHwuEKLE3Aaxx7VnVURvJVTJ4Q48_-sqClJDzrxi-nIv6sxsgqt1RslKEjWhqlVBzAoWogk_ucJPIEhvMzYlC1EGUkmVPlR1hB_uUH19RZ7asJL23s5cjan5wOrxbnTetcPbDtGB22HyRDk457AEzNom2w09rRj7wOnzVqp3tIaD2bIUTvQniNLyaDTBDQ9l2ijS1GC94aEGV6_-cQOPgWgejHCDk',
                'cta' => 'Join Match'
            ],
            [
                'id' => 102,
                'title' => 'Mountain Biking',
                'sport' => 'Cycling',
                'distanceText' => 'Hillside Trails (5km away)',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAGCAqyYqW55OTcWiaX-4FMNzaR4kmyE7LETdG4imPjzKV1LqEy2y-2ss6bNhcaKGLv8F4nwsnQgtVnVS6Ilw-3MTsx25gYt3iWfG0fLhcwYLLRCVQPkyGcrMO7EUGLsrqDm8WyD3lQ0sej8vCpbCpIfjGQhJG7H4q5LhTk1yXRWtBZBi3ygcCpMXElit8RGUIULLKvIz5t6rAjtSouRQz9jwb5NAUyNnqsVmttyXAvIeFFBKwPgjMMKLCPyNb1QxZ-WD-Ft3xgI5SI',
                'cta' => 'Join Match'
            ],
            [
                'id' => 103,
                'title' => 'Tennis Tournament',
                'sport' => 'Tennis',
                'distanceText' => 'Community Courts (1km away)',
                'imageUrl' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCOdQng9qujhWHOAXiXeGemeWZRiFWZLM6d-1bjEItXNzJR0DhrmEE3_ulCj4x5pIVliCBsKTuhCQpliQh4pAMqx6_Z-v7sc2KH_73rlEE9UUDPFVS03PWZB3xYNwjq-46u37VzCUCGbQuPVczvaHEgCU00M4QyMmHU0R3PeD8sV2scqSwdvoCQvct8G8-AM6CKpnKXxyv6YN4-KxwqyjEf-_J1F0AZI2cAykEm9CrnlqqM02Ub-lgo7RX1YuHNMPKP-MLGtlXRzQ5W',
                'cta' => 'Join Match'
            ],
        ];
    }

    public static function favouriteSports(int $currentUserId = null): array {
        $uid = $currentUserId ?? self::CURRENT_USER_ID;
        // User-specific favourites (mocked). Fallback to a default set.
        $byUser = [
            1001 => ['Running', 'Cycling', 'Tennis'],
            2001 => ['Soccer', 'Tennis', 'Running'],
            2002 => ['Basketball', 'Cycling', 'Soccer'],
            2003 => ['Tennis', 'Running', 'Yoga'],
        ];
        $icons = [
            'Running' => '🏃',
            'Cycling' => '🚴',
            'Tennis'  => '🎾',
            'Soccer'  => '⚽',
            'Basketball' => '🏀',
            'Yoga' => '🧘',
            'Volleyball' => '🏐',
            'Gym' => '🏋️',
            'Other' => '➕',
        ];

        // Count nearby events per sport using mock events()
        $catalog = self::sportsCatalog(); // id => name
        $nameToId = array_flip($catalog);
        $counts = [];
        foreach (self::events() as $ev) {
            $sid = $ev['sportId'] ?? null;
            $name = $sid && isset($catalog[$sid]) ? $catalog[$sid] : null;
            if ($name) { $counts[$name] = ($counts[$name] ?? 0) + 1; }
        }

        $list = $byUser[$uid] ?? ['Running', 'Cycling', 'Tennis'];
        return array_map(function($name) use ($icons, $counts) {
            $nearby = $counts[$name] ?? 0;
            $nearbyText = $nearby > 0 ? ("{$nearby} events nearby") : 'No events nearby';
            return [
                'icon' => $icons[$name] ?? '➕',
                'name' => $name,
                'nearbyText' => $nearbyText,
            ];
        }, $list);
    }

    public static function sportsMatches(int $currentUserId = null, array $selectedSports = [], ?string $level = null, ?array $center = null, ?float $radiusKm = null): array {
        $uid = $currentUserId ?? self::CURRENT_USER_ID;
        $catalog = self::sportsCatalog(); // id => name
        $levels = self::levels();
        $events = array_filter(self::events(), function($ev) use ($uid, $catalog, $levels, $selectedSports, $level, $center, $radiusKm) {
            if (($ev['ownerId'] ?? null) === $uid) { return false; }
            // Sports filter
            if (!empty($selectedSports)) {
                $sid = $ev['sportId'] ?? null;
                $name = $sid && isset($catalog[$sid]) ? $catalog[$sid] : null;
                if (!$name || !in_array($name, $selectedSports, true)) { return false; }
            }
            // Level filter
            if ($level !== null) {
                $lid = $ev['levelId'] ?? null;
                $lname = $lid && isset($levels[$lid]) ? $levels[$lid] : null;
                if ($lname !== $level) { return false; }
            }
            // Location radius filter
            if ($center && $radiusKm !== null) {
                $coords = $ev['coords'] ?? '';
                if (!is_string($coords) || $coords === '') { return false; }
                $parts = array_map('trim', explode(',', $coords));
                if (count($parts) !== 2) { return false; }
                $lat2 = (float)$parts[0];
                $lng2 = (float)$parts[1];
                $dist = self::distanceKm((float)$center[0], (float)$center[1], $lat2, $lng2);
                if (!is_finite($dist) || $dist > $radiusKm) { return false; }
            }
            return true;
        });
        $levels = self::levels();
        $colors = self::levelColors();
        $participants = self::eventParticipants();
        return array_map(function($ev) use ($levels, $colors, $participants) {
            $current = count($participants[$ev['id']] ?? []);
            $playersText = $current . '/' . ($ev['maxPlayers'] ?? $current) . ' Players';
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'desc' => $ev['desc'] ?? '',
                'players' => $playersText,
                'level' => $levels[$ev['levelId']] ?? 'Intermediate',
                'levelColor' => $colors[$ev['levelId']] ?? '#eab308',
                'imageUrl' => $ev['imageUrl'] ?? ''
            ];
        }, array_values($events));
    }

    private static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $R = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    public static function joinedMatches(int $currentUserId = null): array {
        $uid = $currentUserId ?? self::CURRENT_USER_ID;
        $parts = self::eventParticipants();
        $events = array_filter(self::events(), function($ev) use ($uid, $parts) {
            return in_array($uid, $parts[$ev['id']] ?? [], true);
        });
        $levels = self::levels();
        $colors = self::levelColors();
        return array_map(function($ev) use ($levels, $colors, $parts) {
            $current = count($parts[$ev['id']] ?? []);
            $playersText = $current . '/' . ($ev['maxPlayers'] ?? $current) . ' Players';
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'dateText' => $ev['dateText'],
                'isoDate' => $ev['isoDate'],
                'location' => $ev['location'],
                'desc' => $ev['desc'] ?? '',
                'players' => $playersText,
                'level' => $levels[$ev['levelId']] ?? 'Intermediate',
                'levelColor' => $colors[$ev['levelId']] ?? '#eab308',
                'imageUrl' => $ev['imageUrl'] ?? ''
            ];
        }, array_values($events));
    }

    public static function myEvents(int $currentUserId = null): array {
        $uid = $currentUserId ?? self::CURRENT_USER_ID;
        $events = array_filter(self::events(), function($ev) use ($uid) {
            return ($ev['ownerId'] ?? null) === $uid;
        });
        $levels = self::levels();
        $colors = self::levelColors();
        $participants = self::eventParticipants();
        return array_map(function($ev) use ($levels, $colors, $participants) {
            $current = count($participants[$ev['id']] ?? []);
            $playersText = $current . '/' . ($ev['maxPlayers'] ?? $current) . ' Players';
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'players' => $playersText,
                'level' => $levels[$ev['levelId']] ?? 'Intermediate',
                'levelColor' => $colors[$ev['levelId']] ?? '#eab308',
                'imageUrl' => $ev['imageUrl'] ?? ''
            ];
        }, array_values($events));
    }

    public static function getEventById(int $id): ?array {
        $levels = self::levels();
        $participants = self::eventParticipants();
        $users = self::users();
        foreach (self::events() as $ev) {
            if (($ev['id'] ?? 0) === $id) {
                $current = count($participants[$ev['id']] ?? []);
                $owner = $ev['ownerId'] ?? null;
                $organizer = $owner && isset($users[$owner]) ? $users[$owner] : [ 'name' => 'Unknown', 'avatar' => '' ];
                return [
                    'id' => $ev['id'],
                    'title' => $ev['title'],
                    'location' => $ev['location'] ?? (self::sportsCatalog()[$ev['sportId']] ?? 'Unknown location'),
                    'coords' => $ev['coords'] ?? null,
                    'dateTime' => $ev['dateText'] ?? 'TBD',
                    'skillLevel' => $levels[$ev['levelId']] ?? 'Intermediate',
                    'organizer' => $organizer,
                    'participants' => [
                        'current' => $current,
                        'max' => $ev['maxPlayers'] ?? $current,
                        'minNeeded' => $ev['minNeeded'] ?? 0
                    ],
                ];
            }
        }
        return null;
    }
}
