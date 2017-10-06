<?php
namespace SolrIndexing;

class IndexRestaurantData {
    
    /**
     *
     * @var boolean show progress
     */
    private $show_progress = false;

    /**
     * @var mysqli MySQLi connection
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
     * @var String Solr health url for restaurant core
     */
    private $url_health;
    
    /**
     * @var resource curl object for posting json data to solr
     */
    private $curl_post;
    
    /**
     * For optimizing mysql
     * @var boolean */
    private $logQuery = false;
    
    /**
     * If $this->logQuery is true. Store these Queries.
     * @var array 
     */
    private $loggedQueries = array();
    
    private $bad_data = array();

    /**
     * Class for indexing restaurants data.
     * @param boolean $show_progress pass as true if progress needs to be displayed
     */
    public function __construct($show_progress = FALSE) {
        $this->show_progress = $show_progress;
        $this->mysqli = Helpers::getMysqliObject();
        $this->url_post = SOLR_URL . 'hbr/update/json';
        $this->url_health = SOLR_URL . 'hbr/admin/ping?wt=json&echoParams=none';
        $this->curl_post = Helpers::getSolrCurlPostObject($this->url_post);
        $this->indexHelper = new IndexHelper();
    }

    public function __destruct() {
        $this->mysqli->close();
        curl_close($this->curl_post);
        unset($this->url_post);
        unset($this->url_health);
        unset($this->bad_data);
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
        curl_setopt($this->curl_post, CURLOPT_POSTFIELDS, json_encode($solrdocs));
        $result = curl_exec($this->curl_post);
        echo 'Result of postToSolr: ' . $result;
    }

    public function solrCommit() {
        $commitUrl = $this->url_post . '?commit=true';
        Helpers::getSolrCommitResponse($commitUrl);
    }

    public function listBadData() {
        echo "\nEchoing Bad data \n";
        echo json_encode($this->bad_data);
        echo "\n\n";
    }

    /**
     * Index all restaurants in db where closed = 0 and inactive = 0
     */
    public function indexAllRestaurants() {
        $where = " WHERE closed = 0 AND inactive = 0";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedRestaurants($selectedRest);
    }

    /**
     * Index supplied cities' restaurants in db where closed = 0 and inactive = 0
     */
    public function indexCityRestaurants($cities = array()) {
        $ids = '(' . implode(',', $cities) . ')';
        echo "Indexing data for the following cities: " . $ids . "\n\n";
        $where = "WHERE closed = 0 AND inactive = 0 AND city_id IN $ids";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedRestaurants($selectedRest);
    }

    /**
     * @param array $res_ids */
    public function indexRestaurantsByIds($res_ids = array()) {
        if(empty($res_ids)){
            echo "\n>>>>>>>>>>>>>>>>>>>Nothing to index<<<<<<<<<<<<<<<<<<<<\n";
            return ;
        }
        if(count($res_ids) == 1){
            //$this->logQuery = true;
        }
        $ids = '(' . implode(',', $res_ids) . ')';
        echo "Indexing restaurants having following ids: " . $ids . "\n\n";
        $where = "WHERE closed = 0 AND inactive = 0 AND id IN $ids";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedRestaurants($selectedRest);
    }

    /**
     * Method to index restaurants by supplying restaurant codes array
     * @param array $res_codes array of restaurant codes
     */
    public function indexRestaurantsByCodes($res_codes) {
        $codes_with_quote = Helpers::getArrayStringsWithDoubleQuote($res_codes);
        $codes = '(' . implode(',', $codes_with_quote) . ')';
        echo "INFO::Indexing data for the following restaurant code(s): " . $codes . "\n";
        $where = " WHERE closed = 0 AND inactive = 0 AND rest_code IN $codes";
        $selectedRest = $this->getResMasterFieldsQuery($where);
        $this->indexSelectedRestaurants($selectedRest);
    }

    private function getResMasterFieldsQuery($where) {
        $query = "SELECT
         id AS res_id,
         TRIM(restaurant_name) AS res_name,
         rest_code AS res_code,
         description AS res_description,
         TRIM(address) AS res_address,
         city_id,
         TRIM(zipcode) AS res_zipcode,
         TRIM(allowed_zip) AS allowed_zip,
         TRIM(landmark) AS res_landmark,
         restaurant_image_name AS res_primary_image,
         ratings AS res_ratings,
         latitude AS location_lat,
         longitude AS location_long,
         nbd_latitude AS nbd_lat,
         nbd_longitude AS nbd_long,

         phone_no AS res_phone_no,
         phone_no2 AS res_phone2,
         email AS res_email,
         mobile_no AS res_mobile,
         fax AS res_fax,
         accept_cc,
         accept_cc_phone,
         delivery AS res_delivery,
         takeout AS res_takeout,
         dining AS res_dining,
         reservations AS res_reservations,
         neighborhood AS res_neighborhood,
         borough,
         price AS res_price,
         CHAR_LENGTH(price) AS r_price_num,
         minimum_delivery AS res_minimum_delivery,
         delivery_area,
         delivery_charge,
         delivery_desc,
         parking_desc,
         menu_available,
         menu_without_price,
         TRIM(meals) AS res_meals,
         is_chain,
         closed,
         inactive,
         delivery_geo,
         order_pass_through
         FROM restaurants " . $where;
        return $query;
    }

