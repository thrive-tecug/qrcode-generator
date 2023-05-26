<?php
// base class for utility methods used by other controllers

class BaseController{
    
    public static function cors(){
        $target = "*"; // "http://localhost:3000";
        if(isset($_SERVER["HTTP_ORIGIN"])){
            // define if ORIGIN is allowed
            header('Access-Control-Allow-Origin:'.$target);
            header("Access-Control-Allow-Credential:true");
            header("Access-Control-Max-Age:86400"); // cache for 1 day
            header("Access-Control-Allow-Headers:Origin,Content-Type,Accept");
            header("Access-Control-Allow-Methods:GET,POST");
            header("HTTP/1.1 200 OK");
        }else{
            header('Access-Control-Allow-Origin:'.$target);
            header("Access-Control-Allow-Headers:Content-Type,Authorization");
            header("Access-Control-Allow-Methods:GET,POST");
            header("Access-Control-Allow-Headers:Origin,Content-Type,Accept");
            header("HTTP/1.1 200 OK");
        }

        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS"){
            header('Access-Control-Allow-Origin:'.$target);
            if (isset($_SERVER['HTTP_ACCESS_ACCESS_CONTROL_REQUEST_METHOD'])){
                header("Access-Control-Allow-Methods:GET,POST,OPTIONS");
            }
            header("Access-Control-Allow-Methods:GET,POST");
            header("Access-Control-Allow-Headers:Content-Type,Authorization");
            header("Access-Control-Allow-Methods:GET,POST,OPTIONS");
            header("HTTP/1.1 200 OK");
            exit(0);
        }
    }

    public static function send_fail(){
        header("HTTP/1.1 404 Not Found");
        exit();
    }

    public function log($data){
        error_log(print_r($data, TRUE));
    }

    /* __call magic method */
    public function __call($name, $arguments){
        $this->send_output('', array('HTTP/1.1 404 Not Found'));
    }

    /** Get URI elements @return array*/
    protected function get_uri_segments(){
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = explode('/', $uri);
        return $uri;
    }

    /** Get querystring params @return array */
    public static function get_query_string_params(){
        parse_str($_SERVER['QUERY_STRING'], $query);
        return $query;
    }

    /**Send API output */
    public static function send_output($data, $httpHeaders=array()){
        header_remove('Set-Cookie');
        if (is_array($httpHeaders) && count($httpHeaders)){
            foreach($httpHeaders as $httpHeader){
                header($httpHeader);
            }
        }
        echo $data;
        exit();
    }

    public static function send_json($json){
        // sends json data with http status ok
        self::send_output(
            $json,
            array('Content-Type: application/json', 'HTTP/1.1 200 OK')
        );
    }

    public static function send_invalid_token(){
        $json = json_encode(["status"=>"failed", "message"=>"Invalid token!"]);
        self::send_output(
            $json,
            array('Content-Type: application/json', 'HTTP/1.1 200 OK')
        );
    }

    public static function send_method_not_allowed(){
        $strErrorDesc = 'Method Not Allowed.';
        $strErrorHeader = 'HTTP/1.1 422 Internal Server Error';
        self::send_output(
            json_encode(array('error' => $strErrorDesc)),
            array('Content-Type: application/json', $strErrorHeader)
        );
    }

    public static function send_internal_error($errorMessage){
        $strErrorHeader = 'HTTP/1.1 500 Internal Server Error';
        self::send_output(
            json_encode(array('error' => $errorMessage)),
            array('Content-Type: application/json', $strErrorHeader)
        );
    }

    public function send_unprocessable_entity(){
        self::sendInternalError("Unprocessable Entity.");
    }
}

?>