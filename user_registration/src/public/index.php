<?php 

session_start();


use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Slim\Middleware\SessionCookie;

require '../vendor/autoload.php';

$app = new \Slim\App;

// Fetch DI Container
$container = $app->getContainer();

	// Register flash provider
	$container['flash'] = function () {
		return new \Slim\Flash\Messages();
	};
	
	
	// Register Twig View helper
	$container['view'] = function ($c) {
		$view = new \Slim\Views\Twig('templates', [
			// 'cache' => 'path/to/cache'
		]);

		// Instantiate and add Slim specific extension
		$basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
		$view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));
		$view->getEnvironment()->addGlobal('flash', $c['flash']); // this
		return $view;
	};
	
	//to set flash 
	$app->add(function ($request, $response, $next) {
		$this->view->offsetSet('flash', $this->flash);
		return $next($request, $response);
	});
	
	//route for register page
	$app->get('/user_registration', function (Request $request, Response $response, $args) {
		return $this->view->render($response, 'register.twig', []);
	});
	
	//route for adding a user
	$app->post('/add_user', function (Request $request, Response $response) {
		$config = require __DIR__ . '/config.php';
		$dsn = $config['settings']['mysql']['dsn'];
		$usr = $config['settings']['mysql']['usr'];
		$pwd = $config['settings']['mysql']['pwd'];

		//get the post req here
		$post_req = $request->getParams();
		$first_name = filter_var($post_req['first_name'], FILTER_SANITIZE_STRING);
		$last_name = filter_var($post_req['last_name'], FILTER_SANITIZE_STRING);
		$user_name = filter_var($post_req['user_name'], FILTER_SANITIZE_STRING);
		$user_password = $post_req['user_password'];
		
		if($request->isPost())
		{
			$pdo = new \Slim\PDO\Database($dsn, $usr, $pwd);
			
			//check if user exists
			$selectStatement = $pdo->select()
							   ->from('user_registration')
							   ->where('user_name', '=', $user_name);

			$stmt = $selectStatement->execute();
			$data = $stmt->fetch();
			
			if($data && $data['user_name']=="")
			{
				$this->flash->addMessage('error', 'User Name Field Is Mandatory');
				
				$this->view->render($response, 'register.twig', [
					'flash' => $this->flash
				]);

				// Redirect - render to be introduced
				return $response->withStatus(302)->withHeader('Location', '/user_registration');
			}	
			
			if($data['user_name']!="")
			{
				// Set flash message for next request
				$this->flash->addMessage('error', 'User Name already exists');
				
				$this->view->render($response, 'register.twig', [
					'flash' => $this->flash
				]);

				// Redirect - render to be introduced
				return $response->withStatus(302)->withHeader('Location', '/user_registration');
			}
			else
			{
				//insert into db
				$insertStatement = $pdo->insert(array('first_name', 'last_name', 'user_name', 'user_password'))
							   ->into('user_registration')
							   ->values(array($first_name, $last_name, $user_name, $user_password));

				$insertId = $insertStatement->execute(false);
				
				//render the welcome page
				$this->view->render($response, 'user.twig', [
					'flash' => $this->flash, 
					'name' => $user_name
				]);				
			}
		}		
	});
	
	//route for welcome page
	$app->get('/user_page', function (Request $request, Response $response, $args) {
		return $this->view->render($response, 'user.twig', []);
	});

	$app->run();