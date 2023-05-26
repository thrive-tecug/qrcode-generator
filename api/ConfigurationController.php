<?php
// requests of form company/ pass here
require_once PROJECT_ROOT_PATH."includes/utils.php";

class ConfigurationController extends BaseController{

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

    public function get_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        if($name_chk){
            $name = $arrQueryStringParams["name"];
                $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
                $config = $this->user->get_configuration_by_name($name);
                if($config!==null){
                    $ret_assoc = ["status"=>"success", "data"=>$config->as_dict()];
                }else{
                    $ret_assoc = ["status"=>"failed", "message"=>"No such configuration!"];
                }
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }  

    public function add_action(){
        /* */
        $name_chk = isset($_POST['name']) && $_POST['name'];
        $folder_batch_chk = isset($_POST['folder_batch']) && $_POST['folder_batch'];
        $error_correction_chk = isset($_POST['error_correction']) && $_POST['error_correction'];
        $box_size_chk = isset($_POST['box_size']) && $_POST['box_size'];
        $border_chk = isset($_POST['border']) && $_POST['border'];
        $fore_color_chk = isset($_POST['fore_color']) && $_POST['fore_color'];
        $back_color_chk = isset($_POST['back_color']) && $_POST['back_color'];

        if($name_chk){
            $name = $_POST["name"];
            $cleaned_name = strtolower(Database::clean_string($name));
            if ($name!==$cleaned_name){
                $ret_assoc = ["status"=>"failed", "message"=>"The provided name contains spaces, special or uppercase characters."];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }
        if($folder_batch_chk && $error_correction_chk && $box_size_chk && $border_chk && $fore_color_chk && $back_color_chk){
            $name=$_POST['name'];
            $version=1;
            $folder_batch=intval($_POST['folder_batch']);
            $error_correction=$_POST['error_correction'];
            $box_size=intval($_POST['box_size']);
            $border=intval($_POST['border']);
            $fore_color=$_POST['fore_color'];
            $back_color=$_POST['back_color'];

            $error=false;
            if($folder_batch<1 || !in_array($error_correction, Configuration::$error_correction_levels) || $box_size<1 || $border<1){
                $error=true;
            }else if(!validate_hex_color($fore_color) || !validate_hex_color($back_color)){
                $error=true;
            }else if($fore_color===$back_color){
                $error=true;
            }
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            if($error){
                $ret_assoc = ["status"=>"failed", "message"=>"Invalid input!"];
            }else{
                $exist_config = $this->user->get_configuration_by_name($name);
                if($exist_config!==null){
                    $ret_assoc = ["status"=>"failed", "message"=>"Configuration name already exists!"];
                }else{
                    $this->user->add_configuration($name,$folder_batch,$version,$error_correction,$box_size,$border,$fore_color,$back_color);
                    $ret_assoc = ["status"=>"success", "data"=>"Conguration added successfully!"];
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable Entity");
        }
    }

    public function list_action(){
        if(true){
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $configs = $this->user->get_configurations();
            if($configs!==null && count($configs)>0){
                $data = [];
                foreach($configs as $config){
                    array_push($data, $config->as_dict());
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable Entity");
        }
    }

    public function search_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $text_chk = isset($arrQueryStringParams["text"]) &&  $arrQueryStringParams["text"];
        if($text_chk){
            $text = $arrQueryStringParams["text"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $configs = $this->user->find_configurations_like($text);
            if($configs!==null){
                $data = [];
                foreach($configs as $config){
                    array_push($data, $config->as_dict());
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function get_error_levels(){
        if(true){
            $ret_assoc = ["status"=>"success", "data"=>Configuration::$error_correction_levels];
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function preview_action(){
        $name_chk = isset($_POST['name']) && $_POST['name'];
        $folder_batch_chk = isset($_POST['folder_batch']) && $_POST['folder_batch'];
        $error_correction_chk = isset($_POST['error_correction']) && $_POST['error_correction'];
        $box_size_chk = isset($_POST['box_size']) && $_POST['box_size'];
        $border_chk = isset($_POST['border']) && $_POST['border'];
        $fore_color_chk = isset($_POST['fore_color']) && $_POST['fore_color'];
        $back_color_chk = isset($_POST['back_color']) && $_POST['back_color'];

        if($name_chk && $folder_batch_chk && $error_correction_chk && $box_size_chk && $border_chk && $fore_color_chk && $back_color_chk){
            $name=strtolower(Database::clean_string($_POST['name']));
            $folder_batch=intval($_POST['folder_batch']);
            $error_correction=$_POST['error_correction'];
            $box_size=intval($_POST['box_size']);
            $border=intval($_POST['border']);
            $fore_color=$_POST['fore_color'];
            $back_color=$_POST['back_color'];

            $error=false;
            if($folder_batch<1 || !in_array($error_correction, Configuration::$error_correction_levels) || $box_size<1 || $border<1){
                $error=true;
            }else if(!validate_hex_color($fore_color) || !validate_hex_color($back_color)){
                $error=true;
            }
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            if($error){
                $ret_assoc = ["status"=>"failed", "message"=>"Invalid input!"];
            }else{
                $output = execute_script("preview",$name,1,1,1,1,"","",$folder_batch,$error_correction,$box_size,$border,$fore_color,$back_color);
                $ret_assoc = json_decode($output, true);
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }
}



?>