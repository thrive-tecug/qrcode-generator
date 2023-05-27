<?php
require_once PROJECT_ROOT_PATH."includes/Database.php";

class Project{
    /*
    
    */
    private static $table_name="projects";
    private static $max_string_length=1000;
    public static $STATE_ACTIVE="ACTIVE";
    public static $STATE_BILLED="BILLED";
    public static $STATE_PAUSED="PAUSED";
    public static $STATE_CANCELLED="CANCELLED";
    public static $STATE_COMPLETE="COMPLETE";

    public $id=null;
    public $name=null;
    public $description=null;
    public $start_value=null;
    public $progress=0;
    public $total=null;
    public $pre_string=null;
    public $pro_string=null;
    public $csv_serial_length=null;
    public $qr_serial_length=null;
    public $created_on=null;
    public $created_by=null;
    public $state=null;
    public $configuration_id=null;
    public $creator=null;
    public $config_name=null;

    public function __construct(){
        self::init();
    }

    private static function __create_table(){
        $query = sprintf("CREATE TABLE %s (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(225),
            description VARCHAR(225),
            start_value INT,
            total INT,
            pre_string VARCHAR(%d),
            pro_string VARCHAR(%d),
            csv_serial_length INT,
            qr_serial_length INT,
            created_by INT,
            created_on VARCHAR(40),
            progress INT,
            state VARCHAR(10),
            configuration_id INT
        )", self::$table_name, self::$max_string_length, self::$max_string_length);
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
            $instance = new Project();
            $instance->fill_from_data($configs[0]);
            return $instance;
        }
        return null;
    }

    public static function fromName($name){
        self::init();
        $configs = Database::fetch_rows_by_condition(self::$table_name, ['name'=>[$name,'s']], 1, 0, "id", true);
        if ($configs!==null && count($configs)>0){
            $instance = new Project();
            $instance->fill_from_data($configs[0]);
            return $instance;
        }
        return null;
    }

    public static function fromData($data){
        $instance = new Project();
        $instance->fill_from_data($data);
        return $instance;
    }

    private function fill_from_data($data){
        $this->id=$data["id"];
        $this->name=$data["name"];
        $this->description=$data["description"];
        $this->start_value=$data["start_value"];
        $this->progress=$data["progress"];
        $this->total=$data["total"];
        $this->pre_string=$data["pre_string"];
        $this->pro_string=$data["pro_string"];
        $this->csv_serial_length=$data["csv_serial_length"];
        $this->qr_serial_length=$data["qr_serial_length"];
        $this->created_on=$data["created_on"];
        $this->created_by=$data["created_by"];
        $this->state=$data["state"];
        $this->configuration_id=$data["configuration_id"];
        if(isset($data["creator"])){
            $this->creator=$data["creator"];
        }
        if(isset($data["config_name"])){
            $this->config_name=$data["config_name"];
        }
    }

    public function as_dict(){
        return [
            "id"=>$this->id,"name"=>$this->name, "description"=>$this->description, "start_value"=>$this->start_value,
            "progress"=>$this->progress,"total"=>$this->total,"pre_string"=>$this->pre_string,"pro_string"=>$this->pro_string,
            "csv_serial_length"=>$this->csv_serial_length,"qr_serial_length"=>$this->qr_serial_length,"creator"=>$this->creator,"config_name"=>$this->config_name,
            "created_on"=>$this->created_on,"created_by"=>$this->created_by,"state"=>$this->state,"configuration_id"=>$this->configuration_id
        ];
    }

    public static function insert($name,$description,$start_value,$total,$pre_string,$pro_string,$csv_serial_length,$qr_serial_length,$created_by,
            $progress=0,$created_on=null,$state="ACTIVE", $configuration_id=null){
        $query = "INSERT INTO ".self::$table_name."(name,description,start_value,total,pre_string,pro_string,csv_serial_length,
            qr_serial_length,created_by,progress,created_on,state,configuration_id)
        VALUES(?,?,?,?,?,?,?, ?,?,?,?,?,?)";
        $typestring = "sssssssssssss";
        if ($created_on===null){
            $created_on=Database::get_time_string();
        }
        Database::execute($query, [$name,$description,$start_value,$total,$pre_string,$pro_string,$csv_serial_length,
        $qr_serial_length,$created_by,$progress,$created_on,$state,$configuration_id], $typestring, false);
    }
    
    public static function fetch_instances_by_condition($condition_assoc, $limit=null, $offset=0){
        $projects = Database::fetch_rows_by_condition(self::$table_name,$condition_assoc,$limit, $offset, "id", false);
        if ($projects!==null){
            $res = [];
            foreach($projects as $data){
                array_push($res, self::fromData($data));
            }
            return $res;
        }
        return null;
    }

    public static function fetch_joinable_projects_by_condition($join_table, $condition_assoc, $join_pair, $join_cols, $limit=null, $offset=0, $order_by="id", $ascending=false){
        // returns projects after joining 
        $rows = Database::fetch_joinable_rows_by_condition(self::$table_name,$join_table, $condition_assoc,$join_pair,$join_cols, $limit, $offset, $order_by, $ascending);
        if ($rows!==null){
            $res = [];
            foreach($rows as $data){
                array_push($res, self::fromData($data));
            }
            return $res;
        }
        return null;
    }

    public static function fetch_instances_by_condition_with_username($condition_assoc, $limit=null, $offset=0){
        // returns projects including their creator usernames
        return self::fetch_joinable_projects_by_condition(User::get_table_name(), $condition_assoc, ['created_by', 'id'], ['username'=>'creator'], $limit, $offset);
    }

    public static function fetch_instances_by_condition_with_config_name($condition_assoc, $limit=null, $offset=0){
        return self::fetch_joinable_projects_by_condition(Configuration::get_table_name(), $condition_assoc, ['configuration_id', 'id'], ['name'=>'config_name'], $limit, $offset);
    }
    

    public static function update_rows_by_condition($update_assoc, $where_assoc){
        Database::update_rows_by_condition(self::$table_name, $update_assoc, $where_assoc);
    }

    public function set_state($state){
        // persists state to database
        $update_assoc = ["state"=>[$state, 's']];
        $where_assoc = ["id"=>[$this->id, 'i']];
        self::update_rows_by_condition($update_assoc, $where_assoc);
        $this->state = $state;
    }

    public function set_progress($progress){
        // persists progress to database
        $update_assoc = ["progress"=>[$progress, 'i']];
        $where_assoc = ["id"=>[$this->id, 'i']];
        self::update_rows_by_condition($update_assoc, $where_assoc);
        $this->progress = $progress;
    }

    public static function get_states(){
        return [self::$STATE_ACTIVE, self::$STATE_BILLED, self::$STATE_CANCELLED, self::$STATE_COMPLETE, self::$STATE_PAUSED];
    }
    
    public static function count_projects($user_id=null, $category="ALL"){
        $condition_assoc = [];
        if (in_array($category, self::get_states())){
            $condition_assoc["state"] = [$category, "s"];
        }else if($category!=="ALL"){
            return null;
        }
        if ($user_id!==null){
            $condition_assoc["created_by"]=[$user_id, "i"];
        }
        $count = Database::count_rows_by_condition(self::$table_name, $condition_assoc);
        if ($count!==null){
            return $count;
        }
        return null;
    }

    public static function get_projects($user_id=null, $category="ALL", $limit=null, $start=0){
        $condition_assoc = [];
        if (in_array($category, self::get_states())){
            $condition_assoc["state"] = [$category, "s"];
        }else if($category!=="ALL"){
            return null;
        }else if ($category==="INACTIVE"){
            $condition_assoc["state"] = ["CANCELLED", 's'];
        }
        if ($user_id!==null){
            $condition_assoc["created_by"]=[$user_id, "i"];
        }
        $projects = self::fetch_instances_by_condition_with_username($condition_assoc, $limit, $start);
        return $projects;
    }

    public static function find_projects_like($pattern, $category=null, $user_id=null, $limit=null, $start=0){
        $condition_assoc = [];
        if($user_id!==null){
            $condition_assoc["created_by"] = [$user_id, 'i'];
        }
        if($category!==null && $category!=="ALL"){
            $condition_assoc["state"] = [$category, 's'];
        }
        $rows = Database::fetch_joinable_rows_like(self::$table_name, User::get_table_name(), $pattern, "name", $condition_assoc, ['created_by', 'id'], ['username'=>'creator'], $limit, $start);
        if ($rows!==null){
            $projects = [];
            foreach($rows as $data){
                array_push($projects, self::fromData($data));
            }
            return $projects;
        }
        return null;
    }

    public static function get_text_samples($start_value,$total,$qr_serial_length,$pre_string,$pro_string){
        // returns sample strings depending on stats
        $first = $start_value; 
        $last = $start_value+$total-1;
        $first_serial =  strval($first); 
        $last_serial  = strval($last);
        if ($qr_serial_length>0){
            $first_serial = str_pad($first_serial, $qr_serial_length, '0', STR_PAD_LEFT);
            $last_serial = str_pad($last_serial, $qr_serial_length, '0', STR_PAD_LEFT);
        }
        $first_string = $pre_string.$first_serial.$pro_string;
        $last_string = $pre_string.$last_serial.$pro_string;
        return [$first_string, $last_string];
    }

    public static function delete_by_name($name){
        Database::delete_rows_by_condition(self::$table_name, ["name"=>[$name, 's']]);
    }

    public static function delete_by_id($id){
        Database::delete_rows_by_condition(self::$table_name, ["id"=>[$id, 'i']]);
    }
}
?>