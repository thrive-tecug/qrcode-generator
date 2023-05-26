<?php
// core class to govern all database access
class Database{
    private static $connection=null;

    public static function init(){
        try{
            if(self::$connection===null){
                self::$connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);
                if(mysqli_connect_errno()){
                    throw new Exception("Could not connect to database.");
                }
            }
        }catch(Exception $e){
            throw new Exception($e->getMessage());
            exit(1);
        }
    }

    public static function execute($query, $fields=[], $typestring="", $fetchable=false){
        /*
        prepares the query and executes it
        */
        try{
            $stmt = self::prepare_statement($query, $fields, $typestring);
            $result=null;
            if ($fetchable){
                $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }else{
                $result=true;
            }
            $stmt->close();
            return $result;
        }catch(Exception $e){
            throw new Exception($e->getMessage());
            exit(1);
        }
        return null;
    }

    private static function prepare_statement($query, $params=[], $typestring=""){
        /* 
            prepares queries
        */
        try{
            $prepared=(self::$connection)->prepare($query);
            if($prepared==false){
                throw new Exception("Unable to prepare statement : ".$query);
            }
            if ($params){
                $prepared->bind_param($typestring, ...$params);
            }
            $prepared->execute();
            return $prepared;
        }catch(Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public static function check_table_exists($table_name){
        $query = "SHOW TABLES LIKE '$table_name'";
        $tables = self::execute($query, [], "", true);
        if($tables!==null && count($tables)>0){
            return true;
        }
        return false;
    }

    public static function construct_where_clause($clause_data, $default_binder="and", $table_name=null){
        /*
        condition_dict :  assoc
            Signature : [col=>[val, type, {boolean_binder, op_binder}]
            Example : ['id'=>[3,'i']], 'name':['j', 's', 'or'], 'age':[99, 'i', null, '!=']}
            Resolves to : "id=3 or name='j' and age!=99"

            Default type : `s`
            Default boolean binder : `and`
            Default op_binder : `=`
        */
        $clause = "";
        $typestring="";
        $values = [];
        $i=0;
        foreach($clause_data as $col=>$args){
            $_value = $args[0];
            $_type = "s";
            $_bbinder = $default_binder;
            $_opbinder = "=";
            if (count($args)>1 && $args[1]!==null){
                $_type=$args[1];
            }
            if(count($args)>2 && $args[2]!==null){
                $_bbinder = $args[2];
            }
            if(count($args)>3 && $args[3]!==null){
                $_opbinder = $args[3];
            }
            if($i==0){
                $_bbinder="";
            }
            if($table_name!==null){
                $col=$table_name.".".$col;
            }
            $s = sprintf("%s %s%s? ", $_bbinder, $col, $_opbinder);
            $clause.=$s;
            $typestring.=$_type;
            array_push($values, $_value);
            $i++;
        }
        return [$clause, $values, $typestring];
    }

    public static function fetch_rows_by_condition($table_name, $condition_assoc, $limit=null, $offset=0, $order_by="id", $ascending=true){
        // fetches rows that match the clause
        $condition_data = self::construct_where_clause($condition_assoc);
        $clause = $condition_data[0];
        $values = $condition_data[1];
        $typestring = $condition_data[2];

        $where_clause = count($values)>0 ? "WHERE ".$clause : "";
        $order = $ascending ? "ASC" : "DESC";
        $limit_string = $limit==null ? "":"LIMIT $limit OFFSET $offset";
        $query = "SELECT * FROM $table_name ".$where_clause." ORDER BY $order_by $order ".$limit_string;
        $fetched = self::execute($query, $values, $typestring, true);
        return $fetched;
    }

    public static function fetch_joinable_rows_by_condition($main_table_name, $join_table_name, $condition_assoc, $join_pair, $join_columns=['*'], $limit=null, $offset=0, $order_by="id", $ascending=true){
        // fetches rows that match the clause
        $condition_data = self::construct_where_clause($condition_assoc, "and", $main_table_name);
        $clause = $condition_data[0];
        $values = $condition_data[1];
        $typestring = $condition_data[2];

        $where_clause = count($values)>0 ? "WHERE ".$clause : "";
        $order = $ascending ? "ASC" : "DESC";
        $limit_string = $limit==null ? "":"LIMIT $limit OFFSET $offset";
        $join_fetchables = "";
        $i=0;
        foreach($join_columns as $key=>$val){
            $join_fetchables.="$join_table_name.$key as $val";
            if($i+1<count($join_columns)){
                $join_fetchables.=",";
            }
            $i++;
        }
        $main_join_col = $join_pair[0];
        $other_join_col = $join_pair[1];
        $query = "SELECT $main_table_name.*,"."$join_fetchables FROM $main_table_name";
        $query.=" LEFT JOIN $join_table_name ON $main_table_name.$main_join_col=$join_table_name.$other_join_col ";
        $query.=$where_clause." ORDER BY $main_table_name.$order_by $order ".$limit_string;
        $fetched = self::execute($query, $values, $typestring, true);
        return $fetched;
    }

    public static function count_rows_by_condition($table_name, $condition_assoc, $count_field="id"){
        $condition_data = self::construct_where_clause($condition_assoc);
        $clause = $condition_data[0];
        $values = $condition_data[1];
        $typestring = $condition_data[2];

        $where_clause = count($values)>0 ? "WHERE ".$clause : "";
        $query = "SELECT COUNT($count_field) FROM $table_name ".$where_clause;
        $count_data = self::execute($query, $values, $typestring, true);
        if($count_data!==null && count($count_data)>0){
            $count = array_values($count_data[0])[0];
            return $count;
        }
        return null;
    }

    public static function delete_rows_by_condition($table_name, $condition_assoc){
        $condition_data = self::construct_where_clause($condition_assoc);
        $clause = $condition_data[0];
        $values = $condition_data[1];
        $typestring = $condition_data[2];

        $where_clause = count($values)>0 ? "WHERE ".$clause : "";
        $query = "DELETE FROM $table_name ".$where_clause;
        return self::execute($query, $values, $typestring, false);
    }

    public static function fetch_rows_like($table_name, $column, $pattern, $condition_assoc){
        $condition_data = self::construct_where_clause($condition_assoc);
        $clause = $condition_data[0];
        $values = $condition_data[1];
        $typestring = $condition_data[2];
        $where_clause = count($values)>0 ? $clause." AND " : "";

        $query = "SELECT * FROM $table_name WHERE ".$where_clause." $column LIKE '%$pattern%'";
        return self::execute($query, $values, $typestring, true);
    }

    public static function delete_table($table_name){
        $query = "DROP TABLE IF EXISTS `$table_name`";
        $result = self::execute($query, [], "", false);
        return $result;
    }

    public static function get_time_string(){
        // date_default_timezone_set(...);
        return date("d/m/y h:i"); // data(F j Y)
    }

    public static function clean_string($str){
        $str = preg_replace("~[\W\s]~", "_", $str);
        return $str;
    }

    public static function update_rows_by_condition($table_name, $update_clause_data, $where_clause_data){
        // updates table rows
        $update_condition_data = self::construct_where_clause($update_clause_data, ",");
        $where_condition_data = self::construct_where_clause($where_clause_data);

        $update_clause = $update_condition_data[0];
        $update_values = $update_condition_data[1];
        $update_typestring = $update_condition_data[2];
        $update_clause = count($update_values)>0 ? $update_clause : "";

        $where_clause = $where_condition_data[0];
        $where_values = $where_condition_data[1];
        $where_typestring = $where_condition_data[2];
        $where_clause = count($where_values)>0 ? $where_clause : "";

        $values = [];
        foreach($update_values as $val){
            array_push($values, $val);
        }
        foreach($where_values as $val){
            array_push($values, $val);
        }
        $query="UPDATE $table_name SET ".$update_clause." WHERE ".$where_clause;
        $updated = self::execute($query, $values, $update_typestring.$where_typestring, false);
        return $updated;
    }

    // ---------------

    public function check_row_exists($table_name, $where_clause_data){
        // ?
        self::__init__();
        $where_clause = $self::get_condition_clause($where_clause_data);
        $values = [];
        $typestring = "";
        foreach($where_clause_data as $key=>$arr){
            array_push($values, $arr[1]);
            $typestring.=$arr[2];
        }
        $query = "SELECT * FROM $table_name WHERE ".$where_clause;
        $fetched = self::execute($query, $values, $typestring, true);
        return ($fetched!==null);
    }

}
?>