<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/schema.php';

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use GuzzleHttp\Client;

// CONSTANTS  & FUNCTIONS:

define('CLIENT_ID','');
define('CLIENT_SECRET','');
define('APP_UUID','');

function assoc_rows_by_col_val($data_file,$col_val_index,$col_compare_index) {
  $schema_a = new Schema($data_file,'../exports');
  $row_headers_a = $schema_a->data_index[0];

  for ($i = 1; $i < count($schema_a->data_index); $i++) {
    $row = [];
    if (!empty($schema_a->data_index[$i][$col_val_index])) {

      if (strpos($schema_a->data_index[$i][$col_compare_index],'test-')) {
        $prop_str = 'test-' . $schema_a->data_index[$i][$col_val_index];
      } else {
        $prop_str = $schema_a->data_index[$i][1];
      }
    } else {
      $prop_str = 'not_set_' . strval($i);
    }

    for ($ii = 0; $ii < count($row_headers_a); $ii++) {
      $row[$row_headers_a[$ii]] = $schema_a->data_index[$i][$ii];
    }
    $site_info_by_name[$prop_str] = $row;
  }
  return $site_info_by_name;
}


function group_rows_by_col_val($data_file,$col_index) {
  $schema_b = new Schema($data_file,'../exports');
  $row_headers_b = $schema_b->data_index[0];

  for ($i = 1; $i < count($schema_b->data_index); $i++) {
    $row = [];
    $prop_index = !empty($schema_b->data_index[$i][$col_index]) && intval($schema_b->data_index[$i][$col_index]) ?
      intval($schema_b->data_index[$i][$col_index]) : 'not_set_' . strval($i);
    for ($ii = 0; $ii < count($row_headers_b); $ii++) {
      $row[$row_headers_b[$ii]] = $schema_b->data_index[$i][$ii];
    }
    if (!empty($site_info_by_priority[$prop_index])) {
      $site_info_by_priority[$prop_index][] = $row;
    } else {
      $site_info_by_priority[$prop_index] = [$row];
    }
  }
  return $site_info_by_priority;
}


function enqueue_task_targets($site_arg,$site_info_by_name,$site_info_by_priority) {
  $targets = [];
  if (intval($site_arg)) {
     if (intval($site_arg) < 7 && intval($site_arg) > 0) {
       // enqueue a list of site info by priority level
       $targets = $site_info_by_priority[ intval($site_arg) ];
     } else {
       error_log('priority level: ' . $site_arg . ' is out of range');
     }
  } else if (strpos($site_arg,',')) {
    // enqueue a list of sites
    $site_names_arr = explode(',', $site_arg);

    foreach($site_names_arr as $site_name) {

      if (!empty($site_info_by_name[ $site_name ])) {
        // get their info onto a list
        $targets[] = $site_info_by_name[ $site_name ];
      } else {
        error_log('site name: ' . $site_name . ' not found');
      }
    }
  } else {
    // enqueue single site
    $site_info = !empty( $site_info_by_name[ $site_arg])  ?
     $site_info_by_name[ $site_arg ] : '';
     $targets = [ $site_info ];
  }
  return $targets;
}


function get_backup_id($resource,$db_name,$newest,$ondemand) {
  $backup_id = '';
  $log_item = $newest ? 'newest' : 'oldest';
  $log_item .= $ondemand ? ' ondemand' : '';
  error_log('making request for ' . $log_item . ' backup ID of db ' . $db_name);
  // make an initial call to get the most recent database backup id
  $response_json = acquia_cloud_api_request('GET',$resource . 'databases/' . $db_name . '/backups');
  $response_data = json_decode($response_json,true);

  if ($ondemand) {
    if ($newest) {
      for ($i = 0; $i < count($response_data['_embedded']['items']); $i++) {
        if ($response_data['_embedded']['items'][$i]['type']==='ondemand') {
          $backup_id = $response_data['_embedded']['items'][$i]['id'];
          error_log('backup ' . $backup_id . ' was made ' . $response_data['_embedded']['items'][$i]['completed_at']);
          break;
        }
      }
    } else {
      for ($i = count($response_data['_embedded']['items'])-1; $i > -1; $i--) {
        if ($response_data['_embedded']['items'][$i]['type']==='ondemand') {
          $backup_id = $response_data['_embedded']['items'][$i]['id'];
          error_log('backup ' . $backup_id . ' was made ' . $response_data['_embedded']['items'][$i]['completed_at']);
          break;
        }
      }
    }
  } else {
    $index = $newest ? 0 : count($response_data['_embedded']['items'])-1;
    $backup_id = ( !empty($response_data['_embedded']['items'][$index]) &&
      !empty($response_data['_embedded']['items'][$index]['id']) ) ?
      $response_data['_embedded']['items'][$index]['id'] : '';
    error_log('backup ' . $backup_id . ' was made ' . $response_data['_embedded']['items'][$index]['completed_at']);
  }
  return $backup_id;
}


