<?php
/**
 * PHPUnit Bootstrap for CodeIgniter 3
 * 
 * This file sets up the CodeIgniter environment for PHPUnit tests
 */

// Set the environment to testing
define('ENVIRONMENT', 'testing');

// Define path constants
$system_path = '../system';
$application_folder = '../application';

// Set the current directory correctly
define('BASEPATH', realpath(dirname(__FILE__) . '/' . $system_path) . '/');
define('APPPATH', realpath(dirname(__FILE__) . '/' . $application_folder) . '/');
define('FCPATH', realpath(dirname(__FILE__) . '/..') . '/');
define('VIEWPATH', APPPATH . 'views/');

// Composer autoloader
if (file_exists(dirname(__FILE__) . '/../vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/../vendor/autoload.php';
}

// Load CodeIgniter core classes manually for testing
require_once BASEPATH . 'core/Common.php';

// Get config for session
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Helper functions for testing
if (!function_exists('get_instance')) {
    /**
     * Get CodeIgniter super-object mock for testing
     * @return stdClass
     */
    function &get_instance()
    {
        static $CI;
        if (!isset($CI)) {
            $CI = new stdClass();
        }
        return $CI;
    }
}
