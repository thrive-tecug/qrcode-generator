<?php
require_once PROJECT_ROOT_PATH."includes/Database.php";
require_once PROJECT_ROOT_PATH."model/UserPermission.php";

class UserPermissionListing{
    private static $table_name = "user_permission_listing";

    public $id=null;
    public $user_id=null;
    public $permission_id=null;
    public $granted_by=null;

    public function __construct(){
        self::init();
    }

    private static function __create_table(){
        $query = "CREATE TABLE ".self::$table_name." (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            permission_id INT,
            granted_by INT
        )";
        Database::execute($query);
    }

    public static function get_table_name(){
        return self::$table_name;
    }

    public static function init(){
        if(!Database::check_table_exists(self::$table_name)){
            $created = self::__create_table();
        }
    }

    public static function fromData($data){
        $listing = new UserPermissionListing();
        $listing->fill_from_data($data);
        return $listing;
    }
        
    public function fill_from_data($data){
        $this->id=$data['id'];
        $this->user_id=$data['user_id'];
        $this->permission_id=$data['permission_id'];
        $this->granted_by=$data['granted_by'];
    }

    public function as_assoc(){
        return [];
    }

    public static function insert_listing($user_id, $permission_id, $granted_by=null, $check_exists=false){
        if ($check_exists){
            $rows = Database::fetch_rows_by_condition(self::$table_name, ["user_id"=>[$user_id, 'i'], "permission_id"=>[$permission_id,'i']]);
            if ($rows!==null && count($rows)>0){
                return;
            }
        }
        $query = "INSERT INTO ".self::$table_name."(user_id,permission_id,granted_by) VALUES(?,?,?)";
        Database::execute($query, [$user_id,$permission_id,$granted_by], "sss", false);
    }

    public static function get_listings_for_id($user_id){
        $rows = Database::fetch_rows_by_condition(self::$table_name, ["user_id"=>[$user_id, 'i']]);
        if ($rows!==null){
            $listings = [];
            foreach($rows as $data){
                array_push($listings, self::fromData($data));
            }
            return $listings;
        }
        return null;
    }
    
    public static function delete_user_permissions($user_id){
        return Database::delete_rows_by_condition(self::$table_name, ["user_id"=>[$user_id, 'i']]);
    }
}

?>