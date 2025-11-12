# PHP Remote SQL Dump

A PHP script for exporting large MySQL/MariaDB databases, designed as an alternative to tools like phpMyAdmin that may fail due to script timeouts during large operations.

This script can be operated in two modes: as a graphical tool in your browser or as a command-line (CLI) utility.

## 1. Browser (GUI) Usage

1.  Upload `remote.sql.dump.php` to your web server.
2.  Access the script directly in your browser (e.g., `https://example.com/remote.sql.dump.php`).
3.  Fill in the required database connection parameters on the web page.
4.  Click "Export" to start the dump operation from your browser.

## 2. Command-Line (CLI) Usage

This mode is designed for running the export/dump process from your local command line.

**Important Requirement:** For CLI mode to work, the `remote.sql.dump.php` script must exist in **two places**:
1.  **Locally:** On the client machine where you are running the command.
2.  **Remotely:** Uploaded to the server at the exact location specified by the `--url` parameter.

### Syntax

```bash
php remote.sql.dump.php --url <URL> --host <HOST> --username <USERNAME> --password <PASSWORD> --database <DATABASE> [--list_tables]
```

### Parameters
Required

    --url <URL> The full URL to the remote remote.sql.dump.php script. (Example: https://example.com/remote.sql.dump.php)

    --host <HOST> The hostname or IP address of the database server. (Example: localhost)

    --username <USERNAME> The username to connect to the MySQL/MariaDB database. (Example: root)

    --password <PASSWORD> The password to connect to the database. (Example: triadpass)

    --database <DATABASE> The name of the database to connect to. (Example: todolist_nette)

Optional

    --list_tables Lists all tables in the specified database without performing any dump operation.

Examples

1. To perform a full database dump:
```bash
php remote.sql.dump.php --url https://example.com/remote.sql.dump.php --host localhost --username your_db_user --password "SECRETPASS" --database your_db_name
```

2. To list all tables in the database:
```bash
php remote.sql.dump.php --url https://example.com/remote.sql.dump.php --host localhost --username your_db_user --password "SECRETPASS" --database your_db_name --list_tables
```
