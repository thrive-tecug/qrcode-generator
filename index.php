<?php
require __DIR__."/includes/bootstrap.php";

// handle preflights and final requests by clients
BaseController::cors();
// get tokens if any
$token_data = Tokenizer::detokenize_from_request(false);

$START_INDEX = 3; // start index of url params
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);

// watch out for short queries that cannot make it to $START_INDEX
$executed = false;

Database::init();

$URI_REGISTER = [
    "user/login"=>["UserController", "login_action", "POST", false],
    "user/add"=>["UserController", "add_action", "POST", true],
    "user/register"=>["UserController", "register_action", "POST", false],
    "user/edit"=>["UserController", "edit_action", "POST", true],
    "user/get"=>["UserController", "get_action", "GET", true],
    "user/list"=>["UserController", "list_action", "GET", true],
    "user/count"=>["UserController", "count_action", "GET", true],
    "user/search"=>["UserController", "search_action", "GET", true],
    "user/permissions"=>["UserController", "get_permissions_action", "GET", true],
    "user/approve"=>["UserController", "approve_action", "GET", true],

    "project/progress"=>["ProjectController", "progress_action", "GET", true],
    "project/add"=>["ProjectController", "add_action", "POST", true],
    "project/list"=>["ProjectController", "list_action", "GET", true],
    "project/downloads"=>["ProjectController", "downloads_action", "GET", true],
    "project/download"=>["ProjectController", "download_action", "GET", false],
    "project/pause"=>["ProjectController", "pause_action", "GET", true],
    "project/resume"=>["ProjectController", "resume_action", "GET", true],
    "project/bill"=>["ProjectController", "bill_action", "GET", true],
    "project/delete"=>["ProjectController", "delete_action", "GET", true],
    "project/cancel"=>["ProjectController", "cancel_action", "GET", true],
    "project/search"=>["ProjectController", "search_action", "GET", true],
    "project/get"=>["ProjectController", "get_action", "GET", true],
    "project/count"=>["ProjectController", "count_action", "GET", true],
    "project/preview_image"=>["ProjectController", "preview_image_action", "POST", true],
    "project/preview_text"=>["ProjectController", "preview_text_action", "POST", true],

    "configuration/get"=>["ConfigurationController", "get_action", "GET", true],
    "configuration/list"=>["ConfigurationController", "list_action", "GET", true],
    "configuration/add"=>["ConfigurationController", "add_action", "POST", true],
    "configuration/search"=>["ConfigurationController", "search_action", "GET", true],
    "configuration/error_levels"=>["ConfigurationController", "get_error_levels", "GET", true],
    "configuration/preview"=>["ConfigurationController", "preview_action", "POST", true],

    "permission/list"=>["PermissionController", "list_action", "GET", true],
    "permission/get"=>["PermissionController", "get_action", "GET", true],
    "permission/set"=>["PermissionController", "set_action", "POST", true]
];

if(count($uri) >= $START_INDEX+2){
    $uri_controller = $uri[$START_INDEX]; // eg user/
    $control_point = $uri[$START_INDEX+1]; // e.g. list, get
    if(!isset($uri_controller) || !isset($control_point)){
        header("HTTP/1.1 404 Not Found");
        exit();
    }
    $uripart = implode("/", [$uri_controller, $control_point]);
    if (array_key_exists($uripart, $URI_REGISTER)){
        $uridata = $URI_REGISTER[$uripart];
        $uri_controller = $uridata[0];
        $uri_controller_method = $uridata[1];
        $uri_request_method = $uridata[2];
        $extract_token = $uridata[3];

        if(strtoupper($_SERVER["REQUEST_METHOD"])!==$uri_request_method){
            BaseController::send_method_not_allowed();
        }

        $valid = true;
        if($extract_token){
            if($token_data){
                $username = $token_data["username"];
                if($username!==null && strlen($username)>0){
                    $objFeedController = new $uri_controller($username);
                }else{
                    BaseController::send_invalid_token();
                    $valid = false;
                }
            }else{
                BaseController::send_invalid_token();
                $valid = false;
            }
        }else{
            $objFeedController = new $uri_controller(null);
        }

        if($valid){
            $objFeedController->call($uri_controller_method, $extract_token);
            $executed = true;
        }
    } 
}

if(!$executed){
    header("HTTP/1.1 404 Not Found");
    exit();
}
?>