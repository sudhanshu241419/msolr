<?php

namespace SolrIndexing;

class IndexMenuData {

    /**
     * @var mysqli MySQL connection
     */
    private $mysqli;
    
    /**
     * @var IndexHelper IndexHelper 
     */
    private $indexHelper;

    /**
     * @var String Solr post url for menu core
     */
    private $url_post;

    /**
     * @var String Solr health url for menu core
     */
    private $url_health;

    /**
     * @var resource curl object for posting json data to solr
     */
    private $curl_post;
    private static $mapDay2To3 = array('mo' => 'mon', 'tu' => 'tue', 'we' => 'wed', 'th' => 'thu',
        'fr' => 'fri', 'sa' => 'sat', 'su' => 'sun');
    private static $mapNextDay = array('mo' => 'tu', 'tu' => 'we', 'we' => 'th', 'th' => 'fr',
        'fr' => 'sa', 'sa' => 'su', 'su' => 'mo');
    
    public function __construct() {
        $this->mysqli = Helpers::getMysqliObject();
        $this->url_post = SOLR_URL . 'hbm/update/json';
        $this->url_health = SOLR_URL . 'hbm/admin/ping?wt=json&echoParams=none';
        $this->curl_post = Helpers::getSolrCurlPostObject($this->url_post);
        $this->indexHelper = new IndexHelper();
    }

    public function __destruct() {
        $this->mysqli->close();
        curl_close($this->curl_post);
        unset($this->url_post);
    }

    public function checkCoreHealth() {
        $dataArr = Helpers::getSolrHealthResponse($this->url_health);
        if ($dataArr['status'] == 'OK') {
            echo "INFO::Solr Core Health is OK.\n";
        } else {
            die("\nSolr Server Error! Exiting now.\n");
        }
    }

    private function postToSolr($solrdocs) {
        $data_string = json_encode($solrdocs);
        curl_setopt($this->curl_post, CURLOPT_POSTFIELDS, $data_string);
        curl_exec($this->curl_post);
    }

    public function solrCommit() {
        $commitUrl = $this->url_post . '?commit=true';
        Helpers::getSolrCommitResponse($commitUrl);
    }

    /**
     * Index all restaurants' menus in db where closed = 0 and inactive = 0
     */
    public function indexAllRestaurantsMenus() {
        $where = " WHERE closed = 0 AND inactive = 0";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedResMenus($selectedRest);
    }

    /**
     * Index supplied cities' restaurants' menus in db where closed = 0 and inactive = 0
     */
    public function indexCityMenus($cities = array()) {
        $ids = '(' . implode(',', $cities) . ')';
        echo "Indexing data for the following cities: " . $ids . "\n\n";
        $where = "WHERE closed = 0 AND inactive = 0 AND city_id IN $ids";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedResMenus($selectedRest);
    }

    public function indexMenusByResIds($res_ids = array()) {
        if (empty($res_ids)) {
            echo "\n>>>>>>>>>>>>>>>>>>> Nothing to index. <<<<<<<<<<<<<<<<<<<<\n";
            return;
        }
        $ids = '(' . implode(',', $res_ids) . ')';
        echo "INFO: Indexing menus for restaurants having following ids: " . $ids . "\n\n";
        $where = "WHERE closed = 0 AND inactive = 0 AND id IN $ids";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedResMenus($selectedRest);
    }

    public function indexMenusByResCodes($res_codes) {
        $codes_with_quote = Helpers::getArrayStringsWithDoubleQuote($res_codes);
        $codes = '(' . implode(',', $codes_with_quote) . ')';
        echo "INFO::Indexing data for the following restaurant code(s): " . $codes . "\n";
        $where = " WHERE closed = 0 AND inactive = 0 AND rest_code IN $codes";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedResMenus($selectedRest);
    }

