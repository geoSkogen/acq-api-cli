<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/schema.php';


use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use GuzzleHttp\Client;

// CONSTANTS / FUNCTIONS :
//
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
     if (intval($site_arg) < 6 && intval($site_arg) > 0) {
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


function triage_rest_route($site_info,$base_resource,$route,$app_UUID,$env_id) {
  $resource = '';
  switch($base_resource) {
    case 'app' :
      $resource = 'applications/' . $app_UUID . '/';
      break;
    case 'env' :
      $resource = 'environments/' . $env_id .'-'. $app_UUID . '/';

      switch($route) {
        case 'backups' :
          $db_name = !empty($site_info["DATABASE_NAME"]) ? $site_info["DATABASE_NAME"] : '';
          $resource .= 'databases/' . $db_name . '/backups';
          error_log('backups case');
          if (!$db_name) { error_log('the database name is missing'); }
          break;
        default :
         $resource .= $route;
         error_log($route . ' case');
      }
      break;
    default :
      $resource = 'account';
  }
  return $resource;
}


function acquia_cloud_api_request($method,$resource) {
  $responseBody = '';
  // CREDs
  $clientId = '';
  $clientSecret = '';
  // API REQUEST BOILERPLATE
  $provider = new GenericProvider([
      'clientId'                => $clientId,
      'clientSecret'            => $clientSecret,
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

  $response_data = json_decode($responseBody,true);
  return $responseBody;
}
//
// BEGIN PROCEDURE :
//
// SORT SITE INFO
$site_info_by_name = assoc_rows_by_col_val('acsf-sites',1,3);
$site_info_by_priority = group_rows_by_col_val('acsf-sites-i',0);
// SET API CALL ARGS
$methods = ['GET','PUT','POST','PATCH','DELETE'];
$app_UUID = 'e72adc2f-0420-484a-bbb3-52e31a0b7448';
$env_ids = [
  'dev' => '2719',
  'test' => '2717',
  'live' => '2715'
];
// GET EXECUTION PARAMS FROM SHELL CALL
$method = ( !empty($argv[1]) && in_array($argv[1],$methods) ) ? $argv[1] : 'GET';
$base_resource = !empty($argv[2]) ? $argv[2] : '';
$env_slug = !empty($argv[3]) ? $argv[3] : '';
$env_id = !empty($env_ids[$env_slug]) ? $env_ids[$env_slug] : '' ;
$route = !empty($argv[4]) ?  $argv[4] : '';
$site_arg = !empty($argv[5]) ?  $argv[5] : '';
// SET TASK SCOPE - the $targets table
$targets = enqueue_task_targets($site_arg,$site_info_by_name,$site_info_by_priority);
//
error_log('target sites enqueued for ' . $route . ' task: ' . strval(count($targets)));
error_log(print_r($targets, true));
//
foreach($targets as $site_info) {
  // TRIAGE ROUTE- assemble the request URL
  $resource = triage_rest_route($site_info,$base_resource,$route,$app_UUID,$env_id);
  //
  error_log('API resource: ' . $resource);
  //
  $response_json = acquia_cloud_api_request($method,$resource);
  /*
  foreach($response_data['_embedded']['items'] as $resp_row) {
    error_log('\r\nresponse row object id:');
    error_log(print_r($resp_row['id']));
    error_log('\r\nresponse row object completion time:');
    error_log(print_r($resp_row['completed_at']));
  }
  */
}
