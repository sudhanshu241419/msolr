<?php
namespace SolrIndexing;

chdir(dirname(__FILE__) );
require_once('../init.php');

$index_what = Helpers::getIndexTypeWithData($argv);
$imd = new IndexMenuData();
$imd->checkCoreHealth();

switch ($index_what['type']) {
    case 'all':
        $imd->indexAllRestaurantsMenus();
        break;
    case 'cityids':
        $imd->indexCityMenus($index_what['ids']);
        break;
    case 'resids':
        $imd->indexMenusByResIds($index_what['ids']);
        break;
    case 'rescodes':
        $imd->indexMenusByResCodes($index_what['ids']); //e.g.RALACA57
        break;
    default:
        echo "\n\n======NOTHING TO DO========\n\n";
        break;
}

$imd->solrCommit();
