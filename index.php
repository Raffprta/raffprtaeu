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

//================================================================================
// Index page.
//================================================================================
$klein->respond('GET', '/', function() use ($twig) { 
    displayPage('index.twig', null);
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
