<?php
namespace SolrIndexing;

chdir(dirname(__FILE__) );
require_once('../init.php');

$index_what = Helpers::getIndexTypeWithData($argv);

$iu = new IndexUser();
$iu->checkCoreHealth();


switch ($index_what['type']) {
    case 'all':
        $iu->indexAllUsers();
        break;
    default:
        echo "\n\n======NOTHING TO DO========\n\n";
        break;
}

$iu->solrCommit();
$iu->listBadData();
