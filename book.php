<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Получение книги
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT *
     FROM books
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$id]);

$book = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Получение баланса пользователя
|--------------------------------------------------------------------------
*/

$balance = null;

if (isLoggedIn()) {

    $stmt = db()->prepare(
        'SELECT balance
         FROM users
         WHERE id = ?'
    );

    $stmt->execute([
        currentUserId(),
    ]);

    $balance = (float) $stmt->fetchColumn();
}

?>

<!doctype html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= $book ? h($book['title']) : 'Книга' ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<div class="site">

    <!-- ==========================================================
         Шапка сайта
         ========================================================== -->

    <header class="topbar">

        <a
            class="logo"
            href="index.php"
        >
            <span>К</span>
            Нижный мир
        </a>


        <nav class="nav">

            <?php if (isLoggedIn()): ?>

                <a href="cart.php">
                    Корзина
                </a>

                <a href="my_books.php">
                    Мои книги
                </a>

                <span class="user-box">
                    <?= h($_SESSION['login']) ?>
                    ·
                    <?= money($balance ?? 0) ?>
                </span>

            <?php else: ?>

                <a href="index.php#login">
                    Войти
                </a>

            <?php endif; ?>

        </nav>

    </header>


    <!-- ==========================================================
         Основное содержимое
         ========================================================== -->

    <main class="main-content">

        <?php if (!$book): ?>

            <!-- ==================================================
                 Книга не найдена
                 ================================================== -->

            <div class="card empty">

                <h1>
                    Книга не найдена
                </h1>

                <a href="index.php">
                    Вернуться в каталог
                </a>

            </div>


        <?php else: ?>

            <!-- ==================================================
                 Возврат в каталог
                 ================================================== -->

            <a
                class="back-link"
                href="index.php"
            >
                ← Вернуться в каталог
            </a>


            <!-- ==================================================
                 Карточка книги
                 ================================================== -->

            <article class="book-detail">

                <!-- Обложка -->

                <div class="detail-cover">

                    <img
                        src="<?= h(
                            $book['image_path']
                                ?: 'assets/images/placeholder.svg'
                        ) ?>"
                        alt="Обложка <?= h($book['title']) ?>"
                    >

                </div>


                <!-- Информация о книге -->

                <div class="detail-info">

                    <!-- Категория и год -->

                    <div class="book-topline">

                        <span>
                            <?= h($book['category']) ?>
                        </span>

                        <span>
                            <?= (int) $book['year'] ?>
                        </span>

                    </div>


                    <!-- Название -->

                    <h1>
                        <?= h($book['title']) ?>
                    </h1>


                    <!-- Автор -->

                    <p class="detail-author">
                        <?= h($book['author']) ?>
                    </p>


                    <!-- Описание -->

                    <p class="detail-description">
                        <?= nl2br(h($book['description'])) ?>
                    </p>


                    <!-- Основная информация -->

                    <div class="detail-meta">

                        <div>

                            <span>
                                Цена
                            </span>

                            <strong>
                                <?= money((float) $book['price']) ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Статус
                            </span>

                            <strong>
                                <?= h(
                                    bookStatusLabel(
                                        $book['status']
                                    )
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <!-- ==================================================
                         Действия
                         ================================================== -->

                    <?php if (
                        isLoggedIn() &&
                        $book['status'] === 'available'
                    ): ?>

                        <div class="detail-actions">

                            <a
                                class="button secondary"
                                href="cart.php?add=<?= (int) $book['id'] ?>&type=buy"
                            >
                                В корзину для покупки
                            </a>


                            <a
                                class="button"
                                href="cart.php?add=<?= (int) $book['id'] ?>&type=rent&term=14"
                            >
                                В аренду на 2 недели
                            </a>

                        </div>


                        <p class="hint">
                            Срок аренды можно изменить в корзине:
                            2 недели, 1 месяц или 3 месяца.
                        </p>


                    <?php elseif (!isLoggedIn()): ?>

                        <a
                            class="button"
                            href="index.php#login"
                        >
                            Войти для покупки
                        </a>

                    <?php endif; ?>

                </div>

            </article>

        <?php endif; ?>

    </main>

</div>

</body>
</html>