<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_form'])) {
    $loginName = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, login, password, role FROM users WHERE login = ? LIMIT 1');
    $stmt->execute([$loginName]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        login((int)$user['id'], $user['login'], $user['role']);
        header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'index.php'));
        exit;
    }
    $error = 'Неверный логин или пароль';
}

$category = trim((string)($_GET['category'] ?? ''));
$author = trim((string)($_GET['author'] ?? ''));
$year = trim((string)($_GET['year'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'year_desc');
$allowedSorts = [
    'year_desc' => 'year DESC, title ASC',
    'year_asc' => 'year ASC, title ASC',
    'title_asc' => 'title ASC',
    'author_asc' => 'author ASC, title ASC',
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['year_desc'];

$sql = 'SELECT * FROM books WHERE 1=1';
$params = [];
if ($category !== '') { $sql .= ' AND category = ?'; $params[] = $category; }
if ($author !== '') { $sql .= ' AND author = ?'; $params[] = $author; }
if ($year !== '') { $sql .= ' AND year = ?'; $params[] = (int)$year; }
$sql .= ' ORDER BY ' . $orderBy;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

$categories = db()->query('SELECT DISTINCT category FROM books ORDER BY category')->fetchAll();
$authors = db()->query('SELECT DISTINCT author FROM books ORDER BY author')->fetchAll();
$years = db()->query('SELECT DISTINCT year FROM books ORDER BY year DESC')->fetchAll();

$balance = 0.0;
$cartCount = 0;
if (isLoggedIn()) {
    $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([currentUserId()]);
    $balance = (float)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT COUNT(*) FROM cart_items WHERE user_id = ?');
    $stmt->execute([currentUserId()]);
    $cartCount = (int)$stmt->fetchColumn();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Книжный магазин</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="site">
    <header class="topbar">
        <a class="logo" href="index.php"><span>К</span>нижный мир</a>
        <nav class="nav">
            <?php if (isLoggedIn()): ?>
                <a href="cart.php">Корзина <span class="nav-badge"><?= $cartCount ?></span></a>
                <a href="my_books.php">Мои книги</a>
                <?php if (isAdmin()): ?><a href="admin.php">Админ-панель</a><?php endif; ?>
                <span class="user-box"><?= h($_SESSION['login']) ?> · <?= money($balance) ?></span>
                <a class="nav-button" href="index.php?logout=1">Выйти</a>
            <?php else: ?>
                <a href="#login">Войти</a>
                <a class="nav-button" href="register.php">Регистрация</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="main-content">
        <section class="hero">
            <div>
                <p class="hero-kicker">Ваша домашняя библиотека</p>
                <h1>Книги, которые хочется прочитать</h1>
                <p>Выбирайте книги, покупайте или берите их в аренду с оплатой внутренним балансом.</p>
            </div>
            <div class="hero-card">
                <strong><?= count($books) ?></strong>
                <span>книг в каталоге</span>
            </div>
        </section>

        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

        <?php if (!isLoggedIn()): ?>
            <section class="card login-card" id="login">
                <div>
                    <p class="section-kicker">Личный кабинет</p>
                    <h2>Войти в магазин</h2>
                </div>
                <form method="post" class="login-form">
                    <input type="hidden" name="login_form" value="1">
                    <input name="login" placeholder="Логин" required>
                    <input name="password" type="password" placeholder="Пароль" required>
                    <button type="submit">Войти</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="catalog-head">
            <div>
                <p class="section-kicker">Каталог</p>
                <h2>Библиотека</h2>
            </div>
            <span class="catalog-count"><?= count($books) ?> книг</span>
        </section>

        <section class="card filters">
            <form method="get" class="filter-grid">
                <label>Категория
                    <select name="category">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $item): ?>
                            <option value="<?= h($item['category']) ?>" <?= $category === $item['category'] ? 'selected' : '' ?>><?= h($item['category']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Автор
                    <select name="author">
                        <option value="">Все авторы</option>
                        <?php foreach ($authors as $item): ?>
                            <option value="<?= h($item['author']) ?>" <?= $author === $item['author'] ? 'selected' : '' ?>><?= h($item['author']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Год
                    <select name="year">
                        <option value="">Все годы</option>
                        <?php foreach ($years as $item): ?>
                            <option value="<?= (int)$item['year'] ?>" <?= $year === (string)$item['year'] ? 'selected' : '' ?>><?= (int)$item['year'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Сортировка
                    <select name="sort">
                        <option value="year_desc" <?= $sort === 'year_desc' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="year_asc" <?= $sort === 'year_asc' ? 'selected' : '' ?>>Сначала старые</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>По названию</option>
                        <option value="author_asc" <?= $sort === 'author_asc' ? 'selected' : '' ?>>По автору</option>
                    </select>
                </label>
                <button type="submit">Применить</button>
                <a class="button secondary" href="index.php">Сбросить</a>
            </form>
        </section>

        <section class="books-grid">
            <?php if (!$books): ?><div class="card empty">Ничего не найдено.</div><?php endif; ?>
            <?php foreach ($books as $book): ?>
                <article class="book-card">
                    <a class="cover" href="book.php?id=<?= (int)$book['id'] ?>">
                        <img src="<?= h($book['image_path'] ?: 'assets/images/placeholder.svg') ?>" alt="Обложка <?= h($book['title']) ?>">
                    </a>
                    <div class="book-content">
                        <div class="book-topline"><span><?= h($book['category']) ?></span><span><?= (int)$book['year'] ?></span></div>
                        <h3><a href="book.php?id=<?= (int)$book['id'] ?>"><?= h($book['title']) ?></a></h3>
                        <p class="author"><?= h($book['author']) ?></p>
                        <p class="description"><?= h($book['description']) ?></p>
                        <div class="book-bottom">
                            <strong class="price"><?= money((float)$book['price']) ?></strong>
                            <span class="status status-<?= h($book['status']) ?>"><?= h(bookStatusLabel($book['status'])) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
</body>
</html>
