<?php
//Your Settings can be read here: settings::read('myArray/settingName') = $settingValue;
//Your Settings can be saved here: settings::set('myArray/settingName',$settingValue,$overwrite = true/false);
class cloudflare_api{
    //public static function command($line):void{}//Run when base command is class name, $line is anything after base command (string). e.g. > [base command] [$line]
    public static function init():void{
        if(!settings::isset('api_credentials')){
            settings::set('api_credentials',base64_encode(json_encode(array())));
        }
        extensions::ensure('curl');
    }//Run at startup
    public static function setApiKey(string $key){
        self::setCredentialValue('api_key',$key);
    }
    public static function setEmail(string $email){
        self::setCredentialValue('email',$email);
    }
    public static function setZoneId(string $zoneId){
        self::setCredentialValue('zone_id',$zoneId);
    }
    private static function setCredentialValue($name,$value){
        $data = json_decode(base64_decode(settings::read('api_credentials')),true);
        $data[$name] = base64_encode($value);
        settings::set('api_credentials',base64_encode(json_encode($data)),true);
    }
    private static function getCredentialValue(string $name):string{
        $data = json_decode(base64_decode(settings::read('api_credentials')),true);
        if(isset($data[$name])){
            return base64_decode($data[$name]);
        }
        return '';
    }
    public static function createSrvRecord($name,$service,$protocol,$port,$target,$priority=0,$weight=0):bool|string{
        // Cloudflare API credentials
        $url = 'https://api.cloudflare.com/client/v4/zones/' . self::getCredentialValue('zone_id') . '/dns_records';
        $headers = array(
            'X-Auth-Email: ' . self::getCredentialValue('email'),
            'X-Auth-Key: ' . self::getCredentialValue('api_key'),
            'Content-Type: application/json'
        );
        $data = array(
            'type' => 'SRV',
            'name' => $service . "." . $protocol . "." . $name,
            'data' => array(
                'priority' => $priority,
                'weight' => $weight,
                'port' => $port,
                'target' => $target,
            )
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        if(isset($result['success'])){
            if($result['success'] === true){
                return $result['result']['id'];
            }
        }
        echo "Cloudflare API Error:\n" . json_encode($result['errors'],JSON_PRETTY_PRINT) . "\n";
        
        return false;
    }
    public static function deleteRecord($recordId):bool{
        $url = 'https://api.cloudflare.com/client/v4/zones/' . self::getCredentialValue('zone_id') . '/dns_records' . '/' . $recordId;
        $headers = array(
            'X-Auth-Email: ' . self::getCredentialValue('email'),
            'X-Auth-Key: ' . self::getCredentialValue('api_key'),
            'Content-Type: application/json'
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpCode == 200 || $httpCode == 201){
            $result = json_decode($response, true);
            if(isset($result['success'])){
                if($result['success'] === true){
                    return true;
                }
            }
        }
        return false;
    }
}