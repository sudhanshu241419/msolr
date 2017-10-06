<?php
namespace SolrIndexing;

chdir(dirname(__FILE__) );
require_once('../init.php');

$delete_what = Helpers::getIndexTypeWithData($argv);
$dd = new DeleteData();
$dd->deleteFromUser($delete_what);
$dd->commitOnUserCore();
//$dd->close();