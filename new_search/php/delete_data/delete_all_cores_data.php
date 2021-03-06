<?php
namespace SolrIndexing;

chdir(dirname(__FILE__) );
require_once('../init.php');

$delete_what = Helpers::getIndexTypeWithData($argv);

$dd = new DeleteData();

$dd->deleteFromMenu($delete_what);
$dd->commitOnMenuCore();

$dd->deleteFromRestaurant($delete_what);
$dd->commitOnRestaurantCore();

$dd->deleteFromUser($delete_what);
$dd->commitOnUserCore();