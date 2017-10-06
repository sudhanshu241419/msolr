<?php
namespace SolrIndexing;
chdir(dirname(__FILE__) );
require_once('../init.php');

$cmsindexing = new CmsIndexing();
echo "\n========= SOLR: DELETING CLOSED RESTAURANTS ================";
$cmsindexing->deleteClosedRestaurantsAndMenusData();
echo "\n\n========= SOLR: INDEXING UPDATED/NEW RESTAURANTS ================";
$info = $cmsindexing->indexRestaurantsAndMenus();
//print_r($info);