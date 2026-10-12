<?php
declare(strict_types=1);

namespace MoteCMS;

use RuntimeException;

final class UserRepository
{
    private array $users;
    private string $usersFile;

    public function __construct(string $usersFile)
    {
        $this->usersFile = $usersFile;
        $this->loadUsers();
    }

    private function loadUsers(): void
    {
        if (file_exists($this->usersFile)) {
            $this->users = require $this->usersFile;
        } else {
            $this->users = [];
        }
    }

    public function findByUsername(string $username): ?array
    {
        return $this->users[$username] ?? null;
    }

    public function validatePassword(string $username, string $password): bool
    {
        $user = $this->findByUsername($username);
        if ($user === null) {
            return false;
        }
        return password_verify($password, $user['password_hash']);
    }

    public function addUser(string $username, string $password, string $role, string $name): void
    {
        if (isset($this->users[$username])) {
            throw new RuntimeException("User $username already exists");
        }

        $this->users[$username] = [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'name' => $name
        ];

        $this->saveUsers();
    }

    public function updateUser(string $username, array $data): void
    {
        if (!isset($this->users[$username])) {
            throw new RuntimeException("User $username not found");
        }

        if (isset($data['password'])) {
            $this->users[$username]['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['role'])) {
            $this->users[$username]['role'] = $data['role'];
        }
        if (isset($data['name'])) {
            $this->users[$username]['name'] = $data['name'];
        }

        $this->saveUsers();
    }

    public function deleteUser(string $username): void
    {
        if (!isset($this->users[$username])) {
            throw new RuntimeException("User $username not found");
        }

        unset($this->users[$username]);
        $this->saveUsers();
    }

    /** @return array<string, array{password_hash: string, role: string, name: string}> */
    public function all(): array
    {
        return $this->users;
    }

    public function getRoles(): array
    {
        return ['admin', 'editor'];
    }

    private function saveUsers(): void
    {
        $content = "<?php\nreturn " . var_export($this->users, true) . ";\n";
        file_put_contents($this->usersFile, $content, LOCK_EX);
    }
}
