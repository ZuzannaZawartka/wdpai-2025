<?php

require_once __DIR__ . '/../../src/repository/UserRepository.php';

use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    private $repository;

    protected function setUp(): void
    {
        // For Repository tests, we usually need a mock database or a test database.
        // Assuming UserRepository uses the Database singleton.
        $this->repository = UserRepository::getInstance();
    }

    public function testGetExistingUser()
    {
        $user = $this->repository->getUserByEmail('admin@gmail.com');

        $this->assertNotNull($user);
        $this->assertEquals('admin@gmail.com', $user['email']);
        $this->assertEquals('admin', $user['role']);
    }

    public function testEmailExistsTrue()
    {
        $exists = $this->repository->emailExists('anna.nowak@example.com');
        $this->assertTrue($exists);
    }

    public function testEmailExistsFalse()
    {
        $exists = $this->repository->emailExists('nonexistent_user_123@test.pl');
        $this->assertFalse($exists);
    }
}