    public function getResMasterFieldsQuery($where) {
        return "SELECT
         id AS res_id,
         TRIM(restaurant_name) AS res_name,
         rest_code AS res_code,
         TRIM(address) AS res_address,
         city_id,
         TRIM(zipcode) AS res_zipcode,
         TRIM(allowed_zip) AS allowed_zip,
         TRIM(landmark) AS res_landmark,
         restaurant_image_name AS res_primary_image,

         latitude AS location_lat,
         longitude AS location_long,
         nbd_latitude AS nbd_lat,
         nbd_longitude AS nbd_long,

         accept_cc,
         accept_cc_phone,
         delivery AS res_delivery,
         takeout AS res_takeout,
         dining AS res_dining,
         reservations AS res_reservations,
         TRIM(neighborhood) AS res_neighborhood,
         borough,
         price AS res_price,
         CHAR_LENGTH(price) AS r_price_num,
         minimum_delivery AS res_minimum_delivery,
         delivery_area,
         menu_available,
         menu_without_price,
         TRIM(meals) AS res_meals,
         is_chain,
         closed,
         inactive,
         delivery_geo,
         order_pass_through
         FROM restaurants " . $where;
    }

    private function indexSelectedResMenus($selectedRestQuery) {
        set_time_limit(0);
        error_reporting(1);
        $time_start = time();
        //srResult = selected restaurants result set
        $srResult = $this->mysqli->query($selectedRestQuery);
        $total = $srResult->num_rows;
        if ($total > 0) {
            echo "\n============= Indexing Started ==========\n";
            echo "Indexing " . $total . " restaurants menus. \n";
            $counter = 0;
            while ($row = $srResult->fetch_array(MYSQLI_ASSOC)) {
                $this->indexOneResMenus($row);
                $counter++;
                if ($counter % 20 == 0) {
                    Helpers::showResIndexProgress($total, $counter, $time_start);
                }
            }
        }
        $srResult->free(); // free result set
        $time_end = time();
        $time = $time_end - $time_start;
        echo "\nTotal Time Taken = " . gmdate("H:i:s", $time) . "\n";
        echo "\nDone!!! Have Fun :-)\n";
    }

    private function indexOneResMenus($res_row) {
        $resData = $this->getResFixedData($res_row);
        $this->indexResMenus($resData);
    }

    private function getResFixedData($row) {
        $fixedData = array();
        $fixedData['r_score'] = 0;
        $this->addResMainData($row, $fixedData);
        $this->addResCityData($fixedData);
        $this->addResCuisines($fixedData);
        $this->indexHelper->addResFeaturesFields($fixedData);
        $this->indexHelper->addResDealsFields($fixedData['res_id'], $fixedData);
        $this->addResCoupons($fixedData);
        $this->addResSpecialFeatures($fixedData);
        $this->indexHelper->addResCalendarFields($fixedData['res_id'], $fixedData);
        $this->indexHelper->addResTags($fixedData);
        $this->indexHelper->addResExtraFields($fixedData);
        return $fixedData;
    }

    function addResMainData($row, &$doc) {
        $doc['res_id'] = $row['res_id'];
        $doc['res_name'] = $row['res_name'];
        $doc['res_code'] = $row['res_code'];
        $doc['res_address'] = $row['res_address'];
        $doc['res_street'] = preg_replace('/\d+\s+/', '', $doc['res_address']);
        $doc['res_zipcode'] = $row['res_zipcode'];
        $doc['allowed_zip'] = Helpers::getAllowedZipsArr($row['allowed_zip']);
        $doc['city_id'] = (int) $row['city_id'];
        $doc['res_city'] = $row['res_city'];
        $doc['res_primary_image'] = $row['res_primary_image'];
        $l_lat = floatval($row['location_lat']);
        $l_long = floatval($row['location_long']);
        $doc['location_lat'] = $l_lat;
        $doc['location_long'] = $l_long;
        $doc['latlong'] = $l_lat . ',' . $l_long;
        $n_lat = floatval($row['nbd_lat']);
        $n_long = floatval($row['nbd_long']);
        $doc['nbd_lat'] = $n_lat;
        $doc['nbd_long'] = $n_long;
        $doc['nbd_latlong'] = $n_lat . ',' . $n_long;

        $doc['accept_cc'] = (int) $row['accept_cc'];
        $doc['accept_cc_phone'] = (int) $row['accept_cc_phone'];
        $doc['r_score'] += ($doc['accept_cc_phone'] > 0) ? 50 : 0;

        $doc['res_delivery'] = (int) $row['res_delivery'];
        $doc['res_takeout'] = (int) $row['res_takeout'];
        $doc['res_dining'] = (int) $row['res_dining'];
        $doc['res_reservations'] = (int) $row['res_reservations'];

        $doc['res_neighborhood'] = $row['res_neighborhood'];
        $doc['res_landmark'] = $row['res_neighborhood'];
        $doc['borough'] = $row['borough'];
        $doc['res_price'] = $row['res_price'];
        $doc['r_price_num'] = (int) $row['r_price_num'];

        $doc['res_minimum_delivery'] = (int) $row['res_minimum_delivery'];
        $doc['delivery_area'] = (float) $row['delivery_area'];

        $doc['r_menu_available'] = (int) $row['menu_available'];
        $doc['r_menu_without_price'] = (int) $row['menu_without_price'];
        $meals = str_replace("Breakfast/Brunch", "Breakfast", $row['res_meals']);
        $doc['meals_arr'] = explode(',', preg_replace('/\s+/', '', $meals));
        $doc['is_chain'] = (int) $row['is_chain'];
        $doc['r_closed'] = (int) $row['closed'];
        $doc['r_inactive'] = (int) $row['inactive'];
        if(strlen($row['delivery_geo']) > 0){
            $doc['delivery_geo'] = $row['delivery_geo'];
        }
        $doc['order_pass_through'] = (int) $row['order_pass_through'];
    }

