<?php

require_once __DIR__ . '/Repository.php';

class AuthRepository extends Repository
{
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * Logs failed login attempt
     * 
     * @param string|null $email User email
     * @param string $ipHash IP address hash
     * @param string|null $reason Failure reason
     */
    public function logFailedLoginAttempt(
        ?string $email,
        string $ipHash,
        ?string $reason = null
    ): void {
        $conn = $this->database->connect();

        $stmt = $conn->prepare('
            INSERT INTO auth_audit_log
                (event_type, email_hash, ip_hash, user_agent, reason)
            VALUES
                (:event_type, :email_hash, :ip_hash, :user_agent, :reason)
        ');

        $emailHash = $email ? hash('sha256', mb_strtolower($email)) : null;
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt->execute([
            ':event_type' => 'login_failed',
            ':email_hash' => $emailHash,
            ':ip_hash'    => $ipHash,
            ':user_agent' => $userAgent,
            ':reason'     => $reason,
        ]);
    }


    /**
     * Gets login attempts for IP address
     * 
     * @param string $ipHash IP address hash
     * @return array|null Attempts data or null
     */
    public function getIpAttempts(string $ipHash): ?array
    {
        $query = $this->database->connect()->prepare('
            SELECT count, lock_until FROM login_attempts
            WHERE email = :email AND ip_hash = :ip
        ');
        $star = '*';
        $query->bindParam(':email', $star, PDO::PARAM_STR);
        $query->bindParam(':ip', $ipHash, PDO::PARAM_STR);
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Increments IP login attempt counter
     * Implements rate limiting and locking
     * 
     * @param string $ipHash IP address hash
     * @param int $windowSeconds Time window for attempts
     * @param int $maxAttempts Max attempts before lock
     * @param int $lockSeconds Lock duration in seconds
     */
    public function incrementIpWindow(string $ipHash, int $windowSeconds, int $maxAttempts, int $lockSeconds): void
    {
        $conn = $this->database->connect();
        try {
            $conn->exec('ALTER TABLE login_attempts ADD COLUMN IF NOT EXISTS last_attempt BIGINT NOT NULL DEFAULT 0');
        } catch (Throwable $e) {
        }

        $emailStar = '*';
        $now = time();
        $stmt = $conn->prepare('SELECT count, lock_until, last_attempt FROM login_attempts WHERE email = :email AND ip_hash = :ip');
        $stmt->execute([':email' => $emailStar, ':ip' => $ipHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $insert = $conn->prepare('INSERT INTO login_attempts (email, ip_hash, count, lock_until, last_attempt) VALUES (:email, :ip, :count, :lock, :last)');
            $insert->execute([':email' => $emailStar, ':ip' => $ipHash, ':count' => 1, ':lock' => 0, ':last' => $now]);
            return;
        }

        $count = (int)$row['count'];
        $last = (int)$row['last_attempt'];
        $lockUntil = (int)$row['lock_until'];

        if ($now - $last <= $windowSeconds) {
            $count++;
        } else {
            $count = 1;
        }
        $last = $now;

        if ($count >= $maxAttempts) {
            $lockUntil = $now + $lockSeconds;
            $count = 0;
        }

        $update = $conn->prepare('UPDATE login_attempts SET count = :count, lock_until = :lock, last_attempt = :last WHERE email = :email AND ip_hash = :ip');
        $update->execute([':count' => $count, ':lock' => $lockUntil, ':last' => $last, ':email' => $emailStar, ':ip' => $ipHash]);
    }
}
