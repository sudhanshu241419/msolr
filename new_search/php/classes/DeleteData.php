<?php

namespace SolrIndexing;

class DeleteData {

    private $curl;
    private $url_hbm;
    private $url_hbr;
    private $url_hbu;

    /**
     * Class for deleting data from solr cores using a delete query.
     */
    public function __construct() {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        $this->url_hbm = SOLR_URL_HBMENU;
        $this->url_hbr = SOLR_URL_HBREST;
        $this->url_hbu = SOLR_URL_HBUSER;
    }

    public function __destruct() {
        curl_close($this->curl);
    }

    public function listVars() {
        $vars = array();
        $vars['url_hbm'] = $this->url_hbm;
        $vars['url_hbr'] = $this->url_hbr;
        $vars['url_hbu'] = $this->url_hbu;
        print_r($vars);
    }

    /**
     * method for deleting $what data from restaurant core
     * containg $what['type']= one of the {all, cityids, resids, rescodes}
     * and $what['ids'] = array of cityids or resids or rescodes
     * @param array $what 
     */
    public function deleteFromMenu($what) {
        $this->deleteFromCore($this->url_hbm, $what);
    }

    /**
     * method for deleting $what data from restaurant core
     * containg $what['type']= one of the {all, cityids, resids, rescodes}
     * and $what['ids'] = array of cityids or resids or rescodes
     * @param array $what 
     */
    public function deleteFromRestaurant($what) {
        $this->deleteFromCore($this->url_hbr, $what);
    }

    /**
     * method for deleting $what data from user core
     * containg $what['type']= one of the {all, cityids}
     * and $what['ids'] = array of cityids or resids or rescodes
     * @param array $what 
     */
    public function deleteFromUser($what) {
        $this->deleteFromCore($this->url_hbu, $what);
    }

    private function deleteFromCore($core_url, $what) {
        echo "\n===============DELETING====================\n";
        $deleteQuery = $this->deleteQuery($what);
        $del_url = $core_url . $deleteQuery;
        echo "URL: " . $del_url . "\n";
        $response = Helpers::getCurlUrlData($del_url);
        echo 'status_code = ' . $response['status_code'] . "\n\n";
    }

    private function deleteQuery($what) {
        $query = '';
        switch ($what['type']) {
            case 'all':
                $query = 'stream.body=<delete><query>*:*</query></delete>';
                break;
            case 'cityids':
                $q = implode('+OR+', $what['ids']);
                $query = 'stream.body=<delete><query>city_id:(' . $q . ')</query></delete>';
                break;
            case 'resids':
                $ids_with_dq = Helpers::getArrayStringsWithDoubleQuote($what['ids']);
                $q = implode('+OR+', $ids_with_dq);
                $query = 'stream.body=<delete><query>res_id:(' . $q . ')</query></delete>';
                break;
            case 'rescodes':
                $ids_with_dq = Helpers::getArrayStringsWithDoubleQuote($what['ids']);
                $q = implode('+OR+', $ids_with_dq);
                $query = 'stream.body=<delete><query>res_code:(' . $q . ')</query></delete>';
                break;
            default :
                break;
        }
        return $query;
    }

    public function commitOnAllCores() {
        $this->commitOnMenuCore();
        $this->commitOnRestaurantCore();
        $this->commitOnUserCore();
    }

    public function commitOnMenuCore() {
        $this->commitOnCore($this->url_hbm);
    }

    public function commitOnRestaurantCore() {
        $this->commitOnCore($this->url_hbr);
    }

    public function commitOnUserCore() {
        $this->commitOnCore($this->url_hbu);
    }

    private function commitOnCore($core_url) {
        $commitUrl = $core_url . 'stream.body=<commit/>';
        echo "\n===============COMMITTING====================\n";
        echo "URL: " . $commitUrl . "\n";
        $response = Helpers::getCurlUrlData($commitUrl);
        echo 'status_code=' . $response['status_code'] . "\n";
    }

}