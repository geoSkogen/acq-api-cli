
# php oauth.php GET env live databases


result=($(drush -r /var/www/pdxd8/docroot --uri=http://ondeck st | grep -E "Database name|DB name|Site path"))
