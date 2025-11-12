<?php
function htmlPage() {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote SQL Dump</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        body {
            background-color: #f0f2f5; 
            display: flex;
            align-items: center;     
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .container {
            max-width: 600px;
            width: 100%;
            margin: 1rem;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
            padding: 2.5rem;
        }
        .form-label { font-weight: 500; }
        #databaseTables td { text-align:right; }

        #tablesList {
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            padding: 1rem;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="text-center mb-4">
            <i class="fas fa-database fa-2x text-primary mb-3"></i>
            <h1 class="h3 mb-1 fw-normal">Remote SQL Dump</h1>
            <p class="text-muted">Securely connect, select tables, and export your data <small>(even from big DB)</small></p>
        </div>

        <form id="mysqlForm">
            
            <h2 class="h5 fw-semibold mb-3">Connection Parameters</h2>
            
            <div class="mb-3">
                <label for="host" class="form-label">Server</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-server fa-fw"></i></span>
                    <input type="text" class="form-control" id="host" name="host" placeholder="e.g., localhost or 127.0.0.1 or mysql.example.com" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="e.g., root or user1234" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key fa-fw"></i></span>
                    <input type="password" class="form-control" id="password" name="password" value="triadpass">
                </div>
            </div>

            <div class="mb-3">
                <label for="database" class="form-label">Database</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-database fa-fw"></i></span>
                    <input type="text" class="form-control" id="database" name="database" placeholder="e.g., my_app_db" required>
                </div>
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <button type="button" class="btn btn-primary btn-lg" id="loadTables">
                    <i class="fas fa-plug me-2"></i> Connect & Load Tables
                </button>
                <button type="button" class="btn btn-secondary btn-lg disabled" id="startDump">
                    <i class="fas fa-download me-2"></i> Start Dump
                </button>
            </div>
            
        </form>

        <div id="response" class="d-none mt-4">
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <div>
                    <strong id="response-title">Status:</strong>
                    <span id="response-progress-text">Processing...</span>
                </div>
            </div>
        </div>
        
        <div id="tableListControls" class="d-none mt-4">
            <h3 class="h5 fw-semibold mb-3">Select Tables</h3>
            <div class="btn-group w-100" role="group" aria-label="Table selection controls">
                <a href="#" class="btn btn-outline-secondary" onclick="$('.dump_table_list').prop('checked', true); globalTableList.forEach((elem)=>{elem.isChecked = true; return elem;}); return false;">
                    <i class="far fa-check-square me-1"></i> Select All
                </a>
                <a href="#" class="btn btn-outline-secondary" onclick="$('.dump_table_list').prop('checked', false); globalTableList.forEach((elem)=>{elem.isChecked = false; return elem;}); return false;">
                    <i class="far fa-square me-1"></i> Deselect All
                </a>
                <a href="#" class="btn btn-outline-secondary" onclick="$('.dump_table_list').each(function() { $(this).prop('checked', !$(this).prop('checked')); }); globalTableList.forEach((elem)=>{elem.isChecked = !elem.isChecked; return elem;}); return false;">
                    <i class="fas fa-exchange-alt me-1"></i> Invert
                </a>
            </div>
        </div>

        <div id="tablesList" class="mt-3">
            </div>

    </div>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/web-streams-polyfill@2.0.2/dist/ponyfill.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/streamsaver@2.0.3/StreamSaver.min.js"></script>

          <script>
            
            function sanitizeFilename(filename) {
              // Remove leading and trailing whitespaces
              filename = filename.trim();

              // Replace invalid characters with underscores
              filename = filename.replace(/[\\/:\*\?"<>\|]/g, '_');

              // Replace multiple consecutive underscores with a single underscore
              filename = filename.replace(/_+/g, '_');

              // Remove trailing dots (.)
              filename = filename.replace(/\.*$/, '');

              // Limit filename length to 255 characters
              filename = filename.slice(0, 255);

              return filename;
            }

            function getDBconfig(){
                  var host = $("#host").val();
                  var username = $("#username").val();
                  var password = $("#password").val();
                  var database = $("#database").val();
                  var filename = $("#filename").val() ? $("#filename").val() : '';
                    
                  return {host, username, password, database, filename};
            }
            
            function showStatusMessage(message, textClass){
              $('#response-progress-text').html('<span class="text-' + textClass + '">' + message + '</span>');
              $('#response').addClass('d-block').removeClass('d-none');
            }    
                    
            let globalTableList = [];
            
            $(document).ready(function () {
              $("#loadTables").click(function () {
                showStatusMessage('', 'body');
                let dbConfig = getDBconfig();
                console.log('dbConfig', dbConfig);
                if(dbConfig.host === ''){
                    showStatusMessage('Host (server name) is required to connect to the database.', 'danger');
                    return;
                }
                if(dbConfig.username === ''){
                    showStatusMessage('Username is required to connect to the database.', 'danger');
                    return;
                }
                if(dbConfig.password === ''){
                    showStatusMessage('Password is required to connect to the database.', 'danger');
                    return;
                }
                if(dbConfig.database === ''){
                    showStatusMessage('Database name is required to connect to the database.', 'danger');
                    return;
                }
                
                $.ajax({
                  type: "POST",
                  url: "?action=getTables",
                  data: {config: dbConfig},
                  dataType: 'json',
                  success: function (response) {
                    //var data = JSON.parse(response);
                    var tables = response.tables;
                    globalTableList = [];
                    var tablesList = '<table id="databaseTables" class="table table-striped table-bordered table-sm"><tr><th></th><th></th><th>table name</th><th>rows</th><th>size (bytes)</th></tr>';
                    tables.forEach(function (table, index) {
                      tablesList += '<tr>';
                      tablesList += '<td>' + (index+1) + '</td>';
                      tablesList += '<th><input onchange="globalTableList[' + globalTableList.length + '].isChecked = this.checked" checked type="checkbox" class="dump_table_list" value="' + table.table_name + '"></th>';
                      tablesList += '<th>' + table.table_name + '<br><span id="tableProgress' + index + '" style="font-weight:normal; font-size:80%;"></span></th>';
                      tablesList += '<td>' + table.table_rows + '</td>';
                      tablesList += '<td>' + (Math.round(table.data_length)) + '</td>';
                      tablesList += '</tr>';
                      table.isChecked = true;
                      table.index = index;
                      globalTableList.push(table);
                      });
                    tablesList += '</table>';
                    $("#tablesList").html("<h3>Select tables to dump </h3>" + tablesList);
                    
                    $("#loadTables").addClass('btn-secondary').removeClass('btn-primary');
                    $("#startDump").addClass('btn-primary').removeClass('btn-secondary').removeClass('disabled');
                    
                    
                    $("#tableListControls").addClass('d-inline').removeClass('d-none');
                  },
                  error: function (xhr, status, error) {
                        var errorMessage = xhr.responseJSON.message;
                        console.log(errorMessage);
                        console.log(xhr, status, error);
                        
                        showStatusMessage((errorMessage ? errorMessage : error), 'danger');
                        }
                    });
              });
              
            async function downloadAllTables(writer){
              for (const table of globalTableList) {
                          console.log('downloadAllTables', table);
                          if(table.isChecked){
                              await downloadTableContent(table.table_name, table.table_rows, writer, table.index);
                          }
              }
            }  
            
            $("#startDump").click(function () {
                    showStatusMessage('', 'body');

                    if(globalTableList.length == 0){
                      alert('First load and select tables to dump!');
                      return;
                    }
                    const dbConfig = getDBconfig();
                    let writer;
                    //output to browser
                    if(dbConfig.filename === ''){
                      const streamSaver = window.streamSaver;
                      // streamSaver.createWriteStream() returns a writable byte stream
                      // The WritableStream only accepts Uint8Array chunks
                      // (no other typed arrays, arrayBuffers or strings are allowed)
                      const fileStream = streamSaver.createWriteStream(sanitizeFilename(dbConfig.database + '.sql'), {
                        //size: uInt8.byteLength, // (optional filesize) Will show progress
                        writableStrategy: undefined, // (optional)
                        readableStrategy: undefined  // (optional)
                      });
                      writer = fileStream.getWriter();
                      console.log('fileStream.getWriter()', writer);                      
                    }else{
                      writer = null;
                    }
                    
                    downloadAllTables(writer)
                      .then(() => {
                        console.log('writer.close()');
                        if(writer){
                          writer.close();
                        }else{
                          alert('Dump completed !');
                        }
                      })
                      .catch((error) => {
                          console.error(error);
                          alert('Error :' + error);
                      });
            });
            
            function showProgress(tableIndex, offset, rows, beforeDumping = false){
              let status = '';
              
              if(beforeDumping){
                  status = 'dumping...';
              }else if(offset > 0){
                status = 'exported ' + offset + ' of ' + rows + 'rows';
                globalStatus = 'table ' + globalTableList[tableIndex].table_name + '... ';
                }else{
                  status = 'exported';
              }
              $('#tableProgress' + tableIndex).html(status);
              
              showStatusMessage(('table ' + globalTableList[tableIndex].table_name + ': ' + status), 'body');
            }

            async function downloadTableContent(table, rows, writer, index) {
              return new Promise((resolve, reject) => {
                var offset = 0;

                var params = {
                  maxRows: null //1
                  , exportStructure: true
                  , exportDropStatement: true
                  , exportData: true
                }
                
                function downloadChunk() {
                  $.ajax({
                    type: "POST",
                    async: true,
                    url: "?action=dumpChunk&table=" + table + "&offset=" + offset,
                    data: {params: params, config: getDBconfig()},
                    success: function (response) {
                      
                      // Find the index of the first newline character
                      var indexOfNewline = response.indexOf('\n');
      
                      // Get the first line
                      var firstLine;
                      var responseOffset;
                      if (indexOfNewline !== -1) {
                        firstLine = response.substring(0, indexOfNewline);
                        responseOffset = parseInt(firstLine);
                        response = response.substring(indexOfNewline+1);
                      }
                      
                      if(writer){
                        const uInt8 = new TextEncoder().encode(response);
                        writer.write(uInt8);
                      }

                      showProgress(index, responseOffset, rows, false);

                      if (responseOffset > 0) {
                        offset = responseOffset;
                        downloadChunk();
                      }else{
                          resolve();                        
                      }
                        
                    },
                    error: function (error, textStatus) {
                      console.log(error, textStatus);
                      reject();
                    }
                  });
                }
                showProgress(index, null, rows, true);
                downloadChunk();
              });
            }

            $("#downloadDump").click(function () {
              var tablesContent = $("#tablesContent").text();
              var blob = new Blob([tablesContent], {type: 'text/plain'});
              var url = URL.createObjectURL(blob);
              var a = document.createElement('a');
              a.href = url;
              a.download = 'database_dump.txt';
              document.body.appendChild(a);
              a.click();
              document.body.removeChild(a);
            });
          });
       </script>      

      </body>
    </html>
  <?php
}
