<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

requireLogin();

$userId = currentUserId();
$db = db();

/*
|--------------------------------------------------------------------------
| Получение арендованных книг
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT rentals.*, books.title, books.image_path
     FROM rentals
     JOIN books ON books.id = rentals.book_id
     WHERE rentals.user_id = ?
     ORDER BY rentals.end_date DESC'
);

$stmt->execute([$userId]);

$rentals = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Получение купленных книг
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT purchases.*, books.title, books.image_path
     FROM purchases
     JOIN books ON books.id = purchases.book_id
     WHERE purchases.user_id = ?
     ORDER BY purchases.purchased_at DESC'
);

$stmt->execute([$userId]);

$purchases = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Получение баланса
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT balance
     FROM users
     WHERE id = ?'
);

$stmt->execute([$userId]);

$balance = (float) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Сообщение
|--------------------------------------------------------------------------
*/

$message = trim((string) ($_GET['message'] ?? ''));

/*
|--------------------------------------------------------------------------
| Текущая дата
|--------------------------------------------------------------------------
*/

$today = new DateTimeImmutable('today');

/*
|--------------------------------------------------------------------------
| Проверка ближайших окончаний аренды
|--------------------------------------------------------------------------
|
| Считаем количество активных аренд, которые заканчиваются
| через 3 дня или меньше.
|
*/

$rentalWarningCount = 0;
$nearestRentalDays = null;

foreach ($rentals as $rental) {

    if ($rental['status'] !== 'active') {
        continue;
    }

    $rentalEndDate = new DateTimeImmutable($rental['end_date']);

    $daysLeft = (int) $today
        ->diff($rentalEndDate)
        ->format('%r%a');

    /*
     * Показываем предупреждение только если аренда
     * ещё не истекла и осталось от 0 до 3 дней.
     */

    if ($daysLeft >= 0 && $daysLeft <= 3) {

        $rentalWarningCount++;

        if (
            $nearestRentalDays === null ||
            $daysLeft < $nearestRentalDays
        ) {
            $nearestRentalDays = $daysLeft;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Текст предупреждения
|--------------------------------------------------------------------------
*/

$rentalWarningText = '';

if ($nearestRentalDays !== null) {

    if ($nearestRentalDays === 0) {
        $rentalWarningText = 'Заканчивается сегодня';
    } elseif ($nearestRentalDays === 1) {
        $rentalWarningText = 'Остался 1 день';
    } else {
        $rentalWarningText = 'Осталось ' . $nearestRentalDays . ' дня';
    }
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

    <title>Мои книги</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body>

<div class="site">

    <!-- ==========================================================
         Шапка
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


            <a href="cart.php">
                Корзина
            </a>


            <!-- ==================================================
                 Уведомление об окончании аренды
                 ================================================== -->

            <?php if ($rentalWarningCount > 0): ?>

                <a
                    class="rental-alert"
                    href="account.php"
                    title="Перейти к арендованным книгам"
                >

                    <span class="rental-alert-icon">
                        !
                    </span>

                    <span class="rental-alert-content">

                        <strong>
                            Срок аренды
                        </strong>

                        <span>
                            <?= h($rentalWarningText) ?>
                        </span>

                    </span>


                    <?php if ($rentalWarningCount > 1): ?>

                        <span class="rental-alert-count">
                            <?= $rentalWarningCount ?>
                        </span>

                    <?php endif; ?>

                </a>

            <?php endif; ?>


            <!-- Баланс -->

            <span class="user-box">
                Баланс: <?= money($balance) ?>
            </span>


            <!-- Выход -->

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
                    Личный кабинет
                </p>

                <h1>
                    Мои книги
                </h1>

            </div>

        </div>


        <!-- Сообщение -->

        <?php if ($message): ?>

            <div class="alert alert-success">
                <?= h($message) ?>
            </div>

        <?php endif; ?>


        <!-- ======================================================
             Арендованные книги
             ====================================================== -->

        <section class="section-block">

            <h2>
                Арендованные книги
            </h2>


            <div class="library-grid">

                <?php foreach ($rentals as $r): ?>

                    <?php

                    $endDate = new DateTimeImmutable($r['end_date']);

                    $daysLeft = (int) $today
                        ->diff($endDate)
                        ->format('%r%a');

                    $isActive = $r['status'] === 'active';

                    $showWarning =
                        $isActive &&
                        $daysLeft >= 0 &&
                        $daysLeft <= 3;

                    ?>

                    <article class="library-card">

                        <img
                            src="<?= h($r['image_path'] ?: 'assets/images/placeholder.svg') ?>"
                            alt="<?= h($r['title']) ?>"
                        >


                        <div>

                            <h3>
                                <?= h($r['title']) ?>
                            </h3>


                            <p>
                                Срок:
                                <?= (int) $r['term_days'] ?>
                                дней
                            </p>


                            <p>
                                До:
                                <?= h($r['end_date']) ?>
                            </p>


                            <p class="<?= $isActive ? 'good' : 'muted' ?>">

                                <?= $isActive
                                    ? 'Активна'
                                    : 'Срок истёк'
                                ?>

                            </p>


                            <!-- Предупреждение внутри карточки -->

                            <?php if ($showWarning): ?>

                                <div class="rental-warning">

                                    <span class="rental-warning-icon">
                                        !
                                    </span>

                                    <div>

                                        <?php if ($daysLeft === 0): ?>

                                            <strong>
                                                Аренда заканчивается сегодня
                                            </strong>

                                        <?php elseif ($daysLeft === 1): ?>

                                            <strong>
                                                До окончания остался 1 день
                                            </strong>

                                        <?php else: ?>

                                            <strong>
                                                До окончания осталось
                                                <?= $daysLeft ?>
                                                дня
                                            </strong>

                                        <?php endif; ?>


                                        <span>
                                            Продлите аренду, чтобы сохранить доступ к книге.
                                        </span>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- Скачать книгу -->

                            <?php if ($isActive): ?>

                                <a
                                    class="button small"
                                    href="download.php?type=rent&id=<?= (int) $r['id'] ?>"
                                >
                                    Скачать файл
                                </a>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>


                <?php if (!$rentals): ?>

                    <div class="card empty">
                        Арендованных книг пока нет.
                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- ======================================================
             Купленные книги
             ====================================================== -->

        <section class="section-block">

            <h2>
                Купленные книги
            </h2>


            <div class="library-grid">

                <?php foreach ($purchases as $p): ?>

                    <article class="library-card">

                        <img
                            src="<?= h($p['image_path'] ?: 'assets/images/placeholder.svg') ?>"
                            alt="<?= h($p['title']) ?>"
                        >


                        <div>

                            <h3>
                                <?= h($p['title']) ?>
                            </h3>


                            <p>
                                Куплена:
                                <?= h($p['purchased_at']) ?>
                            </p>


                            <p>
                                <?= money((float) $p['price']) ?>
                            </p>


                            <a
                                class="button small"
                                href="download.php?type=buy&id=<?= (int) $p['id'] ?>"
                            >
                                Скачать файл
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>


                <?php if (!$purchases): ?>

                    <div class="card empty">
                        Покупок пока нет.
                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

</body>
</html>