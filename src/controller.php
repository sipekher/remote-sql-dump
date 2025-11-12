<?php
// ----------------------------------------------------------------------------

function action($actionName) {

  try {
    $config = getConfigFromRequest();

    switch ($actionName) {

      case 'getTables' :
        $dumper = getDumper($config);
        response(array('tables' => actionGetTables($dumper)));
        exit;
        break;

      case 'dumpChunk':
        $dumper = getDumper($config);
        responseData(actionDumpChunk($dumper, $_REQUEST['table'], $_REQUEST['offset'], $_REQUEST['params']));
        exit;
        break;

      case 'dumpBeforeFirstData':
        $dumper = getDumper($config);
        response(actionDumpBeforeFirstData($dumper));
        exit;
        break;

      case 'dumpAfterLastData':
        $dumper = getDumper($config);
        response(actionDumpAfterLastData($dumper));
        exit;
        break;

      //case 'payment':
      //$this->response($this->payment($_REQUEST['office_id'], $_REQUEST['event_id'], json_decode(file_get_contents('php://input'), true)));
      //exit;

      default:
        htmlPage();
        exit;
    }
  } catch (Exception $exception) {
    responseException($exception->getMessage());
    exit;
  }
}

// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
function responseException($message, $httpCode = 500) {
  header("Content-type: application/json", true, $httpCode);
  header('Access-Control-Allow-Origin: *');
  print json_encode(array('status' => 'error', 'message' => mb_convert_encoding($message, 'UTF-8')));
}

// ----------------------------------------------------------------------------
function response($data) {

  try {

    header('Access-Control-Allow-Origin: *');
    header("Content-type: application/json");

    if (!is_array($data)) {
      $data = array('data' => $data);
    }
    if (!isset($data['status'])) {
      $data['status'] = 'ok';
    }
    print json_encode($data);
  } catch (Exception $exception) {
    header("Content-type: application/json", true, 500);
    header('Access-Control-Allow-Origin: *');
    print json_encode(array('status' => 'error', 'message' => $exception->getMessage()));
  }
}

// ----------------------------------------------------------------------------
function responseData($data) {

    header("Content-type: application/octet-stream", true, 200);
    
    print $data['offset']."\n".$data['sql'];
}
// ----------------------------------------------------------------------------
