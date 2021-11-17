<?php

require 'includes/schema.php';

$site_schema = new Schema('acsf-sites','../exports');
$site_table = $site_schema->data_index;
$priority_assoc = [];
$export_table = [];

foreach($site_table as $site_row) {
  $this_priority = intval($site_row[4]) ?
    intval($site_row[4]) : $site_row[4];
  if (!empty($priority_assoc[$this_priority])) {
    $priority_assoc[$this_priority][] = $site_row;
  } else {
    $priority_assoc[$this_priority] = [$site_row];
  }
}
ksort($priority_assoc);
foreach ($priority_assoc as $key => $arrs) {
  foreach( $arrs as $arr) {
    $export_table[] = array_merge([$key],$arr);
  }
}

$export_str = Schema::make_export_str($export_table);
Schema::export_csv($export_str, 'acsf-sites-i', '../exports');

?>
