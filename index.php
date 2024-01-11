<?php

require './router.php';
require './slugifier.php';

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

// Útvonalak regisztrálása
$routes = [
    // [method, útvonal, handlerFunction],
    ['GET', '/', 'homeHandler'],
    ['GET', '/admin', 'adminHandler'],
    ['GET', '/admin/etel-szerkesztese/{keresoBaratNev}', 'dishEditHandler'],
    ['POST', '/login', 'loginHandler'],
    ['POST', '/logout', 'logoutHandler'],
    ['POST', '/delete-dish/{id}', 'deleteHandler'],
    ['GET', '/admin/uj-etel-letrehozasa', 'createDishHandler'],
    ['POST', '/create-dish', 'createFormHandler'],
    ['POST', '/update-dish/{id}', 'updateHandler'],
    ['GET', '/admin/etel-tipusok', 'dishTypesHandler'],
    ['POST', '/create-dish-type', 'createDishTypeHandler']
];

// Útvonalválasztó inicializálása
$dispatch = registerRoutes($routes);
$matchedRoute = $dispatch($method, $path);
$handlerFunction = $matchedRoute['handler'];
$handlerFunction($matchedRoute['vars']);

// Handler függvények deklarálása
function homeHandler()
{

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishTypes');
    $statement->execute([]);
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);

    foreach($dishTypes as $index => $dishType) {
        $statement = $pdo->prepare('SELECT * FROM dishes WHERE dishTypeId = ?');
        $statement->execute([$dishType['id']]);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $dishes = [];
        foreach($result as $dish) {
            if((int)$dish['isActive']) {
                array_push($dishes, $dish);
            }
        }
        $dishTypes[$index]['dishes'] = $dishes;
    }

    echo render("wrapper.phtml", [
        'content' => render('public-menu.phtml', [
            'dishTypes' => $dishTypes
        ])
    ]);
}

function createDishTypeHandler() {

    $pdo = getConnection();
    $statement = $pdo->prepare('INSERT INTO dishTypes (`name`,`slug`,`description`) 
        VALUES (?,?,?)
    ');
    $statement->execute([
        $_POST['name'],
        slugify($_POST['name']),
        $_POST['description']
    ]);

    header('Location: /admin/etel-tipusok');

}

function dishTypesHandler() {
    if(!isLoggedIn()) {
        header('Location: /admin');
        return;
    }

    echo render('admin-wrapper.phtml', [
        'content' => render('dish-type-list.phtml', [
            'dishTypes' => getDishTypes()
        ])
    ]);

}

function updateHandler($vars) {
    
    $pdo = getConnection();
    $statement = $pdo->prepare('UPDATE dishes SET 
        `name` = ?,
        `slug` = ?,
        `description` = ?,
        `price` = ?,
        `isActive` = ?,
        `dishTypeId` = ?
        WHERE id = ?');
    $statement->execute([
        $_POST['name'],
        slugify($_POST['name']),
        $_POST['description'],
        (int)$_POST['price'],
        (int)isset($_POST['isActive']),
        (int)$_POST['dishTypeId'],
        $vars['id']
    ]);

    header('Location: /admin');
}

function createFormHandler() {
    $pdo = getConnection();
    $statement = $pdo->prepare('INSERT INTO dishes (`name`,`slug`,`description`,`price`,`isActive`,`dishTypeId`)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $statement->execute([
        $_POST['name'],
        slugify($_POST['name']),
        $_POST['description'],
        (int)$_POST['price'],
        (int)isset($_POST['isActive']),
        (int)$_POST['dishTypeId']
    ]);

    header('Location: /admin');
}

function getDishTypes() {
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishTypes');
    $statement->execute([]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function createDishHandler() {

    if(!isLoggedIn()) {
        header('Location: /admin');
        return;
    }

    $dishTypes = getDishTypes();

    echo render('admin-wrapper.phtml', [
        'content' => render('create-dish.phtml', [
            'dishTypes' => $dishTypes
        ])
    ]);

}

function deleteHandler($vars) {
    $pdo = getConnection();
    $statement = $pdo->prepare('DELETE FROM dishes WHERE id = ?');
    $statement->execute([$vars['id']]);

    header('Location: /admin');
}

function logoutHandler() {
    session_start();
    $params = session_get_cookie_params();
    setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    session_destroy();

    header('Location: /');
}

function loginHandler() {
    
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $statement->execute([$_POST['email']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        echo "invalidCredentials";
        return;
    }

    $isVerified = password_verify($_POST['password'], $user['password']);
    if(!$isVerified) {
        echo "invalidCredentials";
        return;
    }

    session_start();
    $_SESSION['userId'] = $user['id'];

    header('Location: /admin');
}

function adminHandler() {

    if(!isLoggedIn()) {
        echo render('wrapper.phtml', [
            'content' => render('login.phtml')
        ]);
        return;
    }

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishes');
    $statement->execute([]);
    $dishes = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo render('admin-wrapper.phtml', [
        'content' => render('dish-list.phtml', [
            'dishes' => $dishes
        ])
    ]);

}

function dishEditHandler($vars)
{

    if(!isLoggedIn()) {
        header('Location: /admin');
        return;
    }

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishes WHERE slug = ?');
    $statement->execute([$vars['keresoBaratNev']]);
    $dish = $statement->fetch(PDO::FETCH_ASSOC);

    if(!$dish) {
        header('Location: /admin');
        return;
    }

    echo render('admin-wrapper.phtml', [
        'content' => render('edit-dish.phtml', [
            'dish' => $dish,
            'dishTypes' => getDishTypes()
        ])
    ]);
}

function notFoundHandler()
{
    echo 'Oldal nem található';
}

function isLoggedIn(): bool {
    if(!isset($_COOKIE[session_name()])) {
        return false;
    }
    session_start();
    if(!isset($_SESSION['userId'])) {
        return false;
    }
    return true;
}

function render($path, $params = [])
{
    ob_start();
    require __DIR__ . '/views/' . $path;
    return ob_get_clean();
}

function getConnection()
{
    return new PDO(
        'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
        $_SERVER['DB_USER'],
        $_SERVER['DB_PASSWORD']
    );
}
