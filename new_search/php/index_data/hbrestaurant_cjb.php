<?php
namespace SolrIndexing;

chdir(dirname(__FILE__) );
require_once('../init.php');

$index_what = Helpers::getIndexTypeWithData($argv);

$show_progress = true;
$ird = new IndexRestaurantData($show_progress);
$ird->checkCoreHealth();


switch ($index_what['type']) {
    case 'all':
        $ird->indexAllRestaurants();
        break;
    case 'cityids':
        $ird->indexCityRestaurants($index_what['ids']);
        break;
    case 'resids':
        $ird->indexRestaurantsByIds($index_what['ids']);
        break;
    case 'rescodes':
        $ird->indexRestaurantsByCodes($index_what['ids']); //e.g.RALACA57
        break;
    default:
        echo "\n\n======NOTHING TO DO========\n\n";
        break;
}

$ird->solrCommit();
$ird->listBadData();
