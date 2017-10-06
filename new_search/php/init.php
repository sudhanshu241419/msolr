<?php
namespace SolrIndexing;

$base_path = dirname(__FILE__);
require_once $base_path . '/conf/config.php';

foreach (glob($base_path . "/classes/*.php") as $filename) {
    include_once $filename;
}

//Helpers::pr($argv, true);

if (count($argv) < 3 ) {
    Helpers::showUsageInfo();
    exit("\n\n");
} else {
    $context = ($argv[1] == 'useconfig') ? SOLR_CONTEXT : $argv[1];
    define('SOLR_URL', SOLR_HOST . $context . '/');
    define('SOLR_URL_HBREST', SOLR_URL . 'hbr/update?');
    define('SOLR_URL_HBMENU', SOLR_URL . 'hbm/update?');
    define('SOLR_URL_HBUSER', SOLR_URL . 'hbu/update?');
}

/**
 *
 * @param mixed $obj object to be printed
 * @param bool $exit defaults to false. If true exit the app.
 * @return NULL
 */
function pr($obj, $exit = false) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo "<pre>";
    echo "\n===== Called from " . $caller['file'] . " " . $caller['line'] . " =====\n\n";
    print_r($obj);
    echo "\n\n";
    if ($exit) {
        exit;
    }
}

/**
 *
 * @param mixed $obj object to be var_dump(ed)
 * @param bool $exit defaults to false. If true exit the app.
 * @return NULL
 */
function vd($obj, $exit = false) {
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo "<pre>";
    echo "\n===== Called from " . $caller['file'] . " " . $caller['line'] . " =====\n\n";
    var_dump($obj);
    echo "\n\n";
    if ($exit) {
        exit;
    }
}
