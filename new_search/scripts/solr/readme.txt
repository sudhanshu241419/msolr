How to for using scripts in this folder:

============================= INSTALL SOLR AS A SERVICE ========================
1. solr5-install-service-bash.sh

Install solr as a service. Script takes one additional argument which has 2 
possible values, hsearch5 or msearch5
a.) hsearch5 installs solr as a service named "hsearch5" on port 8993.
b.) msearch5 installs solr as a service named "msearch5" on port 8994.

Ex. 
$ sudo bash  solr5-install-service-bash.sh hsearch5


============================= REMOVE SOLR AS A SERVICE =========================
2. solr5-remove-service-bash.sh
Removes a solr service installed by using 1. 
Pass the solr service name as an argument

Ex.
$ sudo bash  solr5-remove-service-bash.sh hsearch5


============================= UPDATE SOLR WITH LATEST GIT CODE =================
3. SCRIPT solr5-update-solrhome-bash.bash
Use it to update solr schemas and config files.

Ex. To update schemas and configs for hsearch5 solr service:
$ sudo bash solr5-update-solrhome-bash.bash hsearch5