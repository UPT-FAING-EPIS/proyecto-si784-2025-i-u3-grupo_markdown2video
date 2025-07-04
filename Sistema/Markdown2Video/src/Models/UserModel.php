<?php

namespace Dales\Markdown2video\Models;

use PDO;
use PDOException;

class UserModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getUserById(int $userId): ?array
    {
        $query = "SELECT id, username, email, created_at FROM users WHERE id = :id LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            if ($this->isTestEnvironment()) {
                return null;
            }
            error_log("Error en UserModel::getUserById: " . $e->getMessage());
            return null;
        }
    }

    public function findByEmail(string $email): ?array
    {
        $query = "SELECT id, username, email, password_hash FROM users WHERE email = :email LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            if ($this->isTestEnvironment()) {
                return null;
            }
            error_log("Error en UserModel::findByEmail: " . $e->getMessage());
            return null;
        }
    }

    public function findByUsername(string $username): ?array
    {
        $query = "SELECT id, username FROM users WHERE username = :username LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            if ($this->isTestEnvironment()) {
                return null;
            }
            error_log("Error en UserModel::findByUsername: " . $e->getMessage());
            return null;
        }
    }

    public function createUser(string $username, string $email, string $plainPassword, array $additionalData = []): string|false
    {
        $password_hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($password_hash === false) {
            error_log("Error al hashear la contraseña para: $email");
            return false;
        }

        $fields = ['username', 'email', 'password_hash'];
        $placeholders = [':username', ':email', ':password_hash'];
        $params = [
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $password_hash
        ];

        $query = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if (!$this->isTestEnvironment()) {
                error_log("Error en UserModel::createUser: " . $e->getMessage());
            }
            return false;
        }
    }

    public function updateUser(int $userId, array $data): bool
    {
        $allowedFields = ['username', 'email'];
        $updateFields = [];
        $params = [':id' => $userId];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if (!$this->isTestEnvironment()) {
                error_log("Error en UserModel::updateUser: " . $e->getMessage());
            }
            return false;
        }
    }

    public function deleteUser(int $userId): bool
    {
        $query = "DELETE FROM users WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if (!$this->isTestEnvironment()) {
                error_log("Error en UserModel::deleteUser: " . $e->getMessage());
            }
            return false;
        }
    }

    private function isTestEnvironment(): bool
    {
        return getenv('APP_ENV') === 'testing' || php_sapi_name() === 'cli';
    }
}