function triage_backup_option($option,$resource,$db_name, $site_name) {
  global $registry_obj;
  $backup_id = '';
  error_log('backup options: making request for ' . $option . ' db backup ID');

  if ($option) {
    switch($option) {
      case 'oldest' :
        $backup_id = get_backup_id($resource,$db_name,false,false);
      break;
      case 'newest' :
        $backup_id = get_backup_id($resource,$db_name,true,false);
      break;
      case 'oldest-ondemand' :
        $backup_id = get_backup_id($resource,$db_name,false,true);
      break;
      case 'newest-ondemand' :
        $backup_id = get_backup_id($resource,$db_name,true,true);
      break;
      case 'from-register' :
        $backup_id = (!empty($registry_obj[ $site_name ]) &&
          intval($registry_obj[ $site_name ]) ) ?
          $registry_obj[ $site_name ] : '';
      break;
      default :
        if (intval($option)) {
          $backup_id = $option;
        } else {
          error_log('backup option ' . $option . 'is not executable');
        }
    }
  }
  return $backup_id;
}


function triage_rest_route($site_info,$base_resource,$route,$env_id,$option) {
  global $registry_table;
  global $registry_obj;
  $resource = '';
  //
  switch($base_resource) {

    case 'app' :
      $resource = 'applications/' . APP_UUID . '/';
      break;

    case 'env' :
      $resource = 'environments/' . $env_id .'-'. APP_UUID . '/';

      switch($route) {

        case 'databases' :
          $resource .= 'databases';
          error_log('databases case');
          if (is_array($site_info) && count(array_keys($site_info))) {
            $db_name = !empty($site_info["DATABASE_NAME"]) ? $site_info["DATABASE_NAME"] : '';
            $resource .= ($db_name) ? '/' . $db_name : '';
          }
          break;

        case 'backups' :
          $db_name = !empty($site_info["DATABASE_NAME"]) ? $site_info["DATABASE_NAME"] : '';
          $site_name = !empty($site_info["SITE_NAME"]) ? $site_info["SITE_NAME"] : '';
          error_log('backups case');

          $this_id = triage_backup_option($option,$resource,$db_name,$site_name);

          $resource .= 'databases/' . $db_name . '/backups';
          $resource .= $this_id ? '/' . $this_id : '';

          if ($db_name) {
            $log_msg = 'requesting db ' . $db_name .  ' backup';
            $log_msg .= $this_id ? ' ' . $this_id : 's';
            error_log($log_msg);
            sleep(2);
          } else {
            $resource = '';
            error_log('the database name is missing for the requested site');
            error_log('API resource will return null and request will terminate');
          }
          break;

        case 'restore' :
          $db_name = !empty($site_info["DATABASE_NAME"]) ? $site_info["DATABASE_NAME"] : '';
          $site_name = !empty($site_info["SITE_NAME"]) ? $site_info["SITE_NAME"] : '';
          error_log('restore case - making request for ' . $option . ' db backup ID');

          $backup_id = triage_backup_option($option,$resource,$db_name,$site_name);

          if ($backup_id) {
            $resource .= 'databases/' . $db_name . '/backups/' . $backup_id . '/actions/restore';
            error_log('databse backup# '. $backup_id . ' restore request to db ' . $db_name . ' for ' . $site_name);
            error_log($resource);
            //$resource = '';
            sleep(2);
          } else {
            $resource = '';
            error_log('requested databse backup was not found');
            error_log('API resource will return null and request will terminate');
          }
          break;

        default :
         $resource .= $route;
         error_log($route . ' case has not been validated');
      }
      break;
    default :
      $resource = '/';
  }
  if (strpos($resource,'//')) {
    $resource = '/';
  }
  return $resource;
}


