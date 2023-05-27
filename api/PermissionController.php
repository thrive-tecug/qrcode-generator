<?php

class PermissionController{
    private $user=null;

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

    public function list_action(){
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $data = UserPermission::get_permissions_as_dict();
            $ret_assoc = ["status"=>"success", "data"=>$data];
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
    }

    public function get_action(){
        // get permissions for calling user
        if(true){
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $permissions = $this->user->get_permissions_for_user($this->user->id);
            if($permissions!==null){
                $data=[];
                foreach($permissions as $listing){
                    $code = UserPermission::get_code_from_id($listing->permission_id);
                    array_push($data, $code);
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);  
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function set_action(){
        $username_chk = isset($_POST["username"]) &&  $_POST["username"];
        $list_chk = isset($_POST["permissions"]);
        $list = [];
        if ($list_chk && $_POST["permissions"]){
            try{
                $list = json_decode($_POST["permissions"]);
            }catch(Error $e){
                $list_chk=false;
            }
        }
        if ($username_chk && $list_chk){
            $username = $_POST["username"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $target_user = User::fromUsername($username);
            if ($target_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such user!"];
            }else if($this->user->id==$target_user->id){
                $ret_assoc = ["status"=>"failed", "message"=>"Cannot change personal permissions!"];
            }else{
                $permission_ids = UserPermission::get_ids_from_codes($list);
                $this->user->grant_permissions($target_user->id, $permission_ids);
                $ret_assoc = ["status"=>"success", "data"=>"Operation successful!"];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

}

?>