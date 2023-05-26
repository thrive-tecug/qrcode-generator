<?php
require_once PROJECT_ROOT_PATH."includes/Database.php";
require_once PROJECT_ROOT_PATH."model/Configuration.php";
require_once PROJECT_ROOT_PATH."model/UserPermission.php";
require_once PROJECT_ROOT_PATH."model/UserPermissionListing.php";

class User{
    /*
    */
    private static $table_name = "users";
    private static $default_approve_state=false;
    private static $super_admin_attributes = [
        "name"=>"SUPER ADMIN",
        "username"=>"superadmin",
        "email"=>"super@admin.com",
        "password"=>"superadmin@i23",
        "created_by"=>null,
        "approved"=>true
    ];

    public $id=null;
    public $name=null;
    public $username=null;
    public $email=null;
    public $password=null;
    public $created_by=null;
    public $approved=null;

    public function __construct(){
        User::init();
    }

    private static function __create_table(){
        $query = "CREATE table ".self::$table_name." (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(225),
            username VARCHAR(225) UNIQUE,
            email VARCHAR(225),
            password VARCHAR(255),
            created_by INT,
            approved INT
        )";
        Database::execute($query);
    }

    public static function init(){
        Project::init();
        Configuration::init();
        UserPermission::init();
        UserPermissionListing::init();

        if(!Database::check_table_exists(self::$table_name)){
            self::__create_table();
            // add super user
            $permission_codes = UserPermission::get_permission_codes();
            $permission_ids = UserPermission::get_ids_from_codes($permission_codes);
            $super_admin = self::$super_admin_attributes;
            self::__add_user(
                $super_admin["name"], $super_admin["username"], $super_admin["email"],
                $super_admin["password"], $super_admin["created_by"], $super_admin["approved"],
                $permission_ids
            );
        }
    }

    public static function get_table_name(){
        return self::$table_name;
    }
    
    public static function fromUsername($username){
        self::init();
        $rows = Database::fetch_rows_by_condition(self::$table_name, ['username'=>[$username,'s']], 1, 0, "id", true);
        if ($rows!==null && count($rows)>0){
            $instance=new User();
            $instance->fill_from_data($rows[0]);
            return $instance;
        }
        return null;
    }

    public static function fromEmail($email){
        self::init();
        $rows = Database::fetch_rows_by_condition(self::$table_name, ['email'=>[$email,'s']], 1, 0, "id", true);
        if ($rows!==null && count($rows)>0){
            $instance=new User();
            $instance->fill_from_data($rows[0]);
            return $instance;
        }
        return null;
    }

    public static function fromId($id){
        self::init();
        $rows = Database::fetch_rows_by_condition(self::$table_name, ['id'=>[$id,'i']], 1, 0, "id", true);
        if ($rows!==null && count($rows)>0){
            $instance=new User();
            $instance->fill_from_data($rows[0]);
            return $instance;
        }
        return null;
    }

    public static function fromData($data){
        $user = new User();
        $user->fill_from_data($data);
        return $user;
    }

    public function as_dict(){
        return [
            "id"=>$this->id, "name"=>$this->name, "username"=>$this->username,"email"=>$this->email,
            "password"=>$this->password, "created_by"=>$this->created_by,"approved"=>$this->approved
        ];
    }

    private function fill_from_data($data){
        $this->id=$data["id"];
        $this->name=$data["name"];
        $this->username=$data['username'];
        $this->email=$data["email"];
        $this->password=$data["password"];
        $this->created_by=$data['created_by'];
        $this->approved=$data['approved'];
    }

    public static function fetch_rows_by_condition($condition_assoc){
        $rows = Database::fetch_rows_by_condition(self::$table_name, $condition_assoc);
        if ($rows!==null && count($rows)>0){
            $users = [];
            foreach($rows as $data){
                array_push($users, self::fromData($data));
            }
            return $users;
        }
        return [];
    }

    public static function update_rows_by_condition($update_assoc, $where_assoc){
        Database::update_rows_by_condition(self::$table_name, $update_assoc, $where_assoc);
    }

    public static function insert_user($name, $username, $email, $password, $created_by=null, $approved=false){
        // atomic insert user
        $query = sprintf("INSERT INTO %s (name,username,email,password,created_by,approved) VALUES(?,?,?,?,?,?)", self::$table_name);
        Database::execute($query, [$name,$username,$email,$password,$created_by,$approved], "sssssi", false);
    }

    public static function __add_user($name,$username,$email,$password,$created_by=null,$approved=false,$permission_ids=null){
        self::insert_user($name,$username,$email,$password,$created_by,$approved);
        $rows = Database::fetch_rows_by_condition(self::$table_name, ["username"=>[$username, 's']]);
        $user_data = $rows[0];
        $_id = $user_data["id"];
        // add config
        Configuration::insert_default_config($_id);
        if ($permission_ids===null){
            $permission_ids=UserPermission::get_default_user_permission_ids();
        }
        foreach($permission_ids as $permission_id){
            UserPermissionListing::insert_listing($_id, $permission_id, $granted_by=$created_by);
        }
        return $_id;
    }


    public function add_user($name,$username,$email,$password,$created_by=null,$approved=false,$permission_ids=null){
        /* adds user and sets up dependencies -> returns id */
        // insert
        if ($created_by==null){
            $created_by=$this->id;
        }
        $_id = self::__add_user($name,$username,$email,$password,$created_by,$approved,$permission_ids);
        return $_id;
    }

    public function edit($name,$username,$email,$password){
        $update_assoc = ["name"=>[$name, 's'], "username"=>[$username, 's'], "email"=>[$email, 's'], "password"=>[$password, "s"]];
        $where_assoc = ["id"=>[$this->id, 'i']];
        self::update_rows_by_condition($update_assoc, $where_assoc);
    }

    public function grant_permissions($user_id, $permission_ids){
        if ($user_id===$this->id){
            return false;
        }
        $self_permissions = $this->get_permissions();
        $has_right = false;
        $grant_id = UserPermission::get_id_from_code("GRANT_U_PERMISSIONS");
        foreach($self_permissions as $permission){
            if($permission->permission_id===$grant_id){
                $has_right=true;
                break;
            }
        }
        if($has_right){
            UserPermissionListing::delete_user_permissions($user_id);
            foreach($permission_ids as $perm_id){
                $granted = UserPermissionListing::insert_listing($user_id,$perm_id,$this->id,false);
            }
            return true;
        }
        return false;
    }
        
    public function get_project($name){
        // gets the project with this name that belongs to the user
        $projects = Project::fetch_projects_by_condition_with_config_name(["name"=>[$name,"s"], "created_by"=>[$this->id,"i"]]);
        if ($projects!==null && count($projects)>0){
            return $projects[0];
        }
        return null;
    }

    public function add_new_project($name,$description,$start_value,$total,$pre_string,$pro_string,$csv_serial_length,$qr_serial_length,
            $configuration_id,$progress=0,$created_on=null,$state="ACTIVE"){
        Project::insert($name,$description,$start_value,$total,$pre_string,$pro_string,$csv_serial_length,$qr_serial_length,$this->id,
            $progress,$created_on,$state,$configuration_id);
    }

    public function get_configuration_by_name($name){
        return Configuration::get_configuration_by_id_and_name($this->id, $name);
    }

    public function get_projects($category=null){
        $condition_assoc = ["created_by"=>[$this->id, 'i']];
        if ($category!==null){
            if ($category==='ALL'){
                ;
            }else if ($category==="INACTIVE"){
                $condition_assoc["state"] = ["CANCELLED", 's'];
            }else{
                $condition_assoc["state"] = [$category, 's'];
            }
        }

        $projects = Project::fetch_projects_by_condition_with_username($condition_assoc);
        return $projects;
    }
        
    public function get_user($username){
        $self_perm_listings = UserPermissionListing::get_listings_for_id($this->id);
        $self_perm_ids = [];
        foreach($self_perm_listings as $listing){
            array_push($self_perm_ids, $listing->permission_id);
        }
        $self_perm_codes = UserPermission::get_codes_from_ids($self_perm_ids);
        if(!in_array("VIEW_USERS", $self_perm_codes) && $username!=$this->username){
            return null;
        }
        $users = User::fetch_rows_by_condition(["username"=>[$username, 's']]);
        if ($users!==null && count($users)>0){
            return $users[0];
        }
        return null;
    }

    public function get_users(){
        // returns list of all users except self
        $users = User::fetch_rows_by_condition(["username"=>[$this->username, 's', null, "!="]]);
        if ($users===null){
            return [];
        }
        return $users;
    }
    
    public function find_users_like_name($name){
        $rows = Database::fetch_rows_like(self::$table_name, "name", $name, []);
        if ($rows!==null){
            $users = [];
            foreach($rows as $data){
                array_push($users, self::fromData($data));
            }
            return $users;
        }
        return null;
    }
    
    public function find_users_like_username($username){
        $rows = Database::fetch_rows_like(self::$table_name, "username", $username, []);
        if ($rows!==null){
            $users = [];
            foreach($rows as $data){
                array_push($users, self::fromData($data));
            }
            return $users;
        }
        return null;
    }

    public function find_projects_like($pattern){
        return Project::find_projects_like($pattern, $this->id); // depending on permission
    }

    public function find_configurations_like($pattern){
        return Configuration::find_configurations_like($pattern, $this->id);
    }
    
    public function add_configuration($name,$folder_batch,$version,$error_correction,$box_size,$border,$fgcolor,$bgcolor){
        Configuration::insert($name,$this->id,$folder_batch,$version,$error_correction,$box_size,$border,$fgcolor,$bgcolor);
    }
    
    public function get_configurations(){
        return Configuration::get_configurations_by_id($this->id);
    }

    public function count_users(){
        $count = Database::count_rows_by_condition(self::$table_name, []);
        if ($count!==null){
            return max([0, $count-1]);
        }
        return null;
    }

    public function count_all_projects($category="ALL"){
        return Project::count_projects(null, $category);
    }

    public function count_projects($category="ALL"){
        // counts projects for this user_id that have state=category
        return Project::count_projects($this->id, $category);
    }
    
    public function get_permissions(){
        $listings = UserPermissionListing::get_listings_for_id($this->id);
        return $listings;
    }
    
    public function get_permissions_for_user($user_id){
        return UserPermissionListing::get_listings_for_id($user_id);
    }

    public function update_approved_status($approved){
        // updates *this user's approved status
        $update_assoc = ["approved"=>[$approved, 'i']];
        $where_assoc = ["id"=>[$this->id, 'i']];
        self::update_rows_by_condition($update_assoc, $where_assoc);
    }

}

?>