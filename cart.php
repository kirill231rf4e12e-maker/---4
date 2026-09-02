<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

requireLogin();

$pdo = db();

$message = trim(
    (string) ($_GET['message'] ?? '')
);

$error = '';

/*
|--------------------------------------------------------------------------
| Добавление книги в корзину
|--------------------------------------------------------------------------
*/

if (isset($_GET['add'])) {

    $bookId = (int) $_GET['add'];

    $type = (string) (
        $_GET['type'] ?? 'buy'
    );

    $term = (int) (
        $_GET['term'] ?? 14
    );


    /*
    |--------------------------------------------------------------------------
    | Проверка типа и срока аренды
    |--------------------------------------------------------------------------
    */

    if (!in_array($term, [14, 30, 90], true)) {
        $term = 14;
    }

    if (!in_array($type, ['buy', 'rent'], true)) {
        $type = 'buy';
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | Проверка доступности книги
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            'SELECT status
             FROM books
             WHERE id = ?'
        );

        $stmt->execute([$bookId]);

        $status = $stmt->fetchColumn();


        if ($status !== 'available') {

            $error = 'Книга сейчас недоступна';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Проверка наличия книги в корзине
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT id
                 FROM cart_items
                 WHERE user_id = ?
                   AND book_id = ?
                 LIMIT 1'
            );

            $stmt->execute([
                currentUserId(),
                $bookId,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Обновление существующей позиции
            |--------------------------------------------------------------------------
            */

            if ($stmt->fetch()) {

                $stmt = $pdo->prepare(
                    'UPDATE cart_items
                     SET
                        item_type = ?,
                        term_days = ?
                     WHERE user_id = ?
                       AND book_id = ?'
                );

                $stmt->execute([
                    $type,
                    $type === 'rent' ? $term : null,
                    currentUserId(),
                    $bookId,
                ]);

            }

            /*
            |--------------------------------------------------------------------------
            | Добавление новой позиции
            |--------------------------------------------------------------------------
            */

            else {

                $stmt = $pdo->prepare(
                    'INSERT INTO cart_items (
                        user_id,
                        book_id,
                        item_type,
                        term_days
                    )
                    VALUES (?, ?, ?, ?)'
                );

                $stmt->execute([
                    currentUserId(),
                    $bookId,
                    $type,
                    $type === 'rent' ? $term : null,
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Перенаправление в корзину
            |--------------------------------------------------------------------------
            */

            header(
                'Location: cart.php?message=' .
                rawurlencode('Книга добавлена в корзину')
            );

            exit;
        }

    } catch (PDOException $e) {

        $error = 'Не удалось добавить книгу';
    }
}


/*
|--------------------------------------------------------------------------
| Обработка действий корзины
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string) (
        $_POST['action'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Удаление книги из корзины
    |--------------------------------------------------------------------------
    */

    if ($action === 'remove') {

        $cartId = (int) (
            $_POST['cart_id'] ?? 0
        );

        $stmt = $pdo->prepare(
            'DELETE FROM cart_items
             WHERE id = ?
               AND user_id = ?'
        );

        $stmt->execute([
            $cartId,
            currentUserId(),
        ]);


        header(
            'Location: cart.php?message=' .
            rawurlencode('Книга удалена из корзины')
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Обновление позиции корзины
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        $cartId = (int) (
            $_POST['cart_id'] ?? 0
        );

        $type = (string) (
            $_POST['item_type'] ?? 'buy'
        );

        $term = (int) (
            $_POST['term_days'] ?? 14
        );


        if (!in_array($type, ['buy', 'rent'], true)) {
            $type = 'buy';
        }

        if (!in_array($term, [14, 30, 90], true)) {
            $term = 14;
        }


        $stmt = $pdo->prepare(
            'UPDATE cart_items
             SET
                item_type = ?,
                term_days = ?
             WHERE id = ?
               AND user_id = ?'
        );

        $stmt->execute([
            $type,
            $type === 'rent' ? $term : null,
            $cartId,
            currentUserId(),
        ]);


        header(
            'Location: cart.php?message=' .
            rawurlencode('Корзина обновлена')
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Оформление заказа
    |--------------------------------------------------------------------------
    */

    if ($action === 'checkout') {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Получение товаров корзины
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT
                    c.*,
                    b.title,
                    b.price,
                    b.status,
                    b.file_path
                 FROM cart_items c
                 JOIN books b
                    ON b.id = c.book_id
                 WHERE c.user_id = ?
                 FOR UPDATE'
            );

            $stmt->execute([
                currentUserId(),
            ]);

            $items = $stmt->fetchAll();


            if (!$items) {

                throw new RuntimeException(
                    'Корзина пуста'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Получение баланса пользователя
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT balance
                 FROM users
                 WHERE id = ?
                 FOR UPDATE'
            );

            $stmt->execute([
                currentUserId(),
            ]);

            $balance = (float) $stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Расчёт общей суммы
            |--------------------------------------------------------------------------
            */

            $total = 0.0;


            foreach ($items as $item) {

                if ($item['status'] !== 'available') {

                    throw new RuntimeException(
                        'Одна из книг больше недоступна: ' .
                        $item['title']
                    );
                }


                $total += (float) $item['price'];
            }


            /*
            |--------------------------------------------------------------------------
            | Проверка баланса
            |--------------------------------------------------------------------------
            */

            if ($balance < $total) {

                throw new RuntimeException(
                    'Недостаточно средств на балансе'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Оформление покупки или аренды
            |--------------------------------------------------------------------------
            */

            foreach ($items as $item) {

                /*
                |--------------------------------------------------------------------------
                | Покупка
                |--------------------------------------------------------------------------
                */

                if ($item['item_type'] === 'buy') {

                    $stmt = $pdo->prepare(
                        'INSERT INTO purchases (
                            user_id,
                            book_id,
                            price,
                            file_path
                        )
                        VALUES (?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        currentUserId(),
                        $item['book_id'],
                        $item['price'],
                        $item['file_path'],
                    ]);


                    $stmt = $pdo->prepare(
                        "UPDATE books
                         SET status = 'sold'
                         WHERE id = ?"
                    );

                    $stmt->execute([
                        $item['book_id'],
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Аренда
                |--------------------------------------------------------------------------
                */

                else {

                    $stmt = $pdo->prepare(
                        "INSERT INTO rentals (
                            user_id,
                            book_id,
                            term_days,
                            price,
                            start_date,
                            end_date,
                            status,
                            reminded
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            NOW(),
                            DATE_ADD(NOW(), INTERVAL ? DAY),
                            'active',
                            0
                        )"
                    );

                    $stmt->execute([
                        currentUserId(),
                        $item['book_id'],
                        $item['term_days'],
                        $item['price'],
                        $item['term_days'],
                    ]);


                    $stmt = $pdo->prepare(
                        "UPDATE books
                         SET status = 'rented'
                         WHERE id = ?"
                    );

                    $stmt->execute([
                        $item['book_id'],
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Списание средств
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET balance = balance - ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $total,
                currentUserId(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | Очистка корзины
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'DELETE FROM cart_items
                 WHERE user_id = ?'
            );

            $stmt->execute([
                currentUserId(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | Завершение транзакции
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            header(
                'Location: my_books.php?message=' .
                rawurlencode(
                    'Покупка и аренда успешно оформлены. ' .
                    'Списано: ' . money($total)
                )
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Не удалось оформить заказ';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Получение товаров корзины
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT
        c.*,
        b.title,
        b.author,
        b.image_path,
        b.price
     FROM cart_items c
     JOIN books b
        ON b.id = c.book_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC'
);

$stmt->execute([
    currentUserId(),
]);

$items = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Расчёт общей стоимости
|--------------------------------------------------------------------------
*/

$total = 0.0;

foreach ($items as $item) {
    $total += (float) $item['price'];
}


/*
|--------------------------------------------------------------------------
| Получение текущего баланса
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT balance
     FROM users
     WHERE id = ?'
);

$stmt->execute([
    currentUserId(),
]);

$balance = (float) $stmt->fetchColumn();

?>

<!doctype html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Корзина</title>

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
            Нижный мир
        </a>


        <nav class="nav">

            <a href="index.php">
                Каталог
            </a>

            <a href="my_books.php">
                Мои книги
            </a>

            <span class="user-box">
                Баланс: <?= money($balance) ?>
            </span>

        </nav>

    </header>


    <!-- ==========================================================
         Main content
         ========================================================== -->

    <main class="main-content">

        <!-- Заголовок -->

        <div class="catalog-head">

            <div>

                <p class="section-kicker">
                    Покупки
                </p>

                <h1>
                    Корзина
                </h1>

            </div>


            <a
                class="button secondary"
                href="index.php"
            >
                Продолжить выбор
            </a>

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
             Пустая корзина
             ====================================================== -->

        <?php if (!$items): ?>

            <section class="card empty">

                <h2>
                    Корзина пуста
                </h2>

                <a href="index.php">
                    Перейти в каталог
                </a>

            </section>


        <?php else: ?>


            <!-- ==================================================
                 Корзина
                 ================================================== -->

            <section class="cart-layout">


                <!-- ==================================================
                     Список товаров
                     ================================================== -->

                <div class="cart-list">

                    <?php foreach ($items as $item): ?>

                        <article class="cart-item">

                            <!-- Обложка -->

                            <img
                                src="<?= h($item['image_path'] ?: 'assets/images/placeholder.svg') ?>"
                                alt="<?= h($item['title']) ?>"
                            >


                            <!-- Информация -->

                            <div class="cart-info">

                                <h3>
                                    <?= h($item['title']) ?>
                                </h3>

                                <p>
                                    <?= h($item['author']) ?>
                                </p>

                                <strong>
                                    <?= money((float) $item['price']) ?>
                                </strong>

                            </div>


                            <!-- ==================================================
                                 Изменение типа покупки / аренды
                                 ================================================== -->

                            <form
                                method="post"
                                class="cart-controls"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update"
                                >

                                <input
                                    type="hidden"
                                    name="cart_id"
                                    value="<?= (int) $item['id'] ?>"
                                >


                                <select name="item_type">

                                    <option
                                        value="buy"
                                        <?= $item['item_type'] === 'buy'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Покупка
                                    </option>


                                    <option
                                        value="rent"
                                        <?= $item['item_type'] === 'rent'
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        Аренда
                                    </option>

                                </select>


                                <select name="term_days">

                                    <option
                                        value="14"
                                        <?= (int) $item['term_days'] === 14
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        2 недели
                                    </option>


                                    <option
                                        value="30"
                                        <?= (int) $item['term_days'] === 30
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        1 месяц
                                    </option>


                                    <option
                                        value="90"
                                        <?= (int) $item['term_days'] === 90
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        3 месяца
                                    </option>

                                </select>


                                <button
                                    class="button small"
                                    type="submit"
                                >
                                    Обновить
                                </button>

                            </form>


                            <!-- Удаление -->

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="remove"
                                >

                                <input
                                    type="hidden"
                                    name="cart_id"
                                    value="<?= (int) $item['id'] ?>"
                                >


                                <button
                                    class="button danger small"
                                    type="submit"
                                >
                                    Удалить
                                </button>

                            </form>

                        </article>

                    <?php endforeach; ?>

                </div>


                <!-- ==================================================
                     Итог заказа
                     ================================================== -->

                <aside class="order-card">

                    <p class="section-kicker">
                        Заказ
                    </p>

                    <h2>
                        Итого
                    </h2>


                    <strong class="order-total">
                        <?= money($total) ?>
                    </strong>


                    <p>
                        Баланс после покупки:
                        <b>
                            <?= money($balance - $total) ?>
                        </b>
                    </p>


                    <?php if ($balance >= $total): ?>

                        <form method="post">

                            <input
                                type="hidden"
                                name="action"
                                value="checkout"
                            >


                            <button type="submit">
                                Оформить заказ
                            </button>

                        </form>

                    <?php else: ?>

                        <div class="alert alert-error">

                            Не хватает
                            <?= money($total - $balance) ?>

                        </div>

                    <?php endif; ?>

                </aside>

            </section>

        <?php endif; ?>

    </main>

</div>

</body>
</html>