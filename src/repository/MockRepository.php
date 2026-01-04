<?php

class MockRepository {

    private static ?array $eventsData = null;

    private static function ensureSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    private static function currentUserId(): ?int {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        return null;
    }

    public static function users(): array {
        return [
            41 => [
                'id' => 41,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'a@example.com',
                'birthDate' => '1990-05-15',
                'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8',
                'location' => ['lat' => 40.7580, 'lng' => -73.9855],
                'favouriteSports' => [1, 3, 4]
            ],
            1001 => [
                'id' => 1001,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'john.doe@example.com',
                'birthDate' => '1988-03-20',
                'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8',
                'location' => ['lat' => 40.7128, 'lng' => -74.0060],
                'favouriteSports' => [1, 3, 4]
            ],
            2001 => [
                'id' => 2001,
                'firstName' => 'Alex',
                'lastName' => 'Smith',
                'email' => 'alex.smith@example.com',
                'birthDate' => '1992-07-10',
                'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8',
                'location' => ['lat' => 40.7589, 'lng' => -73.9851],
                'favouriteSports' => [1, 3, 4]
            ],
            2002 => [
                'id' => 2002,
                'firstName' => 'Jamie',
                'lastName' => 'Lee',
                'email' => 'jamie.lee@example.com',
                'birthDate' => '1995-11-22',
                'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8',
                'location' => ['lat' => 40.7549, 'lng' => -73.9840],
                'favouriteSports' => [2, 5, 1]
            ],
            2003 => [
                'id' => 2003,
                'firstName' => 'Taylor',
                'lastName' => 'King',
                'email' => 'taylor.king@example.com',
                'birthDate' => '1993-01-08',
                'avatar' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBAScERWuqlLWv7xDKAkq0vfJxFdtSjQSK-FBJ1endTel7Mo7aFi0qPk4gxnCNCb1jcOw5bTCZJiVDLAQBhlpBj8U1-gi_i4oGP1rXh5zh8C2QBXlIswW7gAzNq7lyqcg86rnO-VrLXkVrwY09NH7MSdq5pIlpsLh7-jmorWShz4aYci8ypqMqV2CSd1MG9qj87bzPvc2crbK3BFhR1PbJfg5TBMCDu4WMtUeUa_ztoq4KYvLtAfafiy448mlV76OCmMn4-TgmdbJx8',
                'location' => ['lat' => 40.7614, 'lng' => -73.9776],
                'favouriteSports' => [3, 4]
            ],
        ];
    }

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
            1 => [
                'id' => 1,
                'name' => 'Soccer',
                'icon' => '⚽',
            ],
            2 => [
                'id' => 2,
                'name' => 'Basketball',
                'icon' => '🏀',
            ],
            3 => [
                'id' => 3,
                'name' => 'Tennis',
                'icon' => '🎾',
            ],
            4 => [
                'id' => 4,
                'name' => 'Running',
                'icon' => '🏃',
            ],
            5 => [
                'id' => 5,
                'name' => 'Cycling',
                'icon' => '🚴',
            ],
        ];
    }

    // Unified events dataset with owner and normalized level/sport references
    public static function events(): array {
        self::ensureSession();

        if (self::$eventsData !== null) {
            return self::$eventsData;
        }

        $hardcodedEvents = [
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
                'ownerId' => 41,
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
                'ownerId' => 41,
                'maxPlayers' => 4,
                'minNeeded' => 2
            ],
            [
                'id' => 8,
                'title' => 'Morning Run Group',
                'isoDate' => '2026-02-08T07:00:00',
                'dateText' => 'Sun, Feb 8, 7:00 AM',
                'location' => 'East River Park, New York, NY',
                'coords' => '40.7150, -73.9733',
                'sportId' => 4,
                'levelId' => 1,
                'imageUrl' => 'https://picsum.photos/seed/morning-run/800/600',
                'desc' => 'Easy pace morning run, all fitness levels welcome.',
                'ownerId' => 2003,
                'maxPlayers' => 20,
                'minNeeded' => 3
            ],
            [
                'id' => 9,
                'title' => 'Weekend Cycling Tour',
                'isoDate' => '2026-02-09T10:00:00',
                'dateText' => 'Mon, Feb 9, 10:00 AM',
                'location' => 'Hudson River Greenway, New York, NY',
                'coords' => '40.7245, -73.9754',
                'sportId' => 5,
                'levelId' => 2,
                'imageUrl' => 'https://picsum.photos/seed/cycling-tour/800/600',
                'desc' => 'Scenic 20 mile ride through the city.',
                'ownerId' => 2001,
                'maxPlayers' => 15,
                'minNeeded' => 4
            ],
            [
                'id' => 10,
                'title' => 'Advanced Basketball League',
                'isoDate' => '2025-11-10T18:00:00',
                'dateText' => 'Mon, Nov 10, 6:00 PM',
                'location' => 'Asphalt Green, New York, NY',
                'coords' => '40.7649, -73.9593',
                'sportId' => 2,
                'levelId' => 3,
                'imageUrl' => 'https://picsum.photos/seed/basketball-league/800/600',
                'desc' => 'Competitive league play, experienced players only.',
                'ownerId' => 2002,
                'maxPlayers' => 12,
                'minNeeded' => 10
            ],
            [
                'id' => 11,
                'title' => 'Beginner Soccer Clinic',
                'isoDate' => '2025-11-11T15:00:00',
                'dateText' => 'Tue, Nov 11, 3:00 PM',
                'location' => 'Pier 1, New York, NY',
                'coords' => '40.7061, -74.0088',
                'sportId' => 1,
                'levelId' => 1,
                'imageUrl' => 'https://picsum.photos/seed/soccer-clinic/800/600',
                'desc' => 'Learn the basics of soccer in a fun environment.',
                'ownerId' => 2003,
                'maxPlayers' => 16,
                'minNeeded' => 8
            ],
            [
                'id' => 12,
                'title' => 'Tennis Doubles Tournament',
                'isoDate' => '2025-11-12T14:00:00',
                'dateText' => 'Wed, Nov 12, 2:00 PM',
                'location' => 'West Side Tennis Club, New York, NY',
                'coords' => '40.7668, -73.8245',
                'sportId' => 3,
                'levelId' => 2,
                'imageUrl' => 'https://picsum.photos/seed/tennis-tournament/800/600',
                'desc' => 'Mixed doubles tournament, bring a partner.',
                'ownerId' => 2001,
                'maxPlayers' => 8,
                'minNeeded' => 4
            ],
            [
                'id' => 13,
                'title' => 'Evening Run - 10K',
                'isoDate' => '2026-02-13T18:00:00',
                'dateText' => 'Fri, Feb 13, 6:00 PM',
                'location' => 'Brooklyn Bridge Park, New York, NY',
                'coords' => '40.6974, -73.9876',
                'sportId' => 4,
                'levelId' => 2,
                'imageUrl' => 'https://picsum.photos/seed/evening-run/800/600',
                'desc' => '10K run with some hills, moderate pace.',
                'ownerId' => 2002,
                'maxPlayers' => 25,
                'minNeeded' => 5
            ],
            [
                'id' => 14,
                'title' => 'Road Cycling Adventure',
                'isoDate' => '2026-02-14T09:00:00',
                'dateText' => 'Sat, Feb 14, 9:00 AM',
                'location' => 'Westchester County, New York, NY',
                'coords' => '40.8448, -73.8648',
                'sportId' => 5,
                'levelId' => 3,
                'imageUrl' => 'https://picsum.photos/seed/road-cycling/800/600',
                'desc' => 'Challenging 40 mile road ride for experienced cyclists.',
                'ownerId' => 2003,
                'maxPlayers' => 12,
                'minNeeded' => 3
            ],
            ];
        
        self::$eventsData = $hardcodedEvents;
        
        if (isset($_SESSION['created_events']) && is_array($_SESSION['created_events'])) {
            self::$eventsData = array_merge(self::$eventsData, $_SESSION['created_events']);
        }
    
        if (isset($_SESSION['edited_events']) && is_array($_SESSION['edited_events'])) {
            foreach (self::$eventsData as &$ev) {
                $evId = $ev['id'] ?? null;
                if ($evId && isset($_SESSION['edited_events'][$evId])) {
                    $ev = $_SESSION['edited_events'][$evId];
                }
            }
        }
        
        return self::$eventsData;
    }


    public static function eventParticipants(): array {
        $participants = [
            1 => [41, 1002, 1003, 1004, 1006],
            2 => [1007, 1008, 1009, 1010, 1011, 1012, 1013],
            3 => [41, 1015, 1016],
            4 => [41, 1002, 1003, 1004, 1005, 1006],
            5 => [41, 1003, 1007, 1008, 1009, 1010, 1011, 1012, 1013],
            6 => [1002, 1003, 1004],
            7 => [41, 1015],
            8 => [2001, 2002],
            9 => [41, 1002],
            10 => [2003],
            11 => [41, 1001],
            12 => [2002, 2003],
            13 => [41, 1003, 1004],
            14 => [2001, 2002, 2003],
        ];
        

        foreach (self::events() as $ev) {
            $id = $ev['id'] ?? null;
            if ($id && !isset($participants[$id])) {
                $participants[$id] = [];
            }
        }
        
        return $participants;
    }

    // Dashboard upcoming = nearest two joined
    public static function upcomingEvents(?int $currentUserId = null): array {
        if ($currentUserId === null) {
            return [];
        }
        $joined = self::joinedMatches($currentUserId);
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

    public static function suggestions(?int $currentUserId = null, int $limit = 3): array {
        $uid = $currentUserId ?? self::currentUserId();
        $users = self::users();
        if (!$uid || !isset($users[$uid])) {
            return [];
        }

        $user = $users[$uid];
        $location = $user['location'] ?? null;
        $favourites = $user['favouriteSports'] ?? [];

        if (empty($favourites) || !$location || !isset($location['lat'], $location['lng'])) {
            return [];
        }

        $events = array_filter(self::events(), function ($ev) use ($favourites) {
            $sid = $ev['sportId'] ?? null;
            return $sid !== null && in_array($sid, $favourites, true) && !empty($ev['coords']);
        });

        $catalog = self::sportsCatalog();
        $enriched = [];

        foreach ($events as $ev) {
            $coords = array_map('trim', explode(',', $ev['coords'] ?? ''));
            if (count($coords) !== 2) {
                continue;
            }

            $lat2 = (float)$coords[0];
            $lng2 = (float)$coords[1];
            $dist = self::distanceKm((float)$location['lat'], (float)$location['lng'], $lat2, $lng2);

            if (!is_finite($dist)) {
                continue;
            }

            $sportName = $catalog[$ev['sportId']]['name'] ?? 'Sport';
            $distanceText = sprintf('%s (%.1f km away)', $ev['location'], round($dist, 1));

            $enriched[] = [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'sport' => $sportName,
                'distanceText' => $distanceText,
                'imageUrl' => $ev['imageUrl'] ?? '',
                'cta' => 'See Details',
                'distanceKm' => $dist
            ];
        }

        usort($enriched, function ($a, $b) {
            $da = $a['distanceKm'] ?? INF;
            $db = $b['distanceKm'] ?? INF;
            return $da <=> $db;
        });

        $trimmed = array_slice($enriched, 0, $limit);

        return array_map(function ($item) {
            unset($item['distanceKm']);
            return $item;
        }, $trimmed);
    }

    public static function favouriteSports(?int $currentUserId = null): array {
        $uid = $currentUserId ?? self::currentUserId();
        
        $users = self::users();
        $userFavourites = $users[$uid]['favouriteSports'] ?? [];
        
        $catalog = self::sportsCatalog();
        
        $counts = [];
        foreach (self::events() as $ev) {
            $sid = $ev['sportId'] ?? null;
            if ($sid && in_array($sid, $userFavourites, true)) {
                $counts[$sid] = ($counts[$sid] ?? 0) + 1;
            }
        }
        
        return array_map(function($sportId) use ($catalog, $counts) {
            $sport = $catalog[$sportId] ?? null;
            if (!$sport) return null;
            
            $nearby = $counts[$sportId] ?? 0;
            $nearbyText = $nearby > 0 ? ("{$nearby} events nearby") : 'No events nearby';
            
            return [
                'icon' => $sport['icon'],
                'name' => $sport['name'],
                'nearbyText' => $nearbyText,
            ];
        }, $userFavourites);
    }

    public static function sportsMatches(int $currentUserId = null, array $selectedSports = [], ?string $level = null, ?array $center = null, ?float $radiusKm = null): array {
        $uid = $currentUserId ?? self::currentUserId();
        $catalog = self::sportsCatalog();
        $levels = self::levels();
        $events = array_filter(self::events(), function($ev) use ($uid, $selectedSports, $level, $center, $radiusKm) {
            // Sports filter - compare by ID not by name
            if (!empty($selectedSports)) {
                $sid = $ev['sportId'] ?? null;
                if (!$sid || !in_array($sid, $selectedSports, true)) { return false; }
            }
            // Level filter
            if ($level !== null) {
                $lid = $ev['levelId'] ?? null;
                $levels = self::levels();
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
            $current = count($participants[$ev['id']]);
            // Handle different participant types
            if ($ev['maxPlayers'] === null) {
                // Minimum mode - show "min+ Players"
                $playersText = $ev['minNeeded'] . '+ Players';
            } elseif ($ev['minNeeded'] === $ev['maxPlayers']) {
                // Specific mode - show "exact Players"
                $playersText = $ev['minNeeded'] . ' Players';
            } else {
                // Range mode - show "current/min-max Players"
                $playersText = $current . '/' . $ev['minNeeded'] . '-' . $ev['maxPlayers'] . ' Players';
            }
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'desc' => $ev['desc'],
                'players' => $playersText,
                'level' => $levels[$ev['levelId']],
                'levelColor' => $colors[$ev['levelId']],
                'imageUrl' => $ev['imageUrl']
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

    public static function joinedMatches(?int $currentUserId = null): array {
        $uid = $currentUserId ?? self::currentUserId();
        $parts = self::eventParticipants();
        $events = array_filter(self::events(), function($ev) use ($uid, $parts) {
            return in_array($uid, $parts[$ev['id']] ?? [], true);
        });
        $levels = self::levels();
        $colors = self::levelColors();
        return array_map(function($ev) use ($levels, $colors, $parts) {
            $current = count($parts[$ev['id']]);
            $playersText = $current . '/' . $ev['maxPlayers'] . ' Players';
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'dateText' => $ev['dateText'],
                'isoDate' => $ev['isoDate'],
                'location' => $ev['location'],
                'desc' => $ev['desc'],
                'players' => $playersText,
                'level' => $levels[$ev['levelId']],
                'levelColor' => $colors[$ev['levelId']],
                'imageUrl' => $ev['imageUrl']
            ];
        }, array_values($events));
    }

    public static function myEvents(int $currentUserId = null): array {
        $uid = $currentUserId ?? self::currentUserId();
        $events = array_filter(self::events(), function($ev) use ($uid) {
            return ($ev['ownerId'] ?? null) === $uid;
        });
        $levels = self::levels();
        $colors = self::levelColors();
        $participants = self::eventParticipants();
        return array_map(function($ev) use ($levels, $colors, $participants) {
            $current = count($participants[$ev['id']]);
            // Handle different participant types
            if ($ev['maxPlayers'] === null) {
                // Minimum mode - show "min+ Players"
                $playersText = $ev['minNeeded'] . '+ Players';
            } elseif ($ev['minNeeded'] === $ev['maxPlayers']) {
                // Specific mode - show "exact Players"
                $playersText = $ev['minNeeded'] . ' Players';
            } else {
                // Range mode - show "current/min-max Players"
                $playersText = $current . '/' . $ev['minNeeded'] . '-' . $ev['maxPlayers'] . ' Players';
            }
            return [
                'id' => $ev['id'],
                'title' => $ev['title'],
                'datetime' => $ev['dateText'],
                'players' => $playersText,
                'level' => $levels[$ev['levelId']],
                'levelColor' => $colors[$ev['levelId']],
                'imageUrl' => $ev['imageUrl']
            ];
        }, array_values($events));
    }

    public static function getEventById(int $id): ?array {
        $levels = self::levels();
        $participants = self::eventParticipants();
        $users = self::users();
        $currentUserId = self::currentUserId();
        
        foreach (self::events() as $ev) {
            if (($ev['id'] ?? 0) === $id) {
                $current = count($participants[$ev['id']] ?? []);
                $owner = $ev['ownerId'] ?? null;
                $isOwner = $owner === $currentUserId;
                
                if ($owner && isset($users[$owner])) {
                    $user = $users[$owner];
                    $organizer = [
                        'name' => ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''),
                        'avatar' => $user['avatar'] ?? ''
                    ];
                } else {
                    $organizer = ['name' => 'Unknown', 'avatar' => ''];
                }
                
                return [
                    'id' => $ev['id'],
                    'title' => $ev['title'],
                    'location' => $ev['location'],
                    'coords' => $ev['coords'],
                    'dateTime' => $ev['isoDate'],
                    'skillLevel' => $levels[$ev['levelId']],
                    'desc' => $ev['desc'] ?? '',
                    'organizer' => $organizer,
                    'isOwner' => $isOwner,
                    'participants' => [
                        'current' => $current,
                        'max' => $ev['maxPlayers'],
                        'minNeeded' => $ev['minNeeded']
                    ],
                ];
            }
        }
        return null;
    }

    private static function applyUpdates(array &$event, array $updates): void
    {
        if (isset($updates['title'])) $event['title'] = $updates['title'];
        if (isset($updates['dateText'])) $event['dateText'] = $updates['dateText'];
        if (isset($updates['isoDate'])) $event['isoDate'] = $updates['isoDate'];
        if (isset($updates['coords'])) $event['coords'] = $updates['coords'];
        if (isset($updates['levelId'])) $event['levelId'] = $updates['levelId'];
        if (array_key_exists('maxPlayers', $updates)) $event['maxPlayers'] = $updates['maxPlayers'];
        if (array_key_exists('minNeeded', $updates)) $event['minNeeded'] = $updates['minNeeded'];
        if (array_key_exists('desc', $updates)) $event['desc'] = $updates['desc'];
    }

    public static function updateEvent(int $id, array $updates): bool
    {
        self::ensureSession();

        // Ensure events data is initialized
        if (self::$eventsData === null) {
            self::events();
        }
        
        // Update the event in the array
        foreach (self::$eventsData as &$ev) {
            if (($ev['id'] ?? 0) === $id) {
                self::applyUpdates($ev, $updates);
                
                // Persist updated event to session
                if (!isset($_SESSION['edited_events'])) {
                    $_SESSION['edited_events'] = [];
                }
                
                // Store entire updated event in session (for all events)
                $_SESSION['edited_events'][$id] = $ev;
                
                return true;
            }
        }
        return false;
    }

    public static function addEvent(array $eventData): ?int
    {
        self::ensureSession();
        $events = self::events();
        
        // Find max ID
        $maxId = 0;
        foreach ($events as $ev) {
            if (($ev['id'] ?? 0) > $maxId) {
                $maxId = $ev['id'];
            }
        }
        
        $newId = $maxId + 1;
        $newEvent = array_merge($eventData, ['id' => $newId]);
        
        // Persist to session (aby przetrwały między requestami/workers w PHP-FPM)
        if (!isset($_SESSION['created_events'])) {
            $_SESSION['created_events'] = [];
        }
        $_SESSION['created_events'][] = $newEvent;
        
        // Also update in-memory cache
        self::$eventsData[] = $newEvent;
        
        return $newId;
    }
}
