<?php
namespace SolrIndexing;

class Helpers {

    /**
     * Shows usage info if correct parameters not passed to the calling php script
     * @param array command line arguments
     * @return null; 
     */
    public static function showUsageInfo() {

        $message = <<< EOT
                
============================================================
Usage: Script requires 2 or 3 parameters.

1st Param: ContextName (where solr is deployed). Set 'useconfig' if using config file. 
2nd Param: supply one of the 4 possitble values {all,cityids,resids,rescodes}
3rd Param: (required if 2nd args != 'all') supply comma delimited values(without space).

Sample Examples:

<<<<<<<<<<<<<<<<<<< INFO: cityid and codes: >>>>>>>>>>>>
austin: 1241 | san francisco: 23637 | newyork: 18848
NOTE: THERE IS NO SPACE AFTER ",(COMMA)" IF SUPPLYING MULTIPLE VALUES

1. Indexing on restaurant core
    a. php hbrestaurant_cjb.php solr all
    b. php hbrestaurant_cjb.php solr cityids 1241,18848
    c. php hbrestaurant_cjb.php solr resids 49391,49392,49393
    d. php hbrestaurant_cjb.php solr rescodes RAUSTIN1207,RAUSTIN487,RAUSTIN682
        
2. Indexing on menu core
    a. nohup php hbmenu_cjb.php solr all &
    b. nohup php hbmenu_cjb.php solr cityids 1241,18848 &
    c. php hbmenu_cjb.php solr resids 49391,49392,49393
    d. php hbmenu_cjb.php solr rescodes RAUSTIN1207,RAUSTIN487,RAUSTIN682
                
Use similar options for data deletion.
============================================================
                
EOT;
        echo $message;
    }

    /**
     * Returns array containing indexing 'type' {all, cityids, resids, rescodes} 
     * and corresponding auxiliary value(s).
     * @param array $params command line arguments i.e. $argv
     * @return array containing 'type' and one of city_ids or res_ids or res_code
     */
    public static function getIndexTypeWithData($params) {
        $result = array();
        switch ($params[2]) {
            case 'all':
                $result['type'] = 'all';
                break;
            case 'cityids':
                $result['type'] = 'cityids';
                if (!isset($params[3])) {
                    die("\nMissing City Ids.\n\n");
                }
                $result['ids'] = explode(',', trim($params[3], ','));
                break;
            case 'resids':
                $result['type'] = 'resids';
                if (!isset($params[3])) {
                    die("\nMissing RestaurantIds Ids.\n\n");
                }
                $result['ids'] = explode(',', trim($params[3], ','));
                break;
            case 'rescodes':
                $result['type'] = 'rescodes';
                if (!isset($params[3])) {
                    die("\nMissing rest_code.\n\n");
                }
                $result['ids'] = explode(',', trim($params[3], ','));
                break;
            default :
                echo "\n=========Incorrect Use=======\n\n";
                break;
        }
        return $result;
    }

    /**
     * Get the 'data' and 'status_code' for the input url
     * @param string $url url string
     * @return array containg data and status_code
     */
    public static function getCurlUrlData($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response ['data'] = curl_exec($ch); // data string
        $response ['status_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response;
    }

    /**
     * Prints an array or object with some debugging info
     * @param Object $obj array | object | string | number
     * @param boolean $exit exit or not. default false.
     */
    public static function pr($obj, $exit = false) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        echo "<pre>";
        echo "\n=====>  Called from " . $caller['file'] . " " . $caller['line'] . "\n\n";
        print_r($obj);
        echo "\n\n";
        if ($exit) {
            exit;
        }
    }

    /**
     * Get a new mysqli db object with utf8 encoding.
     * @return \mysqli mysqli connection object.
     */
    public static function getMysqliObject() {
        $mysqli = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        /* check connection */
        if ($mysqli->connect_errno) {
            printf("Connect failed: %s\n", $mysqli->connect_error);
            return NULL;
        }
        /* change character set to utf8 */
        if (!$mysqli->set_charset("utf8")) {
            printf("Error loading charset utf8: %s", $mysqli->error);
        } else {
            printf("\nINFO::mysql charset in use: %s\n", $mysqli->character_set_name());
        }
        return $mysqli;
    }

    /**
     * curl object for posting json data to solr
     * @param String curl_url
     * @return resource
     */
    public static function getSolrCurlPostObject($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        return $curl;
    }

    /**
     * To get decoded json response of solr ping url
     * @param String $url solr ping url
     * @return array decoded json response
     */
    public static function getSolrHealthResponse($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $raw_json = curl_exec($ch);
        curl_close($ch);
        return json_decode($raw_json, true);
    }
    
    public static function getSolrCommitResponse($commitUrl) {
        echo "\n============COMMITTING ON SOLR============\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $commitUrl);
        $result = curl_exec($ch);
        echo "INFO: Commit Response: " . $result . "\n";
        curl_close($ch);
    }

    public static function showResIndexProgress($total, $counter, $time_start) {
        echo "\n===========PROGRESS===================\n";
        echo "Restaurants indexed: " . $counter . "\n";
        $time = time();
        $seconds_taken = $time - $time_start;
        echo "Time elapsed = " . gmdate("H:i:s", $seconds_taken) . "\n";
        $prog_complete = ($counter / $total) * 100;
        $prog_left = 100 - $prog_complete;
        $time_left = ($prog_left / $prog_complete) * $seconds_taken;
        echo "Approx time left = " . gmdate("H:i:s", $time_left) . "\n";
        echo "\n=======================================\n";
    }
    
    public static function getArrayStringsWithDoubleQuote($arr){
        $result = array();
        foreach ($arr as $str) {
            $result[] = '"'. trim($str) . '"';
        }
        return $result;
    }
    
    /**
     * Get array from comma delimited list of allowed zips.
     * @param string $allowed_zips
     * @return array
     */
    public static function getAllowedZipsArr($allowed_zips) {
        if($allowed_zips == ''){
            return array();
        }
        $zips = array();
        foreach (explode(',', $allowed_zips) as $zip){
            $zip = trim($zip, ' ,');
            if($zip != ''){
                $zips[] = $zip;
            }
        }
        return $zips;
    }

}
