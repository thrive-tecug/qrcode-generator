<?php
require_once PROJECT_ROOT_PATH."includes/Database.php";

class UserPermission{
    /*
    
    */
    private static $table_name="user_permissions";
    private static $PERMISSION_LIST = [
        ["GRANT_U_PERMISSIONS", "GRANT USER PERMISSIONS", "Grant user permissions"],
        ["VIEW_U_PERMISSIONS", "VIEW USER PERMISSIONS", "View user permissions"],
        ["VIEW_M_PERMISSIONS", "VIEW PERSONAL PERMISSIONS", "View personal permissions"],
        ["APPROVE_USERS", "APPROVE USERS", "Approve user accounts"],
        ["CREATE_USERS","CREATE USERS", "Create user accounts"],
        ["CREATE_PROJECTS", "CREATE PROJECTS","Make projects"],
        ["VIEW_USERS", "VIEW USERS", "View Users"],
        ["VIEW_M_PROJECTS", "VIEW PERSONAL PROJECTS", "View personal projects"],
        ["VIEW_U_PROJECTS", "VIEW USER PROJECTS", "View user projects"],
        ["EDIT_M_PROFILE", "EDIT PERSONAL PROFILE", "Edit personal profile"],
        ["EDIT_U_PROFILES", "EDIT USER PROFILES", "Edit user profiles"],
        ["BILL_PROJECTS", "BILL PROJECTS", "Bill user projects"],
        ["DELETE_PROJECTS", "DELETE PROJECTS", "Delete user projects"],
        ["DELETE_USERS", "DELETE USERS","Delete User Accounts"]
    ];
    private static $last_known_permission = "DELETE_USERS";

    public function __construct(){
        self::init();
    }

    private static function __create_table(){
        $query = "CREATE TABLE ".self::$table_name."(
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(225) UNIQUE,
            name VARCHAR(225),
            description VARCHAR(255)
        )";
        Database::execute($query);
    }

    public static function init(){
        if(!Database::check_table_exists(self::$table_name)){
            self::__create_table();
            self::__insert_permissions();
        }
    }

    public static function __insert_permissions(){
        // setup after creating tables
        //  atomic : insert roles
        foreach(self::$PERMISSION_LIST as $arr){
            $code = $arr[0];
            $name = $arr[1];
            $desc = $arr[2];
            self::insert_permission($code, $name, $desc);
        }
    }

    public static function insert_permission($code, $name, $description){
        // inserts permissions
        $query = "INSERT INTO ".self::$table_name."(code,name,description) VALUES(?,?,?)";
        Database::execute($query, [$code,$name,$description], "sss");
    }

    public static function get_permissions(){
        return self::$PERMISSION_LIST;
    }

    public static function get_permission_codes(){
        $codes = [];
        foreach(self::$PERMISSION_LIST as $arr){
            array_push($codes, $arr[0]);
        }
        return $codes;
    }

    public static function get_id_from_code($code){
        for($i=0; $i<count(self::$PERMISSION_LIST); $i++){
            $arr = self::$PERMISSION_LIST[$i];
            if($arr[0]===$code){
                return $i+1;
            }
        }
        return null;
    }

    public static function get_ids_from_codes($codes){
        $ids = [];
        foreach($codes as $code){
            array_push($ids, self::get_id_from_code($code));
        }
        return $ids;
    }
    
    public static function get_code_from_id($c_id){
        for($i=0; $i<count(self::$PERMISSION_LIST); $i++){
            $arr = self::$PERMISSION_LIST[$i];
            if($i+1===$c_id){
                return $arr[0];
            }
        }
        return null;
    }
    
    public static function get_codes_from_ids($ids){
        $codes = [];
        foreach($ids as $c_id){
            array_push($codes, self::get_code_from_id($c_id));
        }
        return $codes;
    }
    
    public static function get_default_user_permission_ids(){
        $codes = ["CREATE_PROJECTS", "VIEW_M_PROJECTS", "EDIT_M_PROFILE"];
        return self::get_ids_from_codes($codes);
    }

    public static function get_permissions_as_dict(){
        $permission_objects = [];
        foreach(self::$PERMISSION_LIST as $arr){
            array_push($permission_objects, ["code"=>$arr[0], "name"=>$arr[1], "description"=>$arr[2]]);
        }
        return $permission_objects;
    }
}

?>
