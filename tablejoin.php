<?php

require 'includes/schema.php';

$new_table = [];

$site_schema = new Schema('acsf-sites','../imports');
$site_table = $site_schema->data_index;
$priority_schema = new Schema('priority','../imports');
$priority_table = $priority_schema->data_index;


// index by ID
$priority_obj = [];
$export_table = [
  ['SITE ID', 'SITE NAME', 'DATABASE NAME', 'DOMAIN', 'PRIORITY']
];

foreach($priority_table as $priority_row) {
  $priority_obj[$priority_row[0]] = $priority_row[2];
}

for ($i = 0; $i < count($site_table); $i++) {
  $this_row = preg_split( '/[\s]+/', $site_table[$i][0] );
  $this_priority = ( !empty($this_row[3]) && !empty($priority_obj[$this_row[3]]) ) ?
    $priority_obj[$this_row[3]] : '(not set)';
  $this_row[] = $this_priority;
  $export_table[] = $this_row;
}

$export_str = Schema::make_export_str($export_table);
Schema::export_csv($export_str, 'acsf-sites-i', '../exports');

?>
