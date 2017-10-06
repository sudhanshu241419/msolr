<?php

namespace SolrIndexing;

chdir(dirname(__FILE__));
require_once('../../php/conf/config.php');

$mysqli = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
/* check connection */
if ($mysqli->connect_errno) {
    printf("Connect failed: %s\n", $mysqli->connect_error);
    return NULL;
}

$query = "ALTER DATABASE " . DB_NAME . " CHARACTER SET utf8 COLLATE utf8_unicode_ci";

$srResult = $mysqli->query($query);
var_dump($srResult);

$tables_q = "SHOW TABLES";

/* @var mysqli_result $tables_result */
$tables_result = $mysqli->query($tables_q);

if ($tables_result->num_rows) {
    while ($row = $tables_result->fetch_array()) {
        echo "\naltering table : ". $row[0];
        $q = "ALTER TABLE ".$row[0]." CONVERT TO CHARACTER SET utf8  COLLATE utf8_unicode_ci";
        echo ", Query Response: ". $mysqli->query($q) . "\n";
    }
    $tables_result->free();
}
$mysqli->close();
