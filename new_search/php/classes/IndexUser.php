<?php

namespace SolrIndexing;

class IndexUser {

    /**
     * @var mysqli MySQLi connection
     */
    private $mysqli;

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
    private $bad_data = array();

    /**
     * Class for indexing restaurants data.
     */
    public function __construct() {
        $this->mysqli = Helpers::getMysqliObject();
        $this->url_post = SOLR_URL . 'hbu/update/json';
        $this->url_health = SOLR_URL . 'hbu/admin/ping?wt=json&echoParams=none';
        $this->curl_post = Helpers::getSolrCurlPostObject($this->url_post);
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
    public function indexAllUsers() {
        $where = " WHERE status = 1";
        $selectedUsers = $this->getUsersMasterFieldsQuery($where);
        $this->indexSelectedUsers($selectedUsers);
    }

    public function getUsersMasterFieldsQuery($where) {
        $query = "SELECT id, CONCAT(first_name, ' ', last_name) AS uname, email, display_pic_url FROM users " . $where;
        return $query;
    }

    private function indexSelectedUsers($selectedUsersQuery) {
        set_time_limit(0);
        error_reporting(1);
        $time_start = time();
        $srResult = $this->mysqli->query($selectedUsersQuery);
        $total = $srResult->num_rows;
        if ($total > 0) {
            echo "\nTotal users to index = " . $total . "\n";
            $counter = 0;
            $postSize = 100;
            $solrdocs = array();
            while ($row = $srResult->fetch_array(MYSQLI_ASSOC)) {
                $solrdocs[] = $this->prepareSolrDoc($row);
                $counter++;
                if ($counter % $postSize == 0) {
                    $this->postToSolr($solrdocs);
                    $solrdocs = array();
                    Helpers::showResIndexProgress($total, $counter, $time_start);
                }
            }

            //index left out documents
            if (count($solrdocs) > 0) {
                $this->postToSolr($solrdocs);
            }
            echo 'Indexed so far = ' . $counter . "\n";
        }
        $srResult->free(); // free result set

        $time_end = time();
        $time = $time_end - $time_start;
        echo "\nTotal Time Taken = " . gmdate("H:i:s", $time) . "\n";
        echo "\nDone!!! Have Fun :-)\n";
    }

    function prepareSolrDoc($row) {
        $doc = array();
        $doc['uid'] = $row['id'];
        $doc['uname'] = $row['uname'];
        $doc['email'] = $row['email'];
        $doc['image_url'] = $row['display_pic_url'];
        $this->addUserAddressesTableFields($doc);
        return $doc;
    }

    function addUserAddressesTableFields(&$doc) {
        $query = "SELECT city, zipcode FROM user_addresses WHERE user_id = " . $doc['uid'];
        $rs = $this->mysqli->query($query);
        if ($rs->num_rows > 0) {
            $row = $rs->fetch_array(MYSQLI_ASSOC);
            $doc['city'] = $row['city'];
            $doc['zipcode'] = $row['zipcode'];
        } else {
            $doc['city'] = '';
            $doc['zipcode'] = '';
        }
        $rs->free();
    }

}
