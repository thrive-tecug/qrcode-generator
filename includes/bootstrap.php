<?php
define("PROJECT_ROOT_PATH", __DIR__."/../");
define("OUTPUT_PATH", PROJECT_ROOT_PATH."OUTPUT/");
require_once PROJECT_ROOT_PATH."/includes/config.php";
// api 
require_once PROJECT_ROOT_PATH."/api/BaseController.php";
require PROJECT_ROOT_PATH."/api/UserController.php";
require PROJECT_ROOT_PATH."/api/ConfigurationController.php";
require PROJECT_ROOT_PATH."/api/ProjectController.php";
require PROJECT_ROOT_PATH."/api/PermissionController.php";

// model
require_once PROJECT_ROOT_PATH."/model/User.php";
require_once PROJECT_ROOT_PATH."/model/Configuration.php";
require_once PROJECT_ROOT_PATH."/model/Project.php";
require_once PROJECT_ROOT_PATH."/model/UserPermission.php";
require_once PROJECT_ROOT_PATH."/model/UserPermissionListing.php";

// include token system
require_once PROJECT_ROOT_PATH."/includes/authentication.php";

// utils
require_once PROJECT_ROOT_PATH."/includes/utils.php";
?>