    private function indexSelectedRestaurants($selectedRestQuery) {
        //pr($selectedRestQuery, true);
        set_time_limit(0);
        error_reporting(1);
        $time_start = time();
        //srResult = selected restaurants result set
        $rs = $this->mysqli->query($selectedRestQuery);
        $total = $rs->num_rows;
        if ($total > 0) {
            echo "\nTotal documents to import = " . $total . "\n";
            $counter = 0;
            $postSize = 1;
            $solrdocs = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $solrdocs[] = $this->prepareSolrDoc($row);
                $counter++;
                if ($counter % $postSize == 0) {
                    $this->postToSolr($solrdocs);
                    $solrdocs = array();
                    if( $this->show_progress && ($counter % 500 == 0) ) {
                        Helpers::showResIndexProgress($total, $counter, $time_start);
                        sleep(1);
                    }
                }
            }

            //index left out documents
            if (count($solrdocs) > 0) {
                $this->postToSolr($solrdocs);
            }
            echo 'Imported so far = ' . $counter . "\n";
        }
        $rs->free(); // free result set

        if($this->logQuery){
            pr($this->loggedQueries);
        }
        $time_end = time();
        $time = $time_end - $time_start;
        echo "\nTotal Time Taken = " . gmdate("H:i:s", $time) . "\n";
        echo "\nDone!!! Have Fun :-)\n";
    }

    private function prepareSolrDoc($row) {
        $res_id = $row['res_id'];
        $doc = array();
        $doc['r_score'] = 0;
        $this->addResMainData($row, $doc);
        $this->addResCityData($doc);
        $this->addResCuisines($res_id, $doc);
        $this->indexHelper->addResFeaturesFields($doc);
        $this->addResMenu($res_id, $doc);
        $this->addResImgData($res_id, $doc);
        $this->indexHelper->addResDealsFields($res_id, $doc);
        $this->addResCoupons($res_id, $doc);
        $this->addResSpecialFeatures($res_id, $doc);
        $this->addResPopularity($res_id, $doc);
        $this->addResReviews($res_id, $doc);
        //$this->addResCalData($doc);
        $this->indexHelper->addResCalendarFields($res_id, $doc);
        $this->indexHelper->addResTags($doc);
        $this->addResAds($res_id, $doc);
        $this->indexHelper->addResExtraFields($doc);
        return $doc;
    }

    private function addResMainData($row, &$doc) {
        echo "Adding res_id: " . $row['res_id']. "\n";
        $doc['res_id'] = $row['res_id'];

        $doc['res_name'] = $row['res_name'];
        $doc['res_code'] = $row['res_code'];
        $doc['res_description'] = $row['res_description'];
        $doc['res_address'] = $row['res_address'];
        $doc['res_street'] = preg_replace('/\d+\s+/', '', $doc['res_address']);
        $doc['res_zipcode'] = $row['res_zipcode'];
        $doc['allowed_zip'] = Helpers::getAllowedZipsArr($row['allowed_zip']);
        $doc['city_id'] = (int) $row['city_id'];
        //$doc['res_city'] = $row['res_city'];
        $doc['res_primary_image'] = $row['res_primary_image'];
        $doc['res_ratings'] = $row['res_ratings'];
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

        $doc['res_phone_no'] = $row['res_phone_no'];
        $doc['res_phone2'] = $row['res_phone2'];
        $doc['res_email'] = $row['res_email'];
        $doc['res_mobile'] = $row['res_mobile'];
        $doc['res_fax'] = $row['res_fax'];
        $doc['accept_cc'] = $row['accept_cc'];
        $doc['accept_cc_phone'] = (int) $row['accept_cc_phone'];
        $doc['r_score'] += ($doc['accept_cc_phone'] > 0) ? 50 : 0;
        $doc['res_delivery'] = $row['res_delivery'];
        $doc['res_takeout'] = (int) $row['res_takeout'];
        $doc['res_dining'] = (int) $row['res_dining'];
        $doc['res_reservations'] = (int) $row['res_reservations'];
        $doc['res_neighborhood'] = trim($row['res_neighborhood']);
        $doc['res_landmark'] = $doc['res_neighborhood'];
        $doc['borough'] = $row['borough'];
        //$doc['res_payment_modes'] = $row['res_payment_modes'];
        $doc['res_price'] = $row['res_price'];
        $doc['r_price_num'] = (int) $row['r_price_num'];
        $doc['res_minimum_delivery'] = (float) $row['res_minimum_delivery'];
        $doc['delivery_area'] = (float) $row['delivery_area'];
        $doc['delivery_charge'] = (float) $row['delivery_charge'];
        $doc['delivery_desc'] = $row['delivery_desc'];
        $doc['r_hit_count'] = (int) $row['hit_count'];
        $doc['r_menu_available'] = (int) $row['menu_available'];
        $doc['r_menu_without_price'] = (int) $row['menu_without_price'];
        //remove white spaces from meals otherwise sent data might be incorrect
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

    private function addResCityData(&$doc) {
        $query = "SELECT city_name AS res_city, time_zone FROM cities WHERE id = " . $doc['city_id'];
        $rs = $this->mysqli->query($query);
        $row = $rs->fetch_array(MYSQLI_ASSOC);
        $doc['res_city'] = $row['res_city'];
        $doc['city_reservation_date_time'] = $row['time_zone'];
        $rs->free();
    }

    private function addResCuisines($res_id, &$doc) {
        $query = "SELECT cui.id,
            TRIM(cui.cuisine) AS res_cuisine
            FROM cuisines AS cui
            LEFT JOIN restaurant_cuisines AS rscui ON cui.id = rscui.cuisine_id
            WHERE restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['r_score'] += 20;
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
            //$this->bad_data['missingCuisines'][] = $res_id;
            $doc['cuisine_id'] = array();
            $doc['res_cuisine'] = '';
            $doc['cuisine_fct'] = array();
        }
        $rs->free();
    }

    private function addResMenu($res_id, &$doc) {
        $query = "SELECT DISTINCT TRIM( menus.item_name ) AS res_menu_name
                FROM menus WHERE restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $res_menu_arr = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $res_menu_arr[] = $row['res_menu_name'];
            }
            $doc['has_menu'] = 1;
            $doc['menu_fct'] = $res_menu_arr;
            $doc['res_menu'] = implode(', ', $res_menu_arr);
            $doc['r_score'] += 5;
        } else {
            //$this->bad_data['missingMenu'][] = $res_id;
        }
        $rs->free();
    }

    private function addResImgData($res_id, &$doc) {
        $query = "SELECT TRIM(image) as res_image
            FROM restaurant_images WHERE status = 1 AND restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        $galleries = array();
        if (isset($doc['res_primary_image']) && ($doc['res_primary_image'] != '')) {
            $galleries[] = $doc['res_primary_image'];
        }
        if ($rs->num_rows > 0) {
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $galleries[] = $row['res_image'];
            }
        }
        $doc['galleries'] = $galleries;
        $doc['gallery_count'] = count($galleries);
        if ($doc['gallery_count'] > 0) {
            $doc['r_img_boost'] = 1;
            $doc['r_score'] += 5;
        }
        $rs->free();
    }
    
    private function addResCoupons($res_id, &$doc) {
        $query = "SELECT title, price
            FROM restaurant_deals_coupons
            WHERE type = 'coupons' AND restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['has_coupons'] = 1;
            $doc['coupons_count'] = $rs->num_rows;
            $doc['r_score'] += 5;
        }
        $rs->free();
    }

    private function addResSpecialFeatures($res_id, &$doc) {
        $query = "SELECT restaurant_id
          FROM  restaurant_accounts WHERE status = 1 AND restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['is_registered'] = 1;
        }
        $rs->free();
    }

    private function addResPopularity($res_id, &$doc) {
        $query = "SELECT type
               FROM  restaurant_bookmarks WHERE restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $doc['popularity'] = $rs->num_rows;
        }
        $rs->free();
    }

    private function addResReviews($res_id, &$doc) {
        $review_count = 0;
        
        //reviews from restaurant_review table
        $query = "SELECT id FROM restaurant_reviews
         WHERE sentiments = 'Positive' AND restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $review_count += $rs->num_rows;
        }
        $rs->free();
        
        //reviews from user_reviews table
        $query2 = "SELECT id FROM user_reviews
                  WHERE status = 1 AND restaurant_id = " . $res_id;
        $rs2 = $this->mysqli->query($query2);
        if ($rs2->num_rows > 0) {
            $review_count += $rs2->num_rows;
        }
        $rs2->free();
        
        
        if($review_count > 0){
            $doc['r_review_count'] = $review_count;
            $doc['has_reviews'] = 1;
            $doc['r_score'] += 5;
        }
    }
    
    private function addResAds($res_id, &$doc) {
        $query = "SELECT keywords
            FROM restaurant_ads
            WHERE restaurant_id = $res_id AND status = 1";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $keywords = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                foreach (explode(',', trim($row['keywords'])) as $keyword) {
                    $keywords[] = trim($keyword);
                }
            }
            $doc['ad_keywords_fct'] = $keywords;
            $doc['ad_keywords'] = implode("|", $keywords);
        }
        $rs->free();
    }
    
}
