msolr
=====
How to index data:

1. use the branch hb_operating_hours for current dev status.
2. msolr_dev can be treated as latest stable version for qa purpose.

Config file:

1. Copy new_search/php/conf/config-sample.php and save it as config.php in the same directory.
2. Edit config.php and make appropriate changes.

Java Lib:
New code requires jts-1.13.jar (within new_search/repo/). Either add this jar into existing webapp's lib directory (WEB-INF/lib) or use the repacked war (new_search/repo/solr421.war)
