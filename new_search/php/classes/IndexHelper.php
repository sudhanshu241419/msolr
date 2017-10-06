<?php

namespace SolrIndexing;

class IndexHelper {

    public static $mapDay2To3 = array('mo' => 'mon', 'tu' => 'tue', 'we' => 'wed', 'th' => 'thu',
        'fr' => 'fri', 'sa' => 'sat', 'su' => 'sun');
    public static $mapNextDay = array('mo' => 'tu', 'tu' => 'we', 'we' => 'th', 'th' => 'fr',
        'fr' => 'sa', 'sa' => 'su', 'su' => 'mo');

    /**
     * @var mysqli MySQL connection
     */
    private $mysqli;

    public function __construct() {
        $this->mysqli = Helpers::getMysqliObject();
    }

    public function __destruct() {
        $this->mysqli->close();
    }

    public function getRestFieldsForRestView() {
        
    }

    public function getRestFieldsForFoodView() {
        
    }

    public function addResCalendarFields($res_id, &$doc) {
        $query = "SELECT
            TRIM(calendar_day) AS calendar_day,
            TIME_FORMAT(open_time,'%H%i') AS ot,
            TIME_FORMAT(close_time,'%H%i') AS ct,
            TIME_FORMAT(breakfast_start_time,'%H%i') AS bst,
            TIME_FORMAT(breakfast_start_time,'%H:%i') AS bst2,
            TIME_FORMAT(breakfast_end_time,'%H%i') AS bet,
            TIME_FORMAT(breakfast_end_time,'%H:%i') AS bet2,
            TIME_FORMAT(lunch_start_time,'%H%i') AS lst,
            TIME_FORMAT(lunch_start_time,'%H:%i') AS lst2,
            TIME_FORMAT(lunch_end_time,'%H%i') AS let,
            TIME_FORMAT(lunch_end_time,'%H:%i') AS let2,
            TIME_FORMAT(dinner_start_time,'%H%i') AS dst,
            TIME_FORMAT(dinner_start_time,'%H:%i') AS dst2,
            TIME_FORMAT(dinner_end_time,'%H%i') AS det,
            TIME_FORMAT(dinner_end_time,'%H:%i') AS det2,
            operation_hours AS oh,
            operation_hrs_ft AS ohft
            FROM restaurant_calendars
            WHERE calendar_day !=  '' AND restaurant_id = " . $res_id;
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $days = array();
            $cal = array();
            $del_hrs = array();
            $oh_ft = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $day = $row['calendar_day'];

                $day_del_hrs = array();
                if ($row['bst2'] != '' && $row['bet2'] != '') {
                    $day_del_hrs[] = $row['bst2'] . '-' . $row['bet2'];
                }
                if ($row['lst2'] != '' && $row['let2'] != '') {
                    $day_del_hrs[] = $row['lst2'] . '-' . $row['let2'];
                }
                if ($row['dst2'] != '' && $row['det2'] != '') {
                    $day_del_hrs[] = $row['dst2'] . '-' . $row['det2'];
                }
                $del_hrs[] = $day . '|' . implode(',', $day_del_hrs);

                $days[] = self::$mapDay2To3[$day];
                $opentime = (int) $row['ot'];
                $closetime = (int) $row['ct'];
                if ($opentime < $closetime) {
                    $cal['ot1_' . $day] = $opentime;
                    $cal['ct1_' . $day] = $closetime;
                } else {
                    $cal['ot1_' . $day] = $opentime;
                    $cal['ct1_' . $day] = 2359;
                    $cal['ot2_' . self::$mapNextDay[$day]] = 0;
                    $cal['ct2_' . self::$mapNextDay[$day]] = $closetime;
                }
                //breakfast start-end time
                $bst = (int) $row['bst'];
                $bet = (int) $row['bet'];
                //lunch start-end time
                $lst = (int) $row['lst'];
                $let = (int) $row['let'];
                //dinner start-end time
                $dst = (int) $row['dst'];
                $det = (int) $row['det'];

                if ($bst <= $bet) {
                    $cal['bst1_' . $day] = $bst;
                    $cal['bet1_' . $day] = $bet;
                } else {
                    $cal['bst1_' . $day] = $bst;
                    $cal['bet1_' . $day] = 2359;
                }

                if ($lst <= $let) {
                    $cal['lst1_' . $day] = $lst;
                    $cal['let1_' . $day] = $let;
                } else {
                    $cal['lst1_' . $day] = $lst;
                    $cal['let1_' . $day] = 2359;
                }

                if ($dst <= $det) {
                    $cal['dst1_' . $day] = $dst;
                    $cal['det1_' . $day] = $det;
                } else {
                    $cal['dst1_' . $day] = $dst;
                    $cal['det1_' . $day] = 2359;
                    $cal['dst2_' . self::$mapNextDay[$day]] = 0;
                    $cal['det2_' . self::$mapNextDay[$day]] = $det;
                }

                $oh_arr = explode(',', $row['oh']);
                $count = min(count($oh_arr), 4);
                if ($count == 1) {//buggy code, will remove soon
                    $slot = explode('-', trim($oh_arr[0]));
                    $temp_st = (int) str_replace(':', '', substr($slot[0], 0, 5));
                    $st = $temp_st + ($temp_st % 100 == 0 ? 30 : 70);
                    $et = (int) str_replace(':', '', substr($slot[1], 0, 5));
                    if ($st < $et) {
                        $cal['ooh1_' . $day] = $st;
                        $cal['coh1_' . $day] = $et;
                    } else {
                        $cal['ooh1_' . $day] = $st;
                        $cal['coh1_' . $day] = 2359;
                        $cal['ooh2_' . self::$mapNextDay[$day]] = 0;
                        $cal['coh2_' . self::$mapNextDay[$day]] = $et;
                    }
                }

                if ($count > 1) {//temp check, will be removed when data guaranteed
                    for ($i = 0; $i < $count; $i++) {
                        $slot = explode('-', trim($oh_arr[$i]));
                        $script = $i + 1;
                        $temp_st = (int) str_replace(':', '', substr($slot[0], 0, 5));
                        $st = $temp_st + ($temp_st % 100 == 0 ? 30 : 70);
                        $cal['ooh' . $script . '_' . $day] = $st;
                        $cal['coh' . $script . '_' . $day] = (int) str_replace(':', '', substr($slot[1], 0, 5));
                    }
                }

                $oh_ft[] = $day . '|' . trim($row['ohft']);
            }
            $doc['working_days'] = $days;
            $doc['delivery_hrs'] = implode('$', $del_hrs);
            $doc['oh_ft'] = implode('$', $oh_ft);
            foreach ($cal as $k => $v) {
                $doc[$k] = $v;
            }
        } else {
            $this->bad_data['missingCalendar'][] = $doc['res_id'];
            $doc['working_days'] = array();
        }
        $rs->free();
    }

    public function addResDealsFields($res_id, &$doc) {
        $query = "SELECT title, type, start_on, end_date, discount, discount_type, 
            minimum_order_amount, days, slots, description, deal_for 
            FROM restaurant_deals_coupons
            WHERE type = 'deals' AND status = 1 AND user_deals = 0 AND restaurant_id = $res_id;";
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {

            $delivery_deals = 0;
            $takeout_deals = 0;
            $dinein_deals = 0;
            $reservation_deals = 0;

            $deals = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $deals[] = array(
                    'title' => $row['title'],
                    'type' => $row['type'],
                    'start_on' => $row['start_on'],
                    'end_date' => $row['end_date'],
                    'discount' => $row['discount'],
                    'discount_type' => $row['discount_type'],
                    'minimum_order_amount' => $row['minimum_order_amount'],
                    'days' => $row['days'],
                    'slots' => $row['slots'],
                    'description' => $row['description'],
                    'deal_for' => $row['deal_for']
                );

                $deal_types = explode(',', preg_replace('/,\s+/', ',', trim($row['deal_for'])));
                foreach ($deal_types as $type) {
                    switch ($type) {
                        case 'delivery':
                            $delivery_deals = 1;
                            break;
                        case 'takeout':
                            $takeout_deals = 1;
                            break;
                        case 'dinein':
                            $dinein_deals = 1;
                            break;
                        case 'reservation':
                            $reservation_deals = 1;
                            break;
                    }
                }
            }

            $doc['has_deals'] = 1;
            $doc['has_delivery_deals'] = $delivery_deals;
            $doc['has_takeout_deals'] = $takeout_deals;
            $doc['has_dinein_deals'] = $dinein_deals;
            $doc['has_reservation_deals'] = $reservation_deals;
            $doc['deals_count'] = $rs->num_rows;

            $doc['deals'] = json_encode($deals);
            $doc['r_score'] += 50;
        }
        $rs->free();
    }

    /**
     * adds preordering_enabled and ordering_enabled fields in solrdoc
     * @param array $doc current doc
     */
    public function addResExtraFields(&$doc) {
        $preorder_cond = $doc['res_reservations'] && $doc['is_registered'] && $doc['accept_cc_phone'];
        if ($preorder_cond) {
            $doc['preordering_enabled'] = 1;
        }
        $hasorder_cond = ($doc['accept_cc_phone']) && ($doc['res_delivery'] || $doc['res_takeout'] || $doc['res_reservations']);
        if ($hasorder_cond) {
            $doc['ordering_enabled'] = 1;
        }
    }
    
    public function addResFeaturesFields(&$doc) {
        $query = "SELECT
            TRIM(ft.features) AS feature_name,
            ft.id AS feature_id
            FROM features AS ft
            LEFT JOIN restaurant_features AS rsft ON ft.id = rsft.feature_id
            WHERE rsft.restaurant_id = " . $doc['res_id'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $featurename_arr = array();
            $featureid_arr = array();
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $featurename_arr[] = $row['feature_name'];
                $featureid_arr[] = $row['feature_id'];
            }
            $doc['feature_fct'] = $featurename_arr;
            $doc['feature_name'] = implode(', ', $featurename_arr);
            $doc['feature_id'] = $featureid_arr;
            $doc['r_score'] += 2;
        } else {
            $doc['feature_name'] = '';
            $doc['feature_fct'] = array();
            $doc['feature_id'] = array();
        }
        $rs->free();
    }

    public function addResTags(&$doc) {
        $query = 'SELECT t.id as tag_id, t.name AS tag_name
            FROM tags AS t
            LEFT JOIN restaurant_tags AS rt ON t.id = rt.tag_id
            WHERE rt.restaurant_id = '. $doc['res_id'] . ' AND rt.status = 1';
        $rs = $this->mysqli->query($query);
        $tags_arr = array();
        if ($rs->num_rows > 0) {
            $doc['r_score'] += 5;
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $tags_arr[] = $row['tag_name'];
            }

        }
        $doc['tags'] = implode(', ',$tags_arr);
        $doc['tags_fct'] = $tags_arr;
        $rs->free();
    }
}
