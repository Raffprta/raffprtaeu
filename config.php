<?php
//================================================================================
//
// Configuration file for Database information and global variables.
// 
//================================================================================

define('BASE_URL', 'http://localhost/');        // Root URL of this app 
define('DATABASE_HOST', 'localhost');           // Database location
define('DATABASE_NAME', 'raffprtaeu');          // Database name
define('DATABASE_USERNAME', 'root');            // Database username
define('DATABASE_PASSWORD', '');                // Database password
define('REDBEAN_FREEZE_ENABLED', false);        // Freeze RedBean for production use (performance increase)
define('REDBEAN_DEBUG_ENABLED', false);         // RedBean debug mode

//================================================================================
// Fixed Global Configuration
//================================================================================

// Access Level
define('ACCESS_LEVEL_USER',             0);     
define('ACCESS_LEVEL_ADMINISTRATOR',    1);    

// Klein flash message types
define('FLASH_SUCCESS',         'success');     // Success message
define('FLASH_ERROR',           'error');       // Error message
define('FLASH_INFO',            'info');        // Information message

