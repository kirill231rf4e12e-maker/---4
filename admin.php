<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

requireAdmin();

$message = '';
$error = '';

$pdo = db();

/*
|--------------------------------------------------------------------------
| Обработка действий администратора
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string) ($_POST['action'] ?? '');

    try {

        /*
        |--------------------------------------------------------------------------
        | Добавление / редактирование книги
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_book') {

            $id = (int) ($_POST['id'] ?? 0);

            $title = trim((string) ($_POST['title'] ?? ''));
            $author = trim((string) ($_POST['author'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));

            $year = (int) ($_POST['year'] ?? 0);

            $description = trim(
                (string) ($_POST['description'] ?? '')
            );

            $price = max(
                0,
                (float) ($_POST['price'] ?? 0)
            );

            $imagePath = trim(
                (string) ($_POST['image_path'] ?? '')
            ) ?: null;

            $filePath = trim(
                (string) ($_POST['file_path'] ?? '')
            ) ?: null;

            $statusInput = $_POST['status'] ?? '';

            $status = in_array(
                $statusInput,
                [
                    'available',
                    'unavailable',
                    'rented',
                    'sold',
                ],
                true
            )
                ? $statusInput
                : 'available';


            /*
            |--------------------------------------------------------------------------
            | Проверка обязательных полей
            |--------------------------------------------------------------------------
            */

            if (
                $title === '' ||
                $author === '' ||
                $category === '' ||
                $year < 1 ||
                $description === ''
            ) {
                throw new RuntimeException(
                    'Заполните обязательные поля'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Обновление существующей книги
            |--------------------------------------------------------------------------
            */

            if ($id > 0) {

                $stmt = $pdo->prepare(
                    'UPDATE books
                     SET
                        title = ?,
                        author = ?,
                        category = ?,
                        year = ?,
                        description = ?,
                        price = ?,
                        image_path = ?,
                        file_path = ?,
                        status = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $title,
                    $author,
                    $category,
                    $year,
                    $description,
                    $price,
                    $imagePath,
                    $filePath,
                    $status,
                    $id,
                ]);

                $message = 'Книга обновлена';

            }

            /*
            |--------------------------------------------------------------------------
            | Добавление новой книги
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare(
                    'INSERT INTO books (
                        title,
                        author,
                        category,
                        year,
                        description,
                        price,
                        image_path,
                        file_path,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $title,
                    $author,
                    $category,
                    $year,
                    $description,
                    $price,
                    $imagePath,
                    $filePath,
                    $status,
                ]);

                $message = 'Книга добавлена';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Удаление книги
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete_book') {

            $id = (int) ($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException(
                    'Некорректный идентификатор книги'
                );
            }

            $stmt = $pdo->prepare(
                'DELETE FROM books WHERE id = ?'
            );

            $stmt->execute([$id]);

            $message = 'Книга удалена';
        }

    } catch (Throwable $e) {

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Не удалось выполнить действие';
    }
}


/*
|--------------------------------------------------------------------------
| Получение книг
|--------------------------------------------------------------------------
*/

$books = $pdo
    ->query(
        'SELECT *
         FROM books
         ORDER BY id DESC'
    )
    ->fetchAll();


/*
|--------------------------------------------------------------------------
| Получение аренд, которые заканчиваются
| в ближайшие 3 дня
|--------------------------------------------------------------------------
*/

$soon = $pdo
    ->query(
        "SELECT
            rentals.*,
            users.login,
            books.title
         FROM rentals
         JOIN users
            ON users.id = rentals.user_id
         JOIN books
            ON books.id = rentals.book_id
         WHERE
            rentals.status = 'active'
            AND rentals.end_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)
         ORDER BY rentals.end_date"
    )
    ->fetchAll();

?>

