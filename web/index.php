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


$app->get('/db', function(Request $request, Response $response, LoggerInterface $logger, Twig $twig, PDO $pdo) {
    $st = $pdo->prepare('SELECT name FROM test_table');
    $st->execute();
    $names = array();
    while($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $logger->debug('Row ' . $row['name']);
      $names[] = $row;
    }
    return $twig->render($response, 'database.twig', [
      'names' => $names,
    ]);
  });

  
  // Agregar nuevo registro
  $app->post('/add', function(Request $request, Response $response, PDO $pdo) {
    // Retrieve data from the form
    $name = $request->getParsedBody()['name'];
    
    // Insert the new record into the database
    $stmt = $pdo->prepare("INSERT INTO test_table (name) VALUES (:name)");
    $stmt->execute(['name' => $name]);
  
    // Redirect back to the database page
    return $response->withHeader('Location', '/db');
  });


$app->run();
