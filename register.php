<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$error = '';

/*
|--------------------------------------------------------------------------
| Обработка регистрации
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $loginName = trim(
        (string) ($_POST['login'] ?? '')
    );

    $password = (string) ($_POST['password'] ?? '');

    $password2 = (string) ($_POST['password2'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Проверка данных
    |--------------------------------------------------------------------------
    */

    if ($loginName === '' || $password === '') {

        $error = 'Заполните все поля';

    } elseif (mb_strlen($loginName) > 50) {

        $error = 'Логин слишком длинный';

    } elseif (strlen($password) < 4) {

        $error = 'Пароль должен содержать минимум 4 символа';

    } elseif ($password !== $password2) {

        $error = 'Пароли не совпадают';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Создание пользователя
        |--------------------------------------------------------------------------
        */

        try {

            $stmt = db()->prepare(
                'INSERT INTO users (
                    login,
                    password,
                    role,
                    balance
                )
                VALUES (
                    ?,
                    ?,
                    \'user\',
                    1000.00
                )'
            );

            $stmt->execute([
                $loginName,
                password_hash($password, PASSWORD_DEFAULT),
            ]);


            /*
            |--------------------------------------------------------------------------
            | Авторизация нового пользователя
            |--------------------------------------------------------------------------
            */

            login(
                (int) db()->lastInsertId(),
                $loginName,
                'user'
            );


            /*
            |--------------------------------------------------------------------------
            | Перенаправление
            |--------------------------------------------------------------------------
            */

            header('Location: index.php');

            exit;

        } catch (PDOException $e) {

            $error =
                ($e->errorInfo[1] ?? 0) === 1062
                    ? 'Такой логин уже существует'
                    : 'Не удалось зарегистрировать пользователя';
        }
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

    <title>Регистрация</title>

    <link
        rel="stylesheet"
        href="assets/style.css"
    >

</head>

<body class="auth-page">

<div class="auth-wrap">

    <section class="auth-card">

        <!-- Логотип -->

        <a
            class="logo"
            href="index.php"
        >
            <span>К</span>
            Нижный мир
        </a>


        <!-- Заголовок -->

        <p class="section-kicker">
            Новый читатель
        </p>

        <h1>
            Создание аккаунта
        </h1>


        <!-- Ошибка -->

        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= h($error) ?>
            </div>

        <?php endif; ?>


        <!-- Форма регистрации -->

        <form
            method="post"
            class="form-stack"
        >

            <input
                name="login"
                maxlength="50"
                placeholder="Логин"
                required
            >


            <input
                name="password"
                type="password"
                placeholder="Пароль"
                required
            >


            <input
                name="password2"
                type="password"
                placeholder="Повторите пароль"
                required
            >


            <button type="submit">
                Зарегистрироваться и войти
            </button>

        </form>


        <!-- Ссылка на авторизацию -->

        <p class="auth-link">

            <a href="index.php#login">
                Уже есть аккаунт? Войти
            </a>

        </p>

    </section>

</div>

</body>
</html>