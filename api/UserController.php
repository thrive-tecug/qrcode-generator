<?php
// requests to user/ pass through here

class UserController{
    public $user=null;

    public function __construct($username){
        $this->user = User::fromUsername($username);
    }

    public function call($method_name, $check_user=true){
        if ($check_user && $this->user===null){
            $ret_assoc=["status"=>"failed", "message"=>"No such user!"];
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
            return;
        }

        try{
            $this->{$method_name}();
        }catch(Error $e){
            BaseController::send_internal_error($e->getMessage().", Something went wrong on our end!");
        }
    }
    
    // [POST] user/verify?
    public function login_action(){
        /* */
        $ident_chk = isset($_POST["identifier"]) &&  $_POST["identifier"];
        $passw_chk = isset($_POST["password"]) &&  $_POST["password"];

        if ($passw_chk && $ident_chk){
            $in_identifier = $_POST["identifier"];
            $in_password = $_POST["password"];
            $log_user = User::fromUsername($in_identifier);
            if($log_user===null){
                $log_user = User::fromEmail($in_identifier);
            }
            if ($log_user!==null){
                if(!$log_user->approved){
                    $ret_assoc = ['status'=>'failed', 'message'=>"Account not yet approved by admin!"];
                }else if ($log_user->password===$in_password){  // to-do : hash
                    $token = Tokenizer::tokenize(["username"=>$log_user->username]);
                    $data = $log_user->as_dict();
                    $data["token"]=$token;
                    $ret_assoc = ["status"=>"success", "data"=>$data];
                }else{
                    $ret_assoc = ['status'=>'failed', 'message'=>"Invalid username/email or password"];
                }
                $responseData = json_encode($ret_assoc);
            }else{
                $ret_assoc = ['status'=>'failed', 'message'=>"Username/Email does not exist!"];
                $responseData = json_encode($ret_assoc);
            }
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function get_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $username_chk = isset($arrQueryStringParams["username"]) &&  $arrQueryStringParams["username"];
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $user = $this->user->get_user($username);
            if($user!==null){
                $ret_assoc = ["status"=>"success", "data"=>$user->as_dict()];
            }else{
                $ret_assoc = ["status"=>"failed", "message"=>"No such user!"];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function get_permissions_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $username_chk = isset($arrQueryStringParams["username"]) &&  $arrQueryStringParams["username"];
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            if ($this->user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such user!"];
            }else{
                $target_user = User::fromUsername($username);
                $permissions = $this->user->get_permissions_for_user($target_user->id);
                if($permissions!==null){
                    $data = [];
                    foreach($permissions as $listing){
                        $code = UserPermission::get_code_from_id($listing->permission_id);
                        array_push($data, $code);
                    }
                    $ret_assoc = ["status"=>"success", "data"=>$data];
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function add_action(){
        $name_chk = isset($_POST["name"]) &&  $_POST["name"];
        $username_chk = isset($_POST["username"]) &&  $_POST["username"];
        $email_chk = isset($_POST["email"]) && $_POST["email"];
        $password_chk = isset($_POST["password"]) && $_POST["password"];

        if($username_chk){
            $username = $_POST["username"];
            $cleaned_username = strtolower(Database::clean_string($username));
            if ($username!==$cleaned_username){
                $ret_assoc = ["status"=>"failed", "message"=>"The provided username contains spaces, special or uppercase characters."];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }
        if($name_chk && $email_chk && $password_chk){
            $name=$_POST["name"];
            $username=$_POST["username"];
            $email=$_POST["email"];
            $password=$_POST["password"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $exist_user = $this->user->get_user($username);
            if ($exist_user===null){
                $this->user->add_user($name,$username,$email,$password,null,true);
                $ret_assoc = ["status"=>"success", "data"=>"user added!"];
            }else{
                $ret_assoc = ["status"=>"failed", "message"=>"user already exists!"];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function register_action(){
        $name_chk = isset($_POST["name"]) &&  $_POST["name"];
        $username_chk = isset($_POST["username"]) &&  $_POST["username"];
        $email_chk = isset($_POST["email"]) && $_POST["email"];
        $password_chk = isset($_POST["password"]) && $_POST["password"];

        if($username_chk){
            $username = $_POST["username"];
            $cleaned_username = strtolower(Database::clean_string($username));
            if ($username!==$cleaned_username){
                $ret_assoc = ["status"=>"failed", "message"=>"The provided username contains spaces, special or uppercase characters."];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }
        if($name_chk && $email_chk && $password_chk){
            $name=$_POST["name"];
            $username=$_POST["username"];
            $email=$_POST["email"];
            $password=$_POST["password"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $exist_users =  User::fetch_instances_by_condition(["username"=>[$username, 's']]);
            if ($exist_users===null || count($exist_users)==0){
                User::__add_user($name,$username,$email,$password,null,false);
                $ret_assoc = ["status"=>"success", "data"=>"user added!"];
            }else{
                $ret_assoc = ["status"=>"failed", "message"=>"user already exists!"];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function count_action(){
        if(true){
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $count = $this->user->count_users();
            if ($count!==null){
                $ret_assoc = ["status"=>"success", "data"=>$count];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function list_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $start_chk = isset($arrQueryStringParams["start"]) && $arrQueryStringParams["start"];
        $limit_chk = isset($arrQueryStringParams["limit"]) && $arrQueryStringParams["limit"];

        $start = 0;
        if($start_chk){
            $_start = $arrQueryStringParams["start"];
            if (is_numeric($_start)){
                $start = intval($_start);
            }
        }
        $limit = null;
        if($limit_chk){
            $_limit = $arrQueryStringParams["limit"];
            if (is_numeric($_limit)){
                $limit = intval($_limit);
            }
        }

        if(true){
            try{
                $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
                $users = $this->user->get_users($limit, $start);
                $data=[];
                foreach($users as $user){
                    array_push($data, $user->as_dict());
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
            }catch(Error $e){
                BaseController::send_internal_error($e->getMessage().", Sorry, something went wrong on our end!");
            }
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function search_action(){
        // search by username
        $arrQueryStringParams = BaseController::get_query_string_params();
        $text_chk = isset($arrQueryStringParams["text"]) &&  $arrQueryStringParams["text"];
        $start_chk = isset($arrQueryStringParams["start"]) && $arrQueryStringParams["start"];
        $limit_chk = isset($arrQueryStringParams["limit"]) && $arrQueryStringParams["limit"];

        $start = 0;
        if($start_chk){
            $_start = $arrQueryStringParams["start"];
            if (is_numeric($_start)){
                $start = intval($_start);
            }
        }
        $limit = null;
        if($limit_chk){
            $_limit = $arrQueryStringParams["limit"];
            if (is_numeric($_limit)){
                $limit = intval($_limit);
            }
        }

        if($text_chk){
            $text = $arrQueryStringParams["text"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $users_by_username = $this->user->find_users_like_username($text, $limit, $start);
            // add by name to-do
            if($users_by_username!==null){
                $data = [];
                foreach($users_by_username as $user){
                    array_push($data, $user->as_dict());
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function approve_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $username_chk = isset($arrQueryStringParams["username"]) &&  $arrQueryStringParams["username"];
        $approved_chk = isset($arrQueryStringParams["approve"]);
        if ($username_chk && $approved_chk){
            $username = $arrQueryStringParams["username"];
            $approved = $arrQueryStringParams["approve"] ? 1 : 0;
            $exist_user = User::fromUsername($username);
            $ret_assoc = ["status"=>"failed", "message"=>"User doesn't exist"];
            if ($exist_user!==null){
                $exist_user->update_approved_status($approved);
                $ret_assoc = ["status"=>"success", "data"=>"Operation successful!"];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function edit_action(){
        $name_chk = isset($_POST["name"]) &&  $_POST["name"];
        $username_chk = isset($_POST["username"]) &&  $_POST["username"];
        $email_chk = isset($_POST["email"]) && $_POST["email"];
        $old_password_chk = isset($_POST["password_old"]) && $_POST["password_old"];
        $new_password_chk = isset($_POST["password_new"]) && $_POST["password_new"];
        if($name_chk && $username_chk && $email_chk && $old_password_chk && $new_password_chk){
            $name = $_POST["name"];
            $username = $_POST["username"];
            $email = $_POST["email"];
            $old_password = $_POST["password_old"];
            $new_password = $_POST["password_new"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured!"];
            $exist_user = User::fromUsername($username);
            if($exist_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"Username doesn't exist!"];
            }else{
                $ret_assoc = ["status"=>"success", "data"=>"Operation successful!"];
                if($name!==$exist_user->name || $email!==$exist_user->email || $new_password!=$exist_user->password){
                    if($old_password!==$exist_user->password){
                        $ret_assoc = ["status"=>"failed", "message"=>"Old password is incorrect!"];
                    }else{
                        $exist_user->edit($name,$username,$email,$new_password);
                    }
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

}
?>