function acquia_cloud_api_request($method,$resource) {
  $responseBody = '';
  // API REQUEST BOILERPLATE - from Acquia Cloud Documentation
  // Bearer Token Authentication via OAuth
  $provider = new GenericProvider([
      'clientId'                => CLIENT_ID,
      'clientSecret'            => CLIENT_SECRET,
      'urlAuthorize'            => '',
      'urlAccessToken'          => 'https://accounts.acquia.com/api/auth/oauth/token',
      'urlResourceOwnerDetails' => '',
  ]);

  try {
      $accessToken = $provider->getAccessToken('client_credentials');

      $request = $provider->getAuthenticatedRequest(
          $method,
          "https://cloud.acquia.com/api/{$resource}",
          $accessToken
      );

      $client = new Client();
      $response = $client->send($request);

      $responseBody = $response->getBody();

      error_log('raw response JSON: ' . $responseBody);

  } catch (IdentityProviderException $e) {
      // Failed to get the access token.
      exit($e->getMessage());
  }
  return $responseBody;
}
// BEGIN PROCEDURE :
// SORT SITE INFO
$site_info_by_name = assoc_rows_by_col_val('acsf-sites',1,3);
$site_info_by_priority = group_rows_by_col_val('acsf-sites-i',0);
$registry_obj = [];
$registry_table = [];
// API CALL ARGS
$methods = ['GET','PUT','POST','PATCH','DELETE'];
$env_ids = [
  'dev' => '2719',
  'test' => '2717',
  'live' => '2715'
];
$wait_seconds = 5;
// GET EXECUTION PARAMS FROM php SHELL CALL
$method = ( !empty($argv[1]) && in_array($argv[1],$methods) ) ? $argv[1] : 'GET';
$base_resource = !empty($argv[2]) ? $argv[2] : '';
$env_slug = !empty($argv[3]) ? $argv[3] : '';
$env_id = !empty($env_ids[$env_slug]) ? $env_ids[$env_slug] : '' ;
$route = !empty($argv[4]) ?  $argv[4] : '';
$site_arg = !empty($argv[5]) ?  $argv[5] : '';
$option = !empty($argv[6]) ?  $argv[6] : '';
$directive = !empty($argv[7]) ?  $argv[7] : '';

switch($option) {
  case 'from-register' :
    $filename = $route . '-ids-' . $site_arg;
    $registry_schema = new Schema($filename,'../data');
    foreach( $registry_schema->data_index as $row_arr ) {
      $registry_obj[$row_arr[0]] = $row_arr[1];
    }
    error_log('creating registry object');
    print_r($registry_obj,true);
  break;
  default :
}
// SET TASK SCOPE - the targets table
$targets = enqueue_task_targets($site_arg,$site_info_by_name,$site_info_by_priority);
//
error_log('target sites enqueued for ' . $route . ' task: ' . strval(count($targets)));
foreach($targets as $site_info_row) {
  if (!empty($site_info_row['SITE_NAME'])) {
    error_log($site_info_row['SITE_NAME']);
  }
}
// MAKE THE API REQUEST(S)
foreach($targets as $site_info) {
  // TRIAGE ROUTE- assemble the request URL
  $resource = triage_rest_route($site_info,$base_resource,$route,$env_id,$option);

  if ($resource) {
    error_log('calling API resource: ' . $resource);
    $resource = $resource==='/' ? '' : $resource;

    $response_json = acquia_cloud_api_request($method,$resource);

    $response_data = json_decode($response_json,true);

    switch($directive) {
      case 'register' : {
        $registry_table[] = [ $site_info['SITE_NAME'],$response_data['id'] ];
      }
      default :
    }
    sleep($wait_seconds);
  } else {
    error_log('the resource returned null; crucial site info not found');
  }
}

if (count($registry_table)) {
  $export_str = Schema::make_export_str($registry_table);
  $filename = $route . '-ids-' . str_replace(',','_',$site_arg);
  Schema::export_csv($export_str, $filename, '../data' );
}
