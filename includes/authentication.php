<?php
class Tokenizer{
    // currently not so secure system

    protected static function get_secret_key(){
        return "secret@4";
    }

    protected static function base64url_encode($str) {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    public static function tokenize($data){
        //build the headers
        /*
        @params
        data : assoc array
            the data to include in the payload
        */
        $expire_time = 86400;
        $headers = array('alg'=>'HS256','typ'=>'JWT');
        $payload = $data;
        $payload["sub"]="none";
        $payload['exp']=(time() + $expire_time);
        $jwt = self::generate_jwt($headers, $payload);
        return $jwt;
    }

    protected static function generate_jwt($headers, $payload) {
        $secret = self::get_secret_key();
        $headers_encoded = self::base64url_encode(json_encode($headers));
        $payload_encoded = self::base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
        $signature_encoded = self::base64url_encode($signature);
        $jwt = "$headers_encoded.$payload_encoded.$signature_encoded";
        
        return $jwt;
    }

    public static function decompose_jwt($jwt){
        // returns payload in $jwt, doesn't verify
        $tokenParts = explode('.', $jwt);
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];
        $payload_json = json_decode($payload, true); // assoc array
        return $payload_json;
    }

    public static function is_jwt_valid($jwt){
        // split the jwt
        if(!$jwt || $jwt=="null"){
            return false;
        }
        $secret = self::get_secret_key();
        $tokenParts = explode('.', $jwt);
        if(count($tokenParts)!==3){
            return false;
        }
        $header = base64_decode($tokenParts[0]);
        $payload = base64_decode($tokenParts[1]);
        $signature_provided = $tokenParts[2];
    
        // check the expiration time - note this will cause an error if there is no 'exp' claim in the jwt
        $payload_json = json_decode($payload, true); // assoc array
        if(!array_key_exists("exp", $payload_json)){
            return false;
        }
        $expiration = json_decode($payload)->exp;
        $is_token_expired = ($expiration - time()) < 0;
    
        // build a signature based on the header and payload using the secret
        $base64_url_header = self::base64url_encode($header);
        $base64_url_payload = self::base64url_encode($payload);
        $signature = hash_hmac('SHA256', $base64_url_header . "." . $base64_url_payload, $secret, true);
        $base64_url_signature = self::base64url_encode($signature);
    
        // verify it matches the signature provided in the jwt
        $is_signature_valid = ($base64_url_signature === $signature_provided);
        
        if ($is_token_expired || !$is_signature_valid) {
            return false;
        } else {
            return true;
        }
    }

    public static function detokenize_from_request($bypass=false){
        // checks if token came with request and returns contents of token payload
        // @params : bypass => set up token-data to handle as if user requested
        if($bypass){
            return array("username"=>"superadmin");
        }
        $headers = apache_request_headers();
        if (isset($headers["Authorization"]) && $headers["Authorization"]){
            $in_auth_str = $headers["Authorization"];
            $auth_data = explode(" ", $in_auth_str);
            $token = $auth_data[1];
            if (!self::is_jwt_valid($token)){
                return false;
            }
            return self::decompose_jwt($token);
        }
        return false;
    }
}
?>