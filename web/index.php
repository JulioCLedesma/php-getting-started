<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use DI\Container;
use DI\Bridge\Slim\Bridge;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;

require(__DIR__.'/../vendor/autoload.php');

// Create DI container
$container = new Container();

// Add Twig to Container
$container->set(Twig::class, function() {
    return Twig::create(__DIR__.'/../views');
});

// Add Monolog to Container
$container->set(LoggerInterface::class, function () {
    $logger = new Logger('default');
    $logger->pushHandler(new StreamHandler('php://stderr'), Level::Debug);
    return $logger;
});

// Create main Slim app
$app = Bridge::create($container);
$app->addErrorMiddleware(true, false, false);

// Our web handlers
$app->get('/', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig) {
    $logger->debug('logging output.');
    return $twig->render($response, 'index.twig');
});

// Add Database connection to Container
$container->set(PDO::class, function() {
    $dburl = parse_url(getenv('DATABASE_URL') ?: throw new Exception('no DATABASE_URL'));
    return new PDO(sprintf(
        "pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s",
        $dburl['host'],
        $dburl['port'],
        ltrim($dburl['path'], '/'), // URL path is the DB name, must remove leading slash
        $dburl['user'],
        $dburl['pass'],
    ));
});

// Mostrar todos los registros
$app->get('/list', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig, PDO $pdo) {
    $stmt = $pdo->query('SELECT * FROM your_table');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $twig->render($response, 'list.twig', ['items' => $items]);
});

// Mostrar un registro individual
$app->get('/view/{id}', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig, PDO $pdo, $args) {
    $stmt = $pdo->prepare('SELECT * FROM your_table WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    return $twig->render($response, 'view.twig', ['item' => $item]);
});

// Editar un registro
$app->get('/edit/{id}', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig, PDO $pdo, $args) {
    $stmt = $pdo->prepare('SELECT * FROM your_table WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    return $twig->render($response, 'edit.twig', ['item' => $item]);
});

$app->post('/edit/{id}', function(Request $request, Response $response, LoggerInterface $logger, PDO $pdo, $args) {
    // Procesar la edición del registro
});

// Eliminar un registro
$app->get('/delete/{id}', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig, PDO $pdo, $args) {
    $stmt = $pdo->prepare('SELECT * FROM your_table WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    return $twig->render($response, 'delete.twig', ['item' => $item]);
});

$app->post('/delete/{id}', function(Request $request, Response $response, LoggerInterface $logger, PDO $pdo, $args) {
    // Procesar la eliminación del registro
});

// Agregar un registro
$app->get('/add', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig) {
    return $twig->render($response, 'add.twig');
});

$app->post('/add', function(Request $request, Response $response, LoggerInterface $logger, PDO $pdo) {
    // Procesar la adición del registro
    // Obtener los datos del formulario
    $name = $request->getParsedBody()['name']; // Suponiendo que el formulario tiene un campo llamado "name"

    // Validar los datos si es necesario

    // Insertar el nuevo registro en la base de datos
    $stmt = $pdo->prepare('INSERT INTO your_table (name) VALUES (:name)');
    $stmt->execute(['name' => $name]);

    // Redirigir a la página principal u otra página según sea necesario
    return $response->withHeader('Location', '/');
});

$app->run();
