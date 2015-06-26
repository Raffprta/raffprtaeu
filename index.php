<?php

//================================================================================
// Setup
//================================================================================

// Enable verbose debug output
ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

// Include libraries
require_once __DIR__.'/config.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/rb.php';

// Update request when app is installed in a subdirectory
$base = dirname($_SERVER['PHP_SELF']);
$trimmedBase = ltrim($base, '/');

// If we're not at the root of the server (Windows and Un*x), alter request
if ($trimmedBase !== '\\' && $trimmedBase !== '') {
    $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen($base));
}

// Init Klein router
$klein = new Klein\Klein();

// Init Twig template engine
$loader = new Twig_Loader_Filesystem('templates');
$twig = new Twig_Environment($loader);

// Add global URL
$twig->addGlobal('baseURL', BASE_URL);

// Initialise database.
init();

//================================================================================
// Index page.
//================================================================================
$klein->respond('GET', '/', function() use ($twig) { 
    displayPage('index.twig', null);
});

//================================================================================
// Admin page.
//================================================================================
$klein->respond('GET', '/admin_post', function ($request, $response, $service) {
    displayPage('admin_post.twig', null);
});

$klein->respond('POST', '/admin_post', function ($request, $response, $service) {
	// Get the information if there's a new blog post to be sent.
	$title = $_POST['blogTitle'];
	$content = $_POST['new'];
	$tags = $_POST['tags'];
	
	// Edit a post information.
	$edit = $_POST['edit'];
	$id = $_POST['id'];
	
	// Grab the password entered.
	$pass = $_POST['pass'];
	
	$errors = [];
	
	$wantsToMakeANewBlog = !empty($title) && !empty($content) && !empty($tags);
	$wantsToEditABlog = !empty($edit) && !empty($id);
	
	// If a new post is being made
	if($wantsToMakeANewBlog){
		if($pass == ADMIN_PASS){
			// The post can be made.
			$blog = R::dispense('blog');
			$blog->title = $title;
			$blog->dateWhen = date('Y-m-d');
			$blog->tags = $tags;
			$blog->content = $content;
			$blog->likes = 0;

			// Save to database
			R::store($blog);
		}else{
			$errors[] = "Your password was typed in incorrectly";
		}
	}else{
		if(!$wantsToEditABlog)
		    $errors[] = "Ensure you filled out all of the details in the new blog fields";
	}
	
	// Check if the user wanted to edit anything.
	if($wantsToEditABlog){
		if($pass == ADMIN_PASS){
			// Find the post you want to edit
			$blog = R::load('blog', $id);
			// If a blog post was found.
			if($blog->id){
				$blog->content = $edit;
				// Save to database
				R::store($blog);
			}else{
				$errors[] = "The ID is incorrect";
			}
		}else{
			$errors[] = "Your password was typed in incorrectly";
		}
	}else{
		if(!$wantsToMakeANewBlog)
		    $errors[] = "Ensure you filled out all of the details in the edit fields";
	}
	
    displayPage('admin_post.twig', array('errors' => $errors));
	
});

// URL routing handlers are now installed; try to dispatch the request
$klein->dispatch();

//================================================================================
// Functions
//================================================================================

/*
 * Generic page display function, written by http://github.com/dwhinham and modified 
 * by myself.
 */
function displayPage($template, $extraVars) {
    global $twig;
    global $klein;
    $twigVars = array();
	
    // Pass any Klein flash messages to Twig
    $twigVars['errorMessages'] = $klein->service()->flashes(FLASH_ERROR);
    $twigVars['successMessages'] = $klein->service()->flashes(FLASH_SUCCESS);
    $twigVars['infoMessages'] = $klein->service()->flashes(FLASH_INFO);
	
    // Pass any extra variables to Twig
    if (!is_null($extraVars)) {
        $twigVars = array_merge($twigVars, $extraVars);
    }
    // Render the template
    echo $twig->render($template, $twigVars);
}

//================================================================================
// Database Setup Helper
//================================================================================
function init() {
    // Setup our RedBean environment.
    R::setup('mysql:host=' . DATABASE_HOST . '; dbname=' . DATABASE_NAME, DATABASE_USERNAME, DATABASE_PASSWORD, REDBEAN_FREEZE_ENABLED);
	
	// Attempt to connect to the configured Database 
    if (!R::testConnection()) {
        throw new Exception('Couldn\'t connect to database. Please check backend configuration.');
    }
    // Enable debug mode
    R::debug(REDBEAN_DEBUG_ENABLED);
	
    // Use camelCase for bean export
    R::useExportCase('camel');
}
