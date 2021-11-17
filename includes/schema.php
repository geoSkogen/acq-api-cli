<?php

class Schema {

  public $data_index = array();
  public $data_assoc = array();
  public $labeled_columns = array();
  public $labeled_rows = array();

  function __construct($filename,$path) {
    $this->data_index = $this->import_csv_index($filename,$path);
    //$this->data_assoc = $this->make_assoc($filename, $path);
  }

  public function import_csv_index($filename, $path) {
    $result = array();
    if (($handle = fopen(__DIR__ . "/" . $path . "/" . $filename . ".csv", "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $result[] = $data;
      }
      fclose($handle);
    //  error_log("Lookup found " . strval(sizeof($result)) . " rows of data");
      return $result;
    } else {
      error_log('could not open file');
      return false;
    }
  }

  public static function make_assoc($data_arr,$bool) {
    /* bool > false - assumes columns are labeled, returns indexed associative rows */
    /* bool > true - assumes columns and rows are labeled, returns 2D associative array */
    $result = array();
    $keys = $data_arr[0];
    $is_table = ($bool) ? 1 : 0;
    for ($i = 1; 1 < count($data_arr); $i++) {
      $row = array();
      for ($col_index = $is_table; $col_index < count($data_arr[$i]); $col_index++) {
        $row[$keys[$col_index]] = $data_arr[$i][$col_index];
      }
      $row_key = ($is_table) ? $data_arr[$i][0] : $i;
      $result[$row_key] = $row;
    }
    return $result;
  }

  public static function get_labeled_columns($data_arr) {
    $keys = [];
    $result = array();
    for ($row_index = 0; $row_index < count($data_arr); $row_index++) {
      for ($i = 0; $i < count($data_arr[$row_index]); $i++) {
        if ($row_index === 0) {
          $result[strval($data_arr[$row_index][$i])] = array();
          array_push($keys, $data_arr[$row_index][$i]);
        } else {
          if ($data_arr[$row_index][$i]) {
            array_push($result[$keys[$i]],$data_arr[$row_index][$i]);
          }
        }
      }
    }
    return $result;
  }

  public static function get_labeled_rows($data_arr) {
    $key = "";
    $valid_data = [];
    $result = array();
    foreach ($data_arr as $row) {
      $key = $row[0];
      $valid_data = array_slice($row,1);
      $result[$key] = $valid_data;
    }
    return $result;
  }

  public function table_lookup($col, $row) {
    $result = false;
    if ( ($col || $col === 0) && ($row || $row === 0) ){
      if ($this->data_index[$row][$col]) {
        $result = $this->data_index[$row][$col];
      }
    }
    return $result;
  }

  public static function make_export_str($data_table) {
    $export_str = "";
    $staging_str = "";
    foreach ($data_table as $data_row) {
      if (is_array($data_row)) {
        for ($i = 0; $i < count($data_row); $i++) {
          if (is_array($data_row[$i])) {
            $staging_str = implode(',',$data_row[$i]);
          } else {
            $staging_str = $data_row[$i];
          }
          $export_str .= '"' . $staging_str . '"';
          $export_str .= ($i === count($data_row)-1) ? "\r\n" : ",";
        }
      } else {
        $export_str .= '"' . $data_row . '"' . "\r\n";
      }
    }
    return $export_str;
  }

  public static function export_csv($export_str, $filename, $dir_path) {
    file_put_contents(__DIR__ . '/'.  $dir_path . "/" . $filename . ".csv" , $export_str);
  }
}
