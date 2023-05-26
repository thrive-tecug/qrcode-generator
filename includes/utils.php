<?php

function validate_hex_color($hex_color){
    $charset = ['0','1', '2', '3', '4','5','6', '7', '8', '9','a','b', 'c', 'd', 'e','f'];
    if($hex_color[0]==='#' && strlen($hex_color)===7){
        for($i=1; $i<7; $i++){
            $c = $hex_color[$i];
            if (!in_array($c, $charset)){
                return false;
            }
        }
        return true;
    }
    return false;
}

function execute_script($type, $string,$start,$total,$qlength,$clength,$pre_string,$pro_string,$batch,$error_level,$box_size,$border,$fgcolor,$bgcolor,$progress=0){
    $script_path = PROJECT_ROOT_PATH."python\\script.py";
    $param_string=sprintf(
        '{type:%s,string:%s,start:%d,total:%d,qlength:%d,clength:%d,pre_string:%s,pro_string:%s,batch:%d,error_level:%s,box_size:%d,border:%d,fgcolor:%s,bgcolor:%s,progress:%d}',
        $type, $string,$start,$total,$qlength,$clength,$pre_string,$pro_string,$batch,$error_level,$box_size,$border,$fgcolor,$bgcolor,$progress
    );
    $command = escapeshellcmd("python \"".$script_path."\" ".$param_string);
    $output = exec($command);
    if (!$output || strlen($output)===0){
        $output = "{'status':'failed', 'message':'An error occured during execution of script!'}";
    }
    $output = str_replace("'", '"', $output);
    return $output;
}

function send_message($message, $path){
    $message_path = OUTPUT_PATH.$path."/".$message;
    file_put_contents($message_path, "");
}

function receive_message($message, $path){
    $message_path = OUTPUT_PATH.$path."/".$message;
    if (file_exists($message_path)){
        $fh = fopen($message_path, "r");
        $content = fread($fh);
        fclose($fh);
        unlink($message_path);
        return $content;
    }
    return null;
}


?>