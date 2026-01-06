<?php
/**
 * CLI tool to reset admin password
 * Usage: php reset_admin.php
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/src/repository/UserRepository.php';

try {
    $db = new Database();
    $userRepo = new UserRepository();
    
    $adminEmail = 'admin@gmail.com';
    $adminPassword = 'adminadmin';
    
    // Check if running from command line
    if (php_sapi_name() !== 'cli') {
        http_response_code(403);
        die('This script can only be run from command line');
    }
    
    echo "Resetting admin credentials...\n";
    echo "Email: $adminEmail\n";
    echo "Password: $adminPassword\n\n";
    
    $conn = $db->connect();
    
    // First, ensure columns exist
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'basic'");
    } catch (Throwable $e) {}
    
    // Hash the password - use bcrypt for compatibility
    $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Check if admin exists
    $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $checkStmt->execute([$adminEmail]);
    $adminExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminExists) {
        // Update existing admin
        $stmt = $conn->prepare('UPDATE users SET password = ?, role = ?, firstname = ?, lastname = ? WHERE email = ?');
        $stmt->execute([$hashedPassword, 'admin', 'Admin', 'User', $adminEmail]);
        echo "✓ Admin password updated\n";
    } else {
        // Create new admin
        $stmt = $conn->prepare('
            INSERT INTO users (email, password, firstname, lastname, role)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$adminEmail, $hashedPassword, 'Admin', 'User', 'admin']);
        echo "✓ Admin account created\n";
    }
    
    echo "\nYou can now login with:\n";
    echo "Email: $adminEmail\n";
    echo "Password: $adminPassword\n";
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    die(1);
}
