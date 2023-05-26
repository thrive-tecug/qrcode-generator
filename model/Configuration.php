<?php
require_once PROJECT_ROOT_PATH."includes/Database.php";

class Configuration{
    private static $table_name = "configurations";
    private static $default_config_name = "default_config";
    public static $error_correction_levels = ["ERROR_CORRECT_M", "ERROR_CORRECT_L", "ERROR_CORRECT_H", "ERROR_CORRECT_Q"];
    public static $versions = [1];

    public $id=null;
    public $name=null;
    public $user_id=null;
    public $folder_batch=null;
    public $version=null;
    public $error_correction=null;
    public $box_size=null;
    public $border=null;
    public $fgcolor=null;
    public $bgcolor=null;

    public function __construct(){
        self::init();
    }

    private static function __create_table(){
        $query = "CREATE TABLE ".self::$table_name."(
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(225),
            user_id INT,
            folder_batch INT,
            version INT,
            error_correction VARCHAR(40),
            box_size INT,
            border INT,
            fgcolor VARCHAR(10),
            bgcolor VARCHAR(10)
        )";
        Database::execute($query, [], "", false);
    }

    public static function init(){
        if(!Database::check_table_exists(self::$table_name)){
            self::__create_table();
        }
    }

    public static function get_table_name(){
        return self::$table_name;
    }

    public static function fromId($id){
        self::init();
        $configs = Database::fetch_rows_by_condition(self::$table_name, ['id'=>[$id,'i']], 1, 0, "id", true);
        if ($configs!==null && count($configs)>0){
            $instance = new Configuration();
            $instance->fill_from_data($configs[0]);
            return $instance;
        }
        return null;
    }

    public static function fromName($name){
        self::init();
        $configs = Database::fetch_rows_by_condition(self::$table_name, ['name'=>[$name,'s']], 1, 0, "id", true);
        if ($configs!==null && count($configs)>0){
            $instance = new Configuration();
            $instance->fill_from_data($configs[0]);
            return $instance;
        }
        return null;
    }

    public static function fromData($data){
        $config = new Configuration();
        $config->fill_from_data($data);
        return $config;
    }

    private function fill_from_data($data){
        $this->id=$data["id"];
        $this->name=$data["name"];
        $this->user_id=$data["user_id"];
        $this->folder_batch=$data["folder_batch"];
        $this->version=$data["version"];
        $this->error_correction=$data["error_correction"];
        $this->box_size=$data["box_size"];
        $this->border=$data["border"];
        $this->fgcolor=$data["fgcolor"];
        $this->bgcolor=$data["bgcolor"];
    }

    public function as_dict(){
        return [
            "id"=>$this->id,"name"=>$this->name,"user_id"=>$this->user_id,"folder_batch"=>$this->folder_batch,"version"=>$this->version,
            "error_correction"=>$this->error_correction,"box_size"=>$this->box_size,"border"=>$this->border,"fgcolor"=>$this->fgcolor,
            "bgcolor"=>$this->bgcolor
        ];
    }

    public static function insert($name,$user_id,$folder_batch,$version,$error_correction,$box_size,$border,$fgcolor,$bgcolor){
        $query = "INSERT INTO ".self::$table_name."(name,user_id,folder_batch,version,error_correction,box_size,border,fgcolor,bgcolor) VALUES(?,?,?,?,?,?,?,?,?)";
        $inserted = Database::execute($query, [$name,$user_id,$folder_batch,$version,$error_correction,$box_size,$border,$fgcolor,$bgcolor], "siiisiiss", false);
        return $inserted;
    }

    public static function insert_default_config($user_id){
        $config = self::get_default_config();
        self::insert(
            self::$default_config_name, $user_id, $config->folder_batch,$config->version, $config->error_correction,
            $config->box_size,$config->border,$config->fgcolor, $config->bgcolor
        );
    }

    public static function get_default_config(){
        $data = [
            "folder_batch"=>500, 
            "version"=>1,
            "error_correction"=>"ERROR_CORRECT_M",
            "box_size"=>4,
            "border"=>1,
            "fgcolor"=>"#000000",
            "bgcolor"=>"#ffffff"
        ];
        $config = new Configuration();
        $config->folder_batch=$data["folder_batch"];
        $config->versioh=$data["version"];
        $config->error_correction=$data["error_correction"];
        $config->box_size=$data["box_size"];
        $config->border=$data["border"];
        $config->fgcolor=$data["fgcolor"];
        $config->bgcolor=$data["bgcolor"];
        return $config;
    }

    public static function get_configurations_by_id($id){
        $rows = Database::fetch_rows_by_condition(self::$table_name, ["user_id"=>[$id, "i"]]);
        if ($rows!==null && count($rows)>0){
            $configs = [];
            foreach($rows as $data){
                array_push($configs, self::fromData($data));
            }
            return $configs;
        }
        return null;
    }

    public static function get_configurations_by_name($name){
        $rows = Database::fetch_rows_by_condition(self::$table_name, ["name"=>[$name, "s"]]);
        if ($rows!==null && count($rows)>0){
            $configs = [];
            foreach($rows as $data){
                array_push($configs, self::fromData($data));
            }
            return $configs;
        }
        return null;
    }

    public static function get_configuration_by_id_and_name($id, $name){
        $rows = Database::fetch_rows_by_condition(self::$table_name, ["user_id"=>[$id,'i'], "name"=>[$name, "s"]]);
        if ($rows!==null && count($rows)>0){
            return self::fromData($rows[0]);
        }
        return null;
    }

    public static function get_error_correction_levels(){
        return self::$error_correction_levels;
    }

    public static function find_configurations_like($pattern, $user_id=null){
        $condition_assoc = [];
        if ($user_id!==null){
            $condition_assoc["user_id"]=[$user_id, "i"];
        }
        $configs = Database::fetch_rows_like(self::$table_name, "name", $pattern, $condition_assoc);
        if ($configs!==null){
            $res = [];
            foreach($configs as $data){
                array_push($res, self::fromData($data));
            }
            return $res;
        }
        return null;
    }
    
}
?>