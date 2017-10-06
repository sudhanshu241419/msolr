<?php

namespace SolrIndexing;

$indexAll = false; //make it true to index all required fields

$time_start = time();
chdir(dirname(__FILE__));
require_once('../../php/conf/config.php');

$mysqli = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
/* check connection */
if ($mysqli->connect_errno) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
    return NULL;
}

$tablesAndIndices = getTablesAndIndicesArr();

$missingIndices = array();
foreach ($tablesAndIndices as $tableName => $requiredIndices) {
    $query = "SHOW INDEX FROM $tableName ";
    $resultset = $mysqli->query($query);
    if ($resultset->num_rows > 0) {
        $existingIndices = array();
        while ($row = $resultset->fetch_assoc()) {
            $existingIndices[] = $row['Key_name'];
        }
        foreach ($requiredIndices as $requiredIndex) {
            if ($indexAll || (!in_array($requiredIndex, $existingIndices))) {
                $missingIndices[$tableName][] = $requiredIndex;
            }
        }
    }
}
pr($missingIndices);

$indexQueries = array();
foreach ($missingIndices as $tableName => $requiredIndexArr) {
    foreach ($requiredIndexArr as $requiredIndex) {
        $indexQueries[] = "ALTER TABLE `" . $tableName . "` ADD INDEX `" . $requiredIndex . "` (`$requiredIndex`);";
    }
}

foreach ($indexQueries as $indQuery) {
    echo "\nExecuting Query: " . $indQuery;
    echo "\nResult: " . intval($mysqli->query($indQuery));
}
$mysqli->close();

$time = time() - $time_start;
echo "\n\nTotal Time Taken = " . gmdate("H:i:s", $time);
echo "\nDone!!! Have Fun :-)\n";

/**
 * Index name is same as field name
 * @return array
 */
function getTablesAndIndicesArr() {

    //*//for cms
    $tablesAndIndices = array();
    $tablesAndIndices['cms_modules'] = array('link');
    $tablesAndIndices['pubnub_notification'] = array('user_id', 'read_status', 'created_on');
    $tablesAndIndices['cms_roles_access'] = array('module_view', 'module_id', 'role_id');
    $tablesAndIndices['cms_users'] = array('role_id');
    $tablesAndIndices['cities'] = array('state_code', 'city_name');
    $tablesAndIndices['user_transactions'] = array('user_id');
    $tablesAndIndices['user_order_addons'] = array('user_order_detail_id');
    $tablesAndIndices['user_action_settings'] = array('user_id');
    $tablesAndIndices['user_points'] = array('user_id', 'promotionId', 'created_at', 'promotionId');
    $tablesAndIndices['users'] = array('wallet_balance', 'referral_code', 'email');
    $tablesAndIndices['user_promocodes'] = array('user_id');
    $tablesAndIndices['user_reviews'] = array('restaurant_id', 'status');
    $tablesAndIndices['restaurant_upload_temp'] = array('city_id', 'table_name', 'rest_code', 'status', 'created_at');
    $tablesAndIndices['user_orders'] = array('fname', 'lname', 'order_amount', 'delivery_time', 'is_deleted', 'status', 'created_at');
    $tablesAndIndices['user_reservations'] = array('first_name', 'last_name', 'restaurant_name', 'reserved_on', 'status', 'time_slot');
    $tablesAndIndices['merchant_registration'] = array('restaurant_name', 'status', 'created_on');
    $tablesAndIndices['cms_user_log'] = array('module_id', 'user_id', 'action_id', 'log_date', 'tablename');
    //*/
    //for solr
    $tablesAndIndices['restaurants'] = array('rest_code', 'featured', 'city_id', 'closed', 'inactive');
    $tablesAndIndices['restaurant_cuisines'] = array('restaurant_id', 'cuisine_id');
    $tablesAndIndices['restaurant_features'] = array('restaurant_id');
    $tablesAndIndices['menus'] = array('restaurant_id', 'status');
    $tablesAndIndices['menu_prices'] = array('menu_id');
    $tablesAndIndices['states'] = array('status');
    $tablesAndIndices['tags'] = array('status');
    $tablesAndIndices['restaurant_images'] = array('restaurant_id', 'status');
    $tablesAndIndices['restaurant_deals_coupons'] = array('restaurant_id', 'type', 'status');
    $tablesAndIndices['restaurant_accounts'] = array('restaurant_id', 'status');
    $tablesAndIndices['restaurant_bookmarks'] = array('restaurant_id');
    $tablesAndIndices['restaurant_reviews'] = array('restaurant_id', 'sentiments', 'status');
    $tablesAndIndices['restaurant_calendars'] = array('restaurant_id', 'calendar_day');
    $tablesAndIndices['restaurant_tags'] = array('restaurant_id', 'status', 'tag_id');
    $tablesAndIndices['restaurant_ads'] = array('restaurant_id', 'status');
    $tablesAndIndices['menu_prices'] = array('menu_id');
    $tablesAndIndices['menu_bookmarks'] = array('menu_id');

    return $tablesAndIndices;
}

function pr($obj, $exit = false) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo "\n=====>  Called from " . $caller['file'] . " " . $caller['line'] . "\n\n";
    print_r($obj);
    echo "\n\n";
    if ($exit) {
        exit;
    }
}
