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
    ['GET', '/admin/etel-szerkesztese/{keresoBaratNev}', 'dishEditHandler'],
    ['POST', '/delete-dish/{id}', 'deleteDishHandler'],
    ['GET', '/admin', 'adminHandler'],
    ['POST', '/login', 'loginHandler'],
    ['POST', '/logout', 'logoutHandler'],
    ['GET', '/admin/uj-etel-letrehozasa', 'createDishHandler'],
    ['POST', '/create-dish', 'createDishPostHandler'],
    ['POST', '/update-dish/{id}', 'updateDishHandler'],
    ['GET', '/admin/etel-tipusok', 'dishTypeHandler'],
    ['POST', '/create-dish-type', 'createDishTypeHandler']
];

// Útvonalválasztó inicializálása
$dispatch = registerRoutes($routes);
$matchedRoute = $dispatch($method, $path);
$handlerFunction = $matchedRoute['handler'];
$handlerFunction($matchedRoute['vars']);

function createDishTypeHandler() {
   
    $pdo = getConnection();
    $statement = $pdo->prepare('INSERT INTO dishTypes (`name`, `slug`, `description`) VALUES (?, ?, ?)');
    $statement->execute([
        $_POST['name'],
        slugify($_POST['name']),
        $_POST['description']
    ]);

    header('Location: /admin/etel-tipusok');

}

function dishTypeHandler() {

    //Ha nincs belépve
    if(!isLoggedIn()) {
        echo render('wrapper.phtml', [
            'content' => render('login.phtml')
        ]);
        return;
    }

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishTypes');
    $statement->execute([]);
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo render('admin-wrapper.phtml', [
        'content' => render('dish-type-list.phtml', [
            'dishTypes' => $dishTypes
        ])
    ]);

}

function updateDishHandler($vars) {

    $pdo = getConnection();
    $statement = $pdo->prepare("UPDATE dishes SET `name` = ?, `slug` = ?, `description` = ?, `price` = ?, `isActive` = ?, `dishTypeId` = ? WHERE id = ?");
    $statement->execute([
        $_POST['name'],
        $_POST['slug'],
        $_POST['description'],
        $_POST['price'],
        (int)isset($_POST['isActive']),
        $_POST['dishTypeId'],
        $vars['id']
    ]);

    header("Location: /admin");

}

function createDishPostHandler() {
   
    $pdo = getConnection();
    $statement = $pdo->prepare('INSERT INTO dishes (`name`, `slug`, `description`, `price`, `isActive`, `dishTypeId`) VALUES (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )');
    $statement->execute([
        $_POST['name'],
        slugify($_POST['name']),
        $_POST['description'],
        $_POST['price'],
        isset($_POST['isActive']) ? '1' : '0',
        $_POST['dishTypeId']
    ]);

    header('Location: /admin');
}

function createDishHandler() {
    
    //Ha nincs belépve
    if(!isLoggedIn()) {
        echo render('wrapper.phtml', [
            'content' => render('login.phtml')
        ]);
        return;
    }

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishTypes');
    $statement->execute([]);
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo render('admin-wrapper.phtml', [
        'content' => render('create-dish.phtml', [
            'dishTypes' => $dishTypes
        ])
    ]);
    

}

function deleteDishHandler($vars) {
    
    $pdo =  getConnection();
    $statement = $pdo->prepare('DELETE FROM dishes WHERE id = ?');
    $statement->execute([$vars['id']]);

    header('Location: /admin');

}

function isLoggedIn() {
    if(!isset($_COOKIE[session_name()])) {
        return false;
    }
    session_start();
    if(!isset($_SESSION['userId'])) {
        return false;
    }
    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $statement->execute([$_SESSION['userId']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    if(!$user) {
        return false;
    }

    return true;
}

function loginHandler() {

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $statement->execute([$_POST['email']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        echo 'invalidCredentials';
        return;
    }

    $passwordVerified = password_verify($_POST['password'], $user['password']);
    if(!$passwordVerified) {
        echo 'invalidCredentials';
        return;        
    }

    session_start();
    $_SESSION['userId'] = $user['id'];

    header('Location: /admin');
}

function logoutHandler() {
    session_start();
    $params = session_get_cookie_params();
    setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    session_destroy();

    header('Location: /');
}

function adminHandler() {

    //Ha nincs belépve
    if(!isLoggedIn()) {
        echo render('wrapper.phtml', [
            'content' => render('login.phtml')
        ]);
        return;
    }

    //Ha be van lépve
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

// Handler függvények deklarálása
function homeHandler()
{

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishTypes');
    $statement->execute([]);
    $dishTypesFetch = $statement->fetchAll(PDO::FETCH_ASSOC);

    $dishTypes = [];

    foreach($dishTypesFetch as $index => $dishType) {
        $stat = $pdo->prepare('SELECT * FROM dishes WHERE dishTypeId = ? AND isActive = ?');
        $stat->execute([
            $dishType['id'],
            1
        ]);
        $dishes = $stat->fetchAll(PDO::FETCH_ASSOC);
        $dishType['dishes'] = $dishes;
        array_push($dishTypes, $dishType);
    }

    echo render("wrapper.phtml", [
        'content' => render('public-menu.phtml', [
            'dishTypes' => $dishTypes
        ])
    ]);
}

function dishEditHandler($vars)
{
    //Ha nincs belépve
    if(!isLoggedIn()) {
        echo render('wrapper.phtml', [
            'content' => render('login.phtml')
        ]);
        return;
    }

    $pdo = getConnection();
    $statement = $pdo->prepare('SELECT * FROM dishes WHERE slug = ?');
    $statement->execute([$vars['keresoBaratNev']]);
    $dish = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $pdo->prepare('SELECT * FROM dishTypes');
    $statement->execute([]);
    $dishTypes = $statement->fetchAll(PDO::FETCH_ASSOC);


    echo render('admin-wrapper.phtml', [
        'content' => render('edit-dish.phtml', [
            'dish' => $dish,
            'dishTypes' => $dishTypes
        ])
    ]);


}

function notFoundHandler()
{
    echo 'Oldal nem található';
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
