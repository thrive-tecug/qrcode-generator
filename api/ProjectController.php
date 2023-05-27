<?php

class ProjectController {
    public static $UPLOAD_FOLDER = PROJECT_ROOT_PATH."//UPLOADS/";
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

    public function add_action(){
        /*
        adds and starts a project; under the token user
        The generation is not necessarily threaded thus it may use some of php server's resources
        */
        $name_chk = isset($_POST["name"]) &&  $_POST["name"];
        $start_value_chk = isset($_POST["start_value"]);
        $total_chk = isset($_POST["total"]) &&  $_POST["total"];
        $qr_serial_length_chk = isset($_POST["qr_serial_length"]);
        $csv_serial_length_chk = isset($_POST["csv_serial_length"]);
        $pre_string_chk = isset($_POST["pre_string"]);
        $pro_string_chk = isset($_POST["pro_string"]);
        $config_name_chk = isset($_POST["config_name"]) && $_POST["config_name"];

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
        if($start_value_chk && $total_chk && $qr_serial_length_chk && $csv_serial_length_chk && $config_name_chk && $pre_string_chk && $pro_string_chk){
            $name = $_POST["name"];
            $start_value = $_POST["start_value"];
            $total = $_POST["total"];
            $description = $_POST["description"];
            $qr_serial_length = $_POST["qr_serial_length"];
            $csv_serial_length = $_POST["csv_serial_length"];
            $pre_string = $_POST["pre_string"];
            $pro_string = $_POST["pro_string"];
            $config_name = $_POST["config_name"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
                
            $exist_project = ($this->user)->get_project($name);
            if($exist_project!==null){
                $ret_assoc = ["status"=>"failed", "message"=>"A project with this name already exists!"];
            }else{
                $config = ($this->user)->get_configuration_by_name($config_name);
                if($config===null){
                    $ret_assoc = ["status"=>"failed", "message"=>sprintf("Unknown configuration `%s`", $config_name)];
                }else{
                    $foldername=$this->user->username."/".$name;
                    // add -v
                    $this->user->add_new_project(
                        $name,$description,$start_value,$total,$pre_string,$pro_string,$csv_serial_length,$qr_serial_length,
                        $config->id,0,null,Project::$STATE_ACTIVE
                    );
                    $ret_assoc = ["status"=>"success", "data"=>"Project added successfully!"];
                    // python script
                    $output = execute_script(
                        "generate",$foldername,$start_value,$total,$qr_serial_length,$csv_serial_length,$pre_string,$pro_string,
                        $config->folder_batch,$config->error_correction,$config->box_size,$config->border,$config->fgcolor,$config->bgcolor,0
                    );
                    $output_assoc = json_decode($output, true);
                    if ($output_assoc){
                        $ret_assoc = $output_assoc;
                    }
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function resume_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($name_chk){
            $name = $arrQueryStringParams["name"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $project = $project_user->get_project($name);
            if($project===null){
                $ret_assoc = ["status"=>"failed", "message"=>"Project does not exist!"];
            }else{
                if ($project->state===Project::$STATE_PAUSED){
                    $config = Configuration::fromId($project->configuration_id);
                    if($config===null){
                        $ret_assoc = ["status"=>"failed", "message"=>"Unknown configuration!"];
                    }else{
                        $message_path=$project_user->username."/".$name;
                        // script
                        $output = execute_script(
                            "generate",
                            $message_path,$project->start_value,$project->total,$project->qr_serial_length,$project->csv_serial_length,
                            $project->pre_string,$project->pro_string,
                            $config->folder_batch,$config->error_correction,$config->box_size,$config->border,$config->fgcolor,$config->bgcolor,
                            $project->progress
                        );
                        $output_assoc = json_decode($output, true);
                        if ($output_assoc){
                            $ret_assoc = $output_assoc;
                            if($output_assoc["status"]==="success"){
                                $project->set_state(Project::$STATE_ACTIVE);
                            }
                        }
                    }
                }else if($project->state===Project::$STATE_ACTIVE){
                    $ret_assoc = ["status"=>"failed", "message"=>"Project is already running!"];
                }else{
                    $ret_assoc = ["status"=>"failed", "message"=>"Cannot resume project with state `".$project->state."`!"];
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function progress_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $projectname_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($projectname_chk){
            $projectname = $arrQueryStringParams["name"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            // python script -> get active progress
            $project = $project_user->get_project($projectname);
            if ($project===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
            }else{
                $message_path = $project_user->username."/".$projectname;
                $output = execute_script("progress", $message_path,0,0,0,0,0,0,0,0,0,0,null,null);
                $output_assoc = json_decode($output, true);
                $ret_assoc = ["status"=>"failed", "message"=>"No response!"];
                if ($output_assoc && $output_assoc["status"]==="success"){
                    $progress = intval($output_assoc["data"]);
                    if ($progress>$project->progress){
                        $project->set_progress($progress);
                    }
                }
                $ret_assoc = ["status"=>"success", "data"=>["progress"=>$project->progress, "total"=>$project->total]];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function list_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $category_chk = isset($arrQueryStringParams["category"]) &&  $arrQueryStringParams["category"];
        $users_chk = isset($arrQueryStringParams["users"]) &&  $arrQueryStringParams["users"];
        $start_chk = isset($arrQueryStringParams["start"]) && $arrQueryStringParams["start"];
        $limit_chk = isset($arrQueryStringParams["limit"]) && $arrQueryStringParams["limit"];

        $category = "ALL";
        if($category_chk){
            $category = strtoupper($arrQueryStringParams["category"]);
        }
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
            $fetch_all_users = $users_chk;
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            if(!in_array($category, Project::get_states()) && $category!="ALL"){
                $ret_assoc = ["status"=>"failed", "message"=>sprintf("Unknown category `%s`", $category)];
            }else{
                $projects=null;
                $data = [];
                if (!$fetch_all_users){
                    $projects = $this->user->get_projects($category, $limit, $start);
                }else{
                    $projects = Project::get_projects(null, $category, $limit, $start);
                }
                if($projects!==null){
                    foreach($projects as $project){
                        array_push($data, $project->as_dict());
                    }
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function downloads_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $projectname_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($projectname_chk){
            $name = $arrQueryStringParams["name"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $message_path = $project_user->username."/".$name;
            $output = execute_script("downloads", $message_path,0,0,0,0,0,0,0,0,0,0,null,null);
            $output_assoc = json_decode($output, true);
            $ret_assoc = ["status"=>"failed", "message"=>"No response!"];
            if ($output_assoc){
                $ret_assoc = $output_assoc;
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function download_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $filename_chk = isset($arrQueryStringParams["file"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($filename_chk){
            $file = $arrQueryStringParams["file"];
            $filename_chk=str_ends_with($file, ".zip") && is_numeric($file[0]);
        }
        if($name_chk && $filename_chk){
            $name = $arrQueryStringParams["name"];
            $file = $arrQueryStringParams["file"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $folder_path = $project_user->username."/".$name;
            $file_path = $folder_path."/".$file;
            $output = execute_script("download_link", $file_path,0,0,0,0,0,0,0,0,0,0,null,null);
            $output_assoc = json_decode($output, true);
            if ($output_assoc && $output_assoc["status"]==="success"){
                $download_path = $output_assoc["data"];
                header('Content-type: application/zip');
                header('Content-disposition: attachment; filename="'.$file.'"');
                readfile($download_path);
            }else{
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
            }
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function bill_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($name_chk){
            $name = $arrQueryStringParams["name"];
            $project = $project_user->get_project($name);
            $ret_assoc = ["status"=>"failed", "message"=>"An error occurred on our end!"];
            if($project!==null){
                if($project->state===Project::$STATE_COMPLETE){
                    $project->set_state(Project::$STATE_BILLED);
                    $ret_assoc = ["status"=>"success", "data"=>"Operation successfull!"];
                }else if($project->state===Project::$STATE_BILLED){
                    $ret_assoc = ["status"=>"failed", "message"=>"Project already billed"];
                }else{
                    $ret_assoc = ["status"=>"failed", "message"=>"Project must be complete to be billed!"];
                }
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
            }else{
                $ret_assoc = ["status"=>"failed", "message"=>"Project does not exist!"];
            }
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function delete_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }
        
        if($name_chk){
            $name = $arrQueryStringParams["name"];
            $project = $project_user->get_project($name);
            $ret_assoc = ["status"=>"failed", "message"=>"An error occurred on our end!"];
            if($project!==null){
                if($project->state===Project::$STATE_BILLED || $project->state===Project::$STATE_CANCELLED){
                    // execute script
                    $folder_path = $project_user->username."/".$name;
                    $output = execute_script("delete", $folder_path,0,0,0,0,0,0,0,0,0,0,null,null);
                    $output_assoc = json_decode($output, true);
                    if($output_assoc && $output_assoc["status"]==="success"){
                        Project::delete_by_id($project->id);
                        $ret_assoc = ["status"=>"success", "data"=>"Operation successfull!"];
                    }else if($output_assoc){
                        $ret_assoc = $output_assoc;
                    }
                }else{
                    $ret_assoc = ["status"=>"failed", "message"=>"Project must be complete to be billed!"];
                }
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
            }else{
                $ret_assoc = ["status"=>"failed", "message"=>"Project does not exist!"];
            }
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function search_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $text_chk = isset($arrQueryStringParams["text"]) &&  $arrQueryStringParams["text"];
        $category_chk = isset($arrQueryStringParams["category"]) &&  $arrQueryStringParams["category"];
        $users_chk = isset($arrQueryStringParams["users"]) &&  $arrQueryStringParams["users"];
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

        $category=null;
        if($category_chk){
            $category = strtoupper($arrQueryStringParams["category"]);
            if($category!=="ALL" && !in_array($category, Project::get_states())){
                $ret_assoc = ["status"=>"failed", "message"=>sprintf("Unkown category `%s`", $category)];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($text_chk){
            $text = $arrQueryStringParams["text"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $projects=null;
            if($users_chk){
                $projects = Project::find_projects_like($text, $category, null, $limit, $start);
            }else{
                $projects = $this->user->find_projects_like($text, $category, $limit, $start);
            }
            if($projects!==null){
                $data = [];
                foreach($projects as $project){
                    array_push($data, $project->as_dict());
                }
                $ret_assoc = ["status"=>"success", "data"=>$data];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }  

    public function get_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if($name_chk){
            $name = $arrQueryStringParams["name"];
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $project = $project_user->get_project($name);
            if($project!==null){
                if ($project->state!==Project::$STATE_BILLED){
                    $message_path = $project_user->username."/".$name;
                    $output = execute_script("state", $message_path,0,0,0,0,0,0,0,0,0,0,null,null);
                    $output_assoc = json_decode($output, true);
                    if($output_assoc && $output_assoc["status"]==="success"){
                        $state = $output_assoc["data"];
                        if(in_array($state, Project::get_states())){
                            $project->set_state($state);
                        } 
                    }
                }
                $ret_assoc = ["status"=>"success", "data"=>$project->as_dict()];
            }else{
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }  

    public function count_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $category_chk = isset($arrQueryStringParams["category"]) && $arrQueryStringParams["category"];
        $category="ALL";
        if($category_chk){
            $category = $arrQueryStringParams["category"];
        }
        if(true){
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $count = $this->user->count_projects($category);
            if ($count!==null){
                $ret_assoc = ["status"=>"success", "data"=>$count];
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function pause_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if ($name_chk){
            $name = $arrQueryStringParams["name"];
            $project = $project_user->get_project($name);
            $ret_assoc = ["status"=>"failed", "message"=>"No response!"];
            if ($project===null){
                $ret_assoc = ["status"=>"failed", "message"=>"Project does not exist!"];
            }else{
                $project_state = $project->state;
                if ($project_state===Project::$STATE_ACTIVE){
                    $message_path = $project_user->username."/".$name;
                    $output = execute_script("pause", $message_path,0,0,0,0,0,0,0,0,0,0,null,null);
                    $output_assoc = json_decode($output, true);
                    if($output_assoc && $output_assoc["status"]==="success"){
                        $count = intval($output_assoc["data"]);
                        $project->set_state(Project::$STATE_PAUSED);
                        $project->set_progress($count);
                        $ret_assoc = ["status"=>"success", "data"=>"Project paused."];
                    }
                }else if($project_state===Project::$STATE_PAUSED){
                    $ret_assoc = ["status"=>"success", "data"=>"Project already paused!"];
                }else{
                    $ret_assoc = ["status"=>"success", "data"=>"Project cannot be paused!"];
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function cancel_action(){
        $arrQueryStringParams = BaseController::get_query_string_params();
        $name_chk = isset($arrQueryStringParams["name"]) &&  $arrQueryStringParams["name"];
        $username_chk = isset($arrQueryStringParams["username"]) && $arrQueryStringParams["username"];

        $project_user =  $this->user; // virtual-user accessing the project
        if($username_chk){
            $username = $arrQueryStringParams["username"];
            $project_user = User::fromUsername($username);
            if ($project_user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such project!"];
                $responseData = json_encode($ret_assoc);
                BaseController::send_json($responseData);
                return;
            }
        }

        if ($name_chk){
            $name = $arrQueryStringParams["name"];
            $project = $project_user->get_project($name);
            $ret_assoc = ["status"=>"failed", "message"=>"An error occured!"];
            if ($project===null){
                $ret_assoc = ["status"=>"failed", "message"=>"Project does not exist!"];
            }else{
                $project_state = $project->state;
                $message_path = $project_user->username."/".$name;

                if ($project_state===Project::$STATE_ACTIVE){
                    $output = execute_script("cancel", $message_path,0,0,0,0,0,0,0,0,0,0,null,null);
                    $output_assoc = json_decode($output, true);
                    $ret_assoc = ["status"=>"failed", "message"=>"No response!"];
                    if ($output_assoc && $output_assoc["status"]==="success"){
                        $ret_assoc = $output_assoc;
                        $project->set_state(Project::$STATE_CANCELLED);
                    }
                }else if($project_state===Project::$STATE_PAUSED){
                    $output = execute_script("force_cancel", $message_path,0,0,0,0,0,0,0,0,0,0,null,null);
                    $output_assoc = json_decode($output, true);
                    if ($output_assoc){
                        $ret_assoc = $output_assoc;
                        if ($output_assoc["status"]==="success"){
                            $project->set_state(Project::$STATE_CANCELLED);
                        }
                    }
                }else if($project_state===Project::$STATE_CANCELLED){
                    $ret_assoc = ["status"=>"success", "data"=>"Project already cancelled!"];
                }else{
                    $ret_assoc = ["status"=>"success", "data"=>"Project cannot be cancelled!"];
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function preview_image_action(){
        $start_value_chk = isset($_POST["start_value"]);
        $total_chk = isset($_POST["total"]) &&  $_POST["total"];
        $qr_serial_length_chk = isset($_POST["qr_serial_length"]);
        $pre_string_chk = isset($_POST["pre_string"]);
        $pro_string_chk = isset($_POST["pro_string"]);
        $config_name_chk = isset($_POST["config_name"]);

        if($start_value_chk && $total_chk && $qr_serial_length_chk && $pre_string_chk && $pro_string_chk && $config_name_chk){
            $start_value = intval($_POST["start_value"]);
            $total = intval($_POST["total"]);
            $qr_serial_length = intval($_POST["qr_serial_length"]);
            $pre_string = $_POST["pre_string"] ? $_POST["pre_string"] : "";
            $pro_string = $_POST["pro_string"] ? $_POST["pro_string"] : "";
            $config_name = $_POST["config_name"];

            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            if ($this->user===null){
                $ret_assoc = ["status"=>"failed", "message"=>"No such user!"];
            }else{
                $config = Configuration::fromName($config_name);
                if ($config===null){
                    $ret_assoc = ["status"=>"failed", "message"=>"Configuration `$config_name` does not exist!"];
                }else{
                    $strings = Project::get_text_samples($start_value,$total,$qr_serial_length,$pre_string,$pro_string);
                    $output = execute_script("preview",$strings[0],1,1,1,1,"","",1,$config->error_correction,$config->box_size,$config->border,$config->fgcolor,$config->bgcolor);
                    $ret_assoc = json_decode($output, true);
                }
            }
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

    public function preview_text_action(){
        $start_value_chk = isset($_POST["start_value"]);
        $total_chk = isset($_POST["total"]) &&  $_POST["total"];
        $qr_serial_length_chk = isset($_POST["qr_serial_length"]);
        $pre_string_chk = isset($_POST["pre_string"]);
        $pro_string_chk = isset($_POST["pro_string"]);

        if($start_value_chk && $total_chk && $qr_serial_length_chk && $pre_string_chk && $pro_string_chk){
            $start_value = intval($_POST["start_value"]);
            $total = intval($_POST["total"]);
            $qr_serial_length = intval($_POST["qr_serial_length"]);
            $pre_string = $_POST["pre_string"] ? $_POST["pre_string"] : "";
            $pro_string = $_POST["pro_string"] ? $_POST["pro_string"] : "";

            $ret_assoc = ["status"=>"failed", "message"=>"An error occured on our end!"];
            $strings = Project::get_text_samples($start_value,$total,$qr_serial_length,$pre_string,$pro_string);
            $ret_assoc = ["status"=>"success", "data"=>["start"=>$strings[0],"end"=>$strings[1] ]];
            $responseData = json_encode($ret_assoc);
            BaseController::send_json($responseData);
        }else{
            BaseController::send_internal_error("Uprocessable entity");
        }
    }

}
?>