<?php
declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'book_store';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: index.php#login');
        exit;
    }
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function login(int $id, string $login, string $role): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['login'] = $login;
    $_SESSION['role'] = $role;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function money(float $value): string
{
    return number_format($value, 2, ',', ' ') . ' ₽';
}

function bookStatusLabel(string $status): string
{
    return match ($status) {
        'available' => 'Доступна',
        'rented' => 'В аренде',
        'sold' => 'Продана',
        'unavailable' => 'Недоступна',
        default => 'Неизвестно',
    };
}

function expireRentals(): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT book_id FROM rentals WHERE status = 'active' AND end_date < NOW()"
    );
    $stmt->execute();
    $bookIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($bookIds === []) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec("UPDATE rentals SET status = 'expired' WHERE status = 'active' AND end_date < NOW()");

        $update = $pdo->prepare(
            "UPDATE books SET status = 'available' WHERE id = ? AND status = 'rented'"
        );
        foreach ($bookIds as $bookId) {
            $update->execute([$bookId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

expireRentals();
