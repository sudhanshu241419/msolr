<?php

namespace SolrIndexing;

/**
 * Class for indexing/updating restaurants and menus whenver changes occur.
 * This class is meant to be used by a crone job.
 *
 * @author dhirendra
 */
class CmsIndexing {

    /**
     * @var \mysqli mysql db connection 
     */
    private $mysqli;

    public function __construct() {
        $this->mysqli = Helpers::getMysqliObject();
    }

    public function __destruct() {
        if (isset($this->mysqli)) {
            $this->mysqli->close();
        }
    }

    /**
     * Deletes data from solr server (restaurants and menus cores) by scanning <b>cms_solr_indexing</b> table 
     * based on the criteria <b>closed=1</b> and <b>is_indexed=1</b>. It also sets <b>is_indexed=0</b> for such restaurants.
     * @return array containg some info
     */
    public function deleteClosedRestaurantsAndMenusData() {
        $res_ids = $this->getClosedResIds();

        if (count($res_ids) > 0) {
            $delete_what = array('type'=>'resids', 'ids'=>$res_ids,);
            $dd = new DeleteData();
            $dd->deleteFromRestaurant($delete_what);
            $dd->deleteFromMenu($delete_what);

            //======================== UPDATE DB TABLE =========================
            $query = "UPDATE cms_solr_indexing SET is_indexed = 0 WHERE restaurant_id IN (" . implode(',', $res_ids). ")";
            $response = $this->mysqli->query($query);
            if($response){
                echo "\n\nUpdated mysql table with is_indexed = 0 for closed restaurants: " . implode(',', $res_ids);
            } else {
                echo "\n\nMySQL update quiry failed after deleting closed restaurants.\n\n";
            }
            
        } else {
            echo "\nNothing to delete...";
        }
    }


    public function indexRestaurantsAndMenus() {
        $idsAndInfo = $this->getResIdsAndUpdateInfoFromDb();
        $res_ids = $idsAndInfo['ids'];
        
        //delete old menus data before reindexing
        $dd = new DeleteData();
        $dd->deleteFromMenu(array('type'=>'resids', 'ids'=>$res_ids,));
        
        $update_info = $idsAndInfo['update_info'];

        echo "\n\n========== Indexing on Restaurant Core ===============\n";
        $this->indexRestaurants($res_ids);

        echo "\n\n========== Indexing on Menu Core =====================\n";
        $this->indexMenus($res_ids);

        $mysql_update = $this->getSolrUpdateInfo($res_ids, $update_info);

        $info = array();
        foreach ($mysql_update as $record) {
            $query = "UPDATE cms_solr_indexing SET is_indexed = 1, indexed_on = " . $record['indexed_on'] .
                    " WHERE restaurant_id = " . $record['res_id'];
            $response = $this->mysqli->query($query);
            $info[] = array('res_id' => $record['res_id'], 'query_response' => $response);
        }
        return $info;
    }

    /**
     * 
     * @return array containg array of ids (of res_id) and update_info
     */
    private function getResIdsAndUpdateInfoFromDb() {
        $res_ids = array();
        $update_info = array();

        $query = 'SELECT restaurant_id, updated_on FROM cms_solr_indexing WHERE closed = 0 AND is_indexed = 0;';
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $res_ids[] = $row['restaurant_id'];
                $update_info[$row['restaurant_id']] = $row['updated_on'];
            }
        }
        $rs->free();
        echo "\n\nRestaurants to be indexed into SOLR: ". implode(',', $res_ids) ."\n\n";
        return array('ids' => $res_ids, 'update_info' => $update_info);
    }

    private function getClosedResIds() {
        $res_ids = array();
        $query = 'SELECT restaurant_id FROM cms_solr_indexing WHERE closed = 1 AND is_indexed = 1;';
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            while ($row = $rs->fetch_array(MYSQLI_ASSOC)) {
                $res_ids[] = (int)$row['restaurant_id'];
            }
        }
        echo "\nRestaurants to be deleted from SOLR: ". implode(',', $res_ids);
        $rs->free();
        return $res_ids;
    }

    /**
     * Indexes and commits restaurants' data with given ids.
     * @param array $res_ids res_ids to be indexed
     */
    private function indexRestaurants($res_ids) {
        $res_index = new IndexRestaurantData(TRUE);
        $res_index->checkCoreHealth();
        $res_index->indexRestaurantsByIds($res_ids);
        $res_index->solrCommit();
    }

    /**
     * Indexes and commits supplied res_ids' menu data.
     * @param array $res_ids ids of restaurants whose data need to be indexed.
     */
    private function indexMenus($res_ids) {
        $menu_index = new IndexMenuData();
        $menu_index->checkCoreHealth();
        $menu_index->indexMenusByResIds($res_ids);
        $menu_index->solrCommit();
    }

    /**
     * check if supplied restaurants are indexed and return auxiliary mysql data
     * @return array each array element contains res_id, indexed_on, updated_on values
     */
    private function getSolrUpdateInfo($res_ids, $update_info) {
        $mysql_update = array();
        foreach ($res_ids as $res_id) {
            $url = SOLR_URL . 'hbr/select?rows=1&fl=indexed_on&q=res_id:' . $res_id;
            $response = Helpers::getCurlUrlData($url);
            if ($response['status_code'] == 200) {
                $solr_data = json_decode($response['data'], TRUE);
                if ($solr_data['response']['numFound'] > 0) {
                    $indexed_on = strtotime($solr_data['response']['docs'][0]['indexed_on']);
                    if ($indexed_on >= $update_info[$res_id]) {
                        $mysql_update[] = array('res_id' => $res_id, 'indexed_on' => $indexed_on, 'updated_on' => $update_info[$res_id]);
                    }
                }
            }
        }
        return $mysql_update;
    }

}
