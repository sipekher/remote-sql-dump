<?php

// ----------------------------------------------------------------------------

function getConfigFromRequest() {
/*
    $config = array(
        'host' => 'localhost',
        'username' => 'root',
        'password' => 'triadpass',
        'db_name' => 'todolist_nette',
        //'include_tables' => array('country', 'city'), // only include those tables
        //'exclude_tables' => array('city'),
        'time_limit' => 30,
        'charset' => 'utf8',
    );
 */
    $config = array(
        'host' => isset($_REQUEST['config']['host']) ? $_REQUEST['config']['host'] : null,
        'username' => isset($_REQUEST['config']['username']) ? $_REQUEST['config']['username'] : null,
        'password' => isset($_REQUEST['config']['password']) ? $_REQUEST['config']['password'] : null,
        'database' => isset($_REQUEST['config']['database']) ? $_REQUEST['config']['database'] : null,
        //'include_tables' => array('country', 'city'), // only include those tables
        //'exclude_tables' => array('city'),
        'time_limit' => 30,
        'charset' => 'utf8',
        'filename' => isset($_REQUEST['config']['filename']) && $_REQUEST['config']['filename'] ? $_REQUEST['config']['filename'] : null,
  );
  return $config;
}
// ----------------------------------------------------------------------------

/**
 * 
 * @param type $config
 * @return Shuttle_Dumper
 */
function getDumper($config) {
  $dumper = Shuttle_Dumper::create($config, isset($config['filename']) ? $config['filename'] : null);

  return $dumper;
}

// ----------------------------------------------------------------------------

function actionGetTables($dumper) {
  $tables = $dumper->get_tables('');
  return $tables;
}

// ----------------------------------------------------------------------------

function actionDumpBeforeFirstData($dumper) {
  return $dumper->dumpBeforeFirstData();
}

// ----------------------------------------------------------------------------

function actionDumpAfterLastData($dumper) {
  return $dumper->dumpAfterLastData();
}

// ----------------------------------------------------------------------------

function actionDumpChunk($dumper, $table, $offset, $params) {
  $data = $dumper->dump_table(
      $table
      , $offset
      , isset($params['maxRows']) ? $params['maxRows'] : null
      , isset($params['exportStructure']) ? $params['exportStructure'] : true
      , isset($params['exportDropStatement']) ? $params['exportDropStatement'] : true
      , isset($params['exportData']) ? $params['exportData'] : true
  );
  return $data;
}

// ----------------------------------------------------------------------------
function cliGetHTTPContext() {
    return array(
        "http" => array(
            "method" => "POST",
            "header" =>
            "Content-Type: application/xml; charset=utf-8;\r\n" .
            "Connection: close\r\n",
            "ignore_errors" => true,
            "timeout" => (float) 300.0,
        ),
        "ssl" => array(
            "allow_self_signed" => true,
            "verify_peer" => false,
        ),
    );
}

// ----------------------------------------------------------------------------

function cliGetTables($options) {

    $query = $options['url'] . '?' . http_build_query(array('config' => $options, 'action' => 'getTables'));
    $response = file_get_contents($query, false, stream_context_create(cliGetHTTPContext()));

    return json_decode($response, true);
}

// ----------------------------------------------------------------------------

function cliDumpChunk($options, $table, $offset) {

    $params = array(
        'config' => $options,
        'action' => 'dumpChunk',
        'table' => $table,
        'offset' => $offset,
        'params' => array(
            'maxRows' => null, //1
            'exportStructure' => true,
            'exportDropStatement' => true,
            'exportData' => true,
        ),
    );

    $query = $options['url'] . '?' . http_build_query($params);
    $response = file_get_contents($query, false, stream_context_create(cliGetHTTPContext()));

    return $response;
}
// ----------------------------------------------------------------------------


function cliHelp(){
  print "Usage: php remote.sql.dump.php --url <URL> --host <HOST> --username <USERNAME> --password <PASSWORD> --database <DATABASE> [--list_tables]

Description:
    This script is designed primarily for large MySQL/MAria databases where tools 
    like phpMyAdmin may fail due to time limits during export or import operations. 
    The script connects to a MySQL database and performs database dump/export 
    of DB structure and data. It requires the URL to the remote SQL dump script
    and connection details to the MySQL database.

    **Important:** The `remote.sql.dump.php` script must be present both 
    on the client side and uploaded to the server in the directory specified 
    by the `--url` parameter. If it is used in command line.
    
    If it is used as GUI in browser, just visit URL https://example.com/remote.sql.dump.php
    and fill in all required paramater on the web page and start export from browser.

Required Parameters (for `cli` version):
    --url <URL>               The URL to the remote SQL dump. Example: https://example.com/remote.sql.dump.php
    --host <HOST>             The hostname or IP address of the database server. Example: localhost
    --username <USERNAME>     The username to connect to the MySQL database. Example: root
    --password <PASSWORD>     The password to connect to the MySQL database. Example: triadpass
    --database <DATABASE>     The name of the database to connect to. Example: todolist_nette

Optional Parameters:
    --list_tables             Lists all tables in the specified database without performing any dump operation.

Examples:
    1. To perform a dump operation with all required parameters:
       php remote.sql.dump.php --url https://example.com/remote.sql.dump.php --host localhost --username your_database_user --password SECRETPASS --database YOUR_DB_NAME

    2. To list all tables in the specified database:
       php remote.sql.dump.php --url https://example.com/remote.sql.dump.php --host localhost --username your_database_user --password SECRETPASS --database YOUR_DB_NAME --list_tables

Notes:
    - This script is particularly useful for handling large database exports where phpMyAdmin or similar tools may encounter timeouts.
    - Ensure that the `remote.sql.dump.php` script is not only available on the client side but also uploaded to the server in the directory specified by the `--url` parameter.
    - All required parameters must be provided when running the script.
    - The `--list_tables` parameter is optional and can be used to view the tables in the database without performing a dump.

    ";
}

function cliAction($options){
      
    if(!isset($options['url']) || !isset($options['host']) || !isset($options['username']) || !isset($options['password']) || !isset($options['database'])){
        cliHelp();
    }elseif (isset($options['list_tables'])) {

        $data = cliGetTables($options);

        if ($data['status'] == 'ok') {
            foreach ($data['tables'] as $r)
                print $r['table_name'] . ' (' . $r['table_rows'] . ' rows)' . "\n";
        }
    } else {

        $data = cliGetTables($options);

        if ($data['status'] == 'ok') {
            foreach ($data['tables'] as $r) {
                $offset = 0;
                while ($offset !== null) {
                    $data = cliDumpChunk($options, $r['table_name'], $offset);

                    // first line contains offset position
                    $firstNewlinePos = strpos($data, "\n");

                    if ($firstNewlinePos !== false) {
                        // Extract everything after the first newline character
                        $firstLine = substr($data, 0, $firstNewlinePos);
                        print substr($data, $firstNewlinePos + 1);
                        $offset = (int) $firstLine;
                        $offset = ($offset === 0) ? null : $offset;
                    } else {
                        $offset = null;
                    }
                }
            }
        }
    }
}
// ----------------------------------------------------------------------------