<!doctype html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Админ-панель</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<div class="site">

    <!-- ==========================================================
         Header
         ========================================================== -->

    <header class="topbar">

        <a
            class="logo"
            href="index.php"
        >
            <span>К</span>
            нижный мир
        </a>


        <nav class="nav">

            <a href="index.php">
                Каталог
            </a>

            <a href="my_books.php">
                Мои книги
            </a>

            <a
                class="nav-button"
                href="index.php?logout=1"
            >
                Выйти
            </a>

        </nav>

    </header>


    <!-- ==========================================================
         Основное содержимое
         ========================================================== -->

    <main class="main-content">

        <!-- Заголовок -->

        <div class="catalog-head">

            <div>

                <p class="section-kicker">
                    Управление
                </p>

                <h1>
                    Админ-панель
                </h1>

            </div>

        </div>


        <!-- ======================================================
             Сообщения
             ====================================================== -->

        <?php if ($message): ?>

            <div class="alert alert-success">
                <?= h($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= h($error) ?>
            </div>

        <?php endif; ?>


        <!-- ======================================================
             Добавление книги и напоминания
             ====================================================== -->

        <section class="admin-layout">


            <!-- ==================================================
                 Добавление книги
                 ================================================== -->

            <div class="card">

                <p class="section-kicker">
                    Новая книга
                </p>

                <h2>
                    Добавить книгу
                </h2>


                <form
                    method="post"
                    class="admin-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="save_book"
                    >


                    <input
                        name="title"
                        placeholder="Название"
                        required
                    >


                    <input
                        name="author"
                        placeholder="Автор"
                        required
                    >


                    <input
                        name="category"
                        placeholder="Категория"
                        required
                    >


                    <input
                        name="year"
                        type="number"
                        placeholder="Год"
                        required
                    >


                    <textarea
                        name="description"
                        placeholder="Описание"
                        required
                    ></textarea>


                    <input
                        name="price"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Цена"
                        required
                    >


                    <input
                        name="image_path"
                        placeholder="assets/images/book.jpg"
                    >


                    <input
                        name="file_path"
                        placeholder="assets/files/book.pdf"
                    >


                    <select name="status">

                        <option value="available">
                            Доступна
                        </option>

                        <option value="unavailable">
                            Недоступна
                        </option>

                        <option value="rented">
                            В аренде
                        </option>

                        <option value="sold">
                            Продана
                        </option>

                    </select>


                    <button type="submit">
                        Добавить книгу
                    </button>

                </form>

            </div>


            <!-- ==================================================
                 Напоминания об аренде
                 ================================================== -->

            <div class="card">

                <p class="section-kicker">
                    Ближайшие окончания
                </p>

                <h2>
                    Напоминания об аренде
                </h2>


                <?php if (!$soon): ?>

                    <p class="muted">
                        Нет аренд, заканчивающихся
                        в ближайшие 3 дня.
                    </p>

                <?php else: ?>

                    <div class="reminders">

                        <?php foreach ($soon as $r): ?>

                            <div class="reminder">

                                <div>

                                    <b>
                                        <?= h($r['title']) ?>
                                    </b>

                                    <span>
                                        Пользователь:
                                        <?= h($r['login']) ?>
                                    </span>

                                </div>

                                <span>
                                    До
                                    <?= h($r['end_date']) ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- ======================================================
             Редактирование книг
             ====================================================== -->

        <section class="card">

            <p class="section-kicker">
                Каталог
            </p>

            <h2>
                Редактирование книг
            </h2>


            <div class="admin-books">

                <?php foreach ($books as $book): ?>

                    <form
                        method="post"
                        class="admin-book"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="save_book"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $book['id'] ?>"
                        >


                        <input
                            name="title"
                            value="<?= h($book['title']) ?>"
                            required
                        >


                        <input
                            name="author"
                            value="<?= h($book['author']) ?>"
                            required
                        >


                        <input
                            name="category"
                            value="<?= h($book['category']) ?>"
                            required
                        >


                        <input
                            name="year"
                            type="number"
                            value="<?= (int) $book['year'] ?>"
                            required
                        >


                        <textarea
                            name="description"
                            required
                        ><?= h($book['description']) ?></textarea>


                        <input
                            name="price"
                            type="number"
                            step="0.01"
                            min="0"
                            value="<?= h((string) $book['price']) ?>"
                            required
                        >


                        <input
                            name="image_path"
                            value="<?= h($book['image_path']) ?>"
                        >


                        <input
                            name="file_path"
                            value="<?= h($book['file_path']) ?>"
                        >


                        <select name="status">

                            <?php
                            $statuses = [
                                'available' => 'Доступна',
                                'unavailable' => 'Недоступна',
                                'rented' => 'В аренде',
                                'sold' => 'Продана',
                            ];
                            ?>

                            <?php foreach ($statuses as $key => $label): ?>

                                <option
                                    value="<?= $key ?>"
                                    <?= $book['status'] === $key ? 'selected' : '' ?>
                                >
                                    <?= $label ?>
                                </option>

                            <?php endforeach; ?>

                        </select>


                        <button type="submit">
                            Сохранить
                        </button>


                        <button
                            class="danger"
                            type="submit"
                            name="action"
                            value="delete_book"
                            onclick="return confirm('Удалить книгу?')"
                        >
                            Удалить
                        </button>

                    </form>

                <?php endforeach; ?>

            </div>

        </section>

    </main>

</div>

</body>
</html>