    function addResCityData(&$doc) {
        $query = "SELECT city_name AS res_city FROM cities WHERE id = " . $doc['city_id'];
        $rs = $this->mysqli->query($query);
        $row = $rs->fetch_array(MYSQLI_ASSOC);
        $doc['res_city'] = $row['res_city'];
        $rs->free();
    }

    private function indexResMenus($resData) {
        $resMenus = "SELECT
               id as menu_id,
               cuisines_id,
               TRIM(item_name) as menu_name,
               TRIM(image_name) as menu_image,
               item_desc as menu_item_desc,
               online_order_allowed
               FROM menus WHERE status = 1 AND user_deals = 0 AND restaurant_id = " . $resData['res_id'];
        $rs = $this->mysqli->query($resMenus);
        if ($rs->num_rows > 0) {
            $menuDocs = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $currDoc = $this->prepareMenuDoc($resData, $row);
                if ($currDoc['menu_price_num'] >= 1) {
                    $menuDocs[] = $currDoc;
                }
            }
            if(!empty($menuDocs)){
                $this->postToSolr($menuDocs); 
            } else {
                echo "\nNo menus for rest_id: " . $resData['res_id'] . "\n";
            }
        }
        
    }

//    private function checkMenuPrice($menu_id) {
//        $query = "SELECT price FROM menu_prices
//               WHERE menu_id = " . $menu_id . " AND price >= 1";//do not index menu whose price is less than 1
//        $rs = $this->mysqli->query($query);
//        $num_rows = $rs->num_rows;
//        $rs->free();
//        return $num_rows;
//    }

    private function prepareMenuDoc($resFixedData, $menuRow) {
        //$doc = $resFixedData;
        $this->addMenuMainData($resFixedData, $menuRow);
        $this->addMenuCuisines($resFixedData, $menuRow['cuisines_id']);
        $this->addMenuPrices($resFixedData);
        //$this->addMenuAddons($doc);
        $this->addMenuPopularity($resFixedData);
        return $resFixedData;
    }

    function addMenuMainData(&$doc, $row) {
        $doc['menu_id'] = $row['menu_id'];
        $doc['m_score'] = $doc['r_score'];
        $doc['menu_name'] = $row['menu_name'];
        $doc['menu_image'] = $row['menu_image'];
        if ($row['menu_image'] != "" && $row['menu_image'] != NULL) {
            $doc['m_score'] += 30;
        }
        $doc['menu_item_desc'] = $row['menu_item_desc'];
        $doc['online_order_allowed'] = $row['online_order_allowed'];
    }

    private function addMenuCuisines(&$doc, $cuisines_id) {
        $real_cuisine_ids = $this->extractMenuCuisines($cuisines_id);
        if($real_cuisine_ids == ''){
            return;
        }
        $query = 'SELECT id AS cuisines_id, cuisine AS menu_cuisine,
         cuisine_type AS menu_cuisine_type
         FROM cuisines
         WHERE id IN (' . $real_cuisine_ids . ')';
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $ids = array();
            $cuisines = array();
            $types = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $ids[] = $row['cuisines_id'];
                $cuisines[] = $row['menu_cuisine'];
                $types[] = $row['menu_cuisine_type'];
            }
            $doc['m_score'] += 20;
            $doc['menu_cuisines_id'] = $ids;
            $doc['menu_cuisine'] = implode(', ', $cuisines);
            $doc['menu_cuisine_fct'] = $cuisines;
            $doc['menu_cuisine_type'] = $types;
        }
        $rs->free();
    }

    private function addMenuPrices(&$doc) {
        $query = "SELECT
               price_type AS menu_price_type,
               price as menu_price,
               price_desc as menu_price_desc
               FROM menu_prices
               WHERE menu_id = " . $doc['menu_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $row = $rs->fetch_array(MYSQLI_ASSOC);
            $doc['menu_price_type'] = $row['menu_price_type'];
            $price = floatval($row['menu_price']);
            //$doc['menu_price = $row['menu_price'];
            $doc['menu_price'] = ($price != 0) ? number_format($price, 2) : '';
            $doc['menu_price_num'] = $price;
            $doc['menu_price_desc'] = $row['menu_price_desc'];
        } else {
            $doc['menu_price_num'] = 0;
        }
        $rs->free();
    }

    private function addMenuPopularity(&$doc) {
        $query = "SELECT type
         FROM menu_bookmarks
         WHERE menu_id = " . $doc['menu_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['m_score'] += 10;
            $doc['popularity'] = $rs->num_rows;
        }
        $rs->free();
    }

    //not used anymore food core uses menus cuisines
    private function addResCuisines(&$doc) {
        $query = "SELECT cui.id,
            TRIM(cui.cuisine) AS res_cuisine
            FROM cuisines AS cui
            LEFT JOIN restaurant_cuisines AS rscui ON cui.id = rscui.cuisine_id
            WHERE restaurant_id = " . $doc['res_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['r_score'] += 5;
            $cuisineid_arr = array();
            $res_cuisine_arr = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $cuisineid_arr[] = $row['id'];
                $res_cuisine_arr[] = $row['res_cuisine'];
            }
            $doc['cuisine_id'] = $cuisineid_arr;
            $doc['res_cuisine'] = implode(', ', $res_cuisine_arr);
            $doc['cuisine_fct'] = $res_cuisine_arr;
        } else {
            $doc['cuisine_id'] = array();
            $doc['res_cuisine'] = '';
            $doc['cuisine_fct'] = array();
        }
        $rs->free();
    }

    private function addResCoupons(&$doc) {
        $query = "SELECT title, price
            FROM restaurant_deals_coupons
            WHERE type = 'coupons' AND restaurant_id = " . $doc['res_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['has_coupons'] = 1;
            $doc['coupons_count'] = $rs->num_rows;
            $doc['r_score'] += 2;
        }
        $rs->free();
    }

    private function addResSpecialFeatures(&$doc) {
        $query = "SELECT restaurant_id
          FROM  restaurant_accounts
          WHERE status = 1 AND restaurant_id = " . $doc['res_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['is_registered'] = 1;
        }
        $rs->free();
    }
    
    private function addMenuAddons(&$doc) {
        $query = "SELECT
         GROUP_CONCAT( addon_option ) AS menu_addon
         FROM menu_addons
         WHERE menu_id = " . $doc['menu_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $row = $rs->fetch_assoc();
            $doc['menu_addon'] = $row['menu_addon'];
        }
        $rs->free();
    }
    
    private function extractMenuCuisines($cuisines_id) {
        $real_ids = array();
        $cuis_ids = explode(',', $cuisines_id);
        foreach($cuis_ids as $cid){
            if((int)$cid > 0){
                $real_ids[] = $cid;
            }
        }
        return implode(',', $real_ids);
    }
}
