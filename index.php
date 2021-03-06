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
	$blogs = R::findAll('blog', 'ORDER BY id DESC LIMIT 5');
    displayPage('index.twig', array('posts' => $blogs));
});

//================================================================================
// Blog View page.
//================================================================================
$klein->respond('GET', '/view/[i:id]', function ($request, $response, $service) { 
    $blog = R::findOne('blog', 'id = ?', array($request->id));
	$posts = R::findAll('post', 'blog_id = ? ORDER BY id DESC', array($blog->id));
    displayPage('view.twig', array('blog' => $blog, 'posts' => $posts));
});

//================================================================================
// Posting page.
//================================================================================
$klein->respond('POST', '/post/[i:id]', function ($request, $response, $service) { 
    // Create a new post bean and populate its contents from the form.
    $post = R::dispense('post');
	$post->content = $request->post;
	$post->blog = R::findOne('blog', 'id = ?', array($request->id));
	$post->uniquePoster = md5($_SERVER['REMOTE_ADDR']);
	R::store($post);
	// Display page saying that the post was successful or otherwise.
	if($post)
	    displayPage('success.twig', null);
	else
		displayPage('success.twig', array('error' => "Your post could not be created at this time"));
});

//================================================================================
// Likes page.
//================================================================================
$klein->respond('POST', '/like/[i:id]', function ($request, $response, $service) { 
    // Create a new like bean in case it did not exist.
    $like = R::dispense('likes');
	// The blog that the like belongs to
	$like->blog = R::findOne('blog', 'id = ?', array($request->id));
	// The IP Address to prevent duplicate likes on the same blog.
	$like->ip = $_SERVER['REMOTE_ADDR'];
	
	// Check for duplicates
	$isDupe = R::findAll('likes', 'blog_id = :blogId AND ip = :ipaddr', 
	                     array('blogId' => $request->id, 'ipaddr' => $_SERVER['REMOTE_ADDR']));
	
	if(count($isDupe) == 0){
		// Update the amount of likes but only if there is no duplicate;
		$like->blog->likes++;
		// Store our bean to the DB
		R::store($like);
		displayPage('success.twig', null);
	}else{
		displayPage('success.twig', array('error' => "You have already liked this blog post"));
	}
});

//================================================================================
// Admin page.
//================================================================================
$klein->respond('GET', '/admin_post', function ($request, $response, $service) {
    displayPage('admin_post.twig', null);
});

$klein->respond('GET', '/search', function ($request, $response, $service) {
	
	$month = null;
	$year = null;
	$tags = null;
	$errors = null;
	$onRootSearch = true;
	
	// Check if there's anything set
	if((isset($_GET['month']) && isset($_GET['year'])) || isset($_GET['tags'])){
		$month = $_GET['month'];
		$year = $_GET['year'];
		$tags = $_GET['tags'];
		$onRootSearch = false;
	}
	
	// Check what the user searched for.
	$searchingByDate = !empty($month) && !empty($year);
	$searchingByTags = !empty($tags);
	
	// Check if the user entered anything.
	if(!$searchingByDate && !$searchingByTags && !$onRootSearch){
		$errors[] = "Please search by either a date or by a tag";
	}
	
	$searchResults = null;
	
	// If the user is searching by dates.
	if($searchingByDate){
		// Find all matching beans
		$searchResults = R::find('blog','YEAR(date_when) = :year AND MONTH(date_when) = :month ',
       		array('year' => $year, 'month' => $month));
		
		if(count($searchResults) == 0){
			$errors[] = "There exist no blog posts at this date";
		}
		
	}
	
	$tagsSearchResults = null;
	
	// Or by tags
	if($searchingByTags){
		// Separate all the Tags that the user searched with
		$tagsToFind = explode(",", strtolower($tags), -1);

		foreach($tagsToFind as $tagsQuery){
			$tagsSearchResults[] = R::find('blog', 'tags LIKE ?', ['%'. $tagsQuery .'%']);
		}
		
		if(count($tagsSearchResults) == 0){
			$errors[] = "There exist no blog posts with this tag";
		}
	
	
	}
	
	if(count($searchResults) == 0 ){
		$searchResults = null;
	}
	
	if(count($tagsSearchResults) == 0){
		$tagsSearchResults = null;
	}

	// Display the page handling any errors.	
    displayPage('search.twig', array('searchByDate' => $searchResults, 
	                                 'searchByTag' => $tagsSearchResults,
									 'ofMonth' => $month,
									 'ofYear' => $year,
									 'tags' => $tags,
									 'errors' => $errors));
									 
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
			$blog->tags = strtolower($tags);
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
