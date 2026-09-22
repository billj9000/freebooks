<?php
// +-----------------------------------------------------------------+
// |                          FreeBooks ERP                          |
// +-----------------------------------------------------------------+
// | Copyright(c) 2026 Bill Jones                                    |
// | Portions Copyright(c) 2008-2014 PhreeSoft                       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /includes/db/mysql/query_factory.php
//

class queryFactory {
    public $count_queries    = 0;
    public $total_query_time = 0;
    public $database         = '';
    public $db_connected     = false;
    public $link             = null;
    public $error_number     = '';
    public $error_text       = '';

    function connect($zf_host, $zf_user, $zf_password, $zf_database) {
        $this->database = $zf_database;
        try {
            $dsn = "mysql:host={$zf_host};dbname={$zf_database};charset=utf8";
            $this->link = new PDO($dsn, $zf_user, $zf_password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->db_connected = true;
            return true;
        } catch (PDOException $e) {
            $this->set_error($e->getCode(), $e->getMessage(), false);
            return false;
        }
    }

    function selectdb($zf_database) {
        $this->database = $zf_database;
        $this->link->exec('USE `' . $zf_database . '`');
    }

    function transStart() {
        if (!$this->link->inTransaction()) $this->link->beginTransaction();
    }

    function transCommit() {
        if ($this->link->inTransaction()) $this->link->commit();
    }

    function transRollback() {
        if ($this->link->inTransaction()) $this->link->rollBack();
    }

    // Returns the escaped string without surrounding quotes, matching the original contract.
    function prepare_input($zp_string) {
        if ($this->link) {
            return substr($this->link->quote($zp_string), 1, -1);
        }
        return addslashes($zp_string);
    }

    function close() {
        $this->link         = null;
        $this->db_connected = false;
    }

    function set_error($zp_err_num, $zp_err_text, $zp_fatal = true) {
        $this->error_number = $zp_err_num;
        $this->error_text   = $zp_err_text;
        if ($zp_fatal && $zp_err_num != 1141) {
            $this->show_error();
            die();
        }
    }

    function show_error() {
        $num  = htmlspecialchars($this->error_number);
        $text = htmlspecialchars($this->error_text);
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database Error</title>
<style>
  body{font-family:sans-serif;background:#f8f9fa;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
  .card{background:#fff;border:1px solid #dee2e6;border-radius:.375rem;max-width:480px;width:100%;overflow:hidden}
  .card-header{background:#dc3545;color:#fff;padding:.5rem 1rem;font-weight:600}
  .card-body{padding:1rem}
  .code{color:#6c757d;font-size:.85em}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">FreeBooks &mdash; Database Error</div>
  <div class="card-body">
    <p>A database connection error occurred. Please check that the database server is running and the credentials in your configuration are correct.</p>
    <p class="code">Error {$num}: {$text}</p>
  </div>
</div>
</body></html>
HTML;
    }

    /**
     * Append a failed statement to my_files/sql_errors.log.
     *
     * Everything else this class does with a failure is transient: the web path
     * redirects to the crash page, the ajax path returns an <error> element, and
     * write_debug() opens trace.txt with mode 'w', so each failure erases the one
     * before it. A cron job gets none of them - gen_redirect() is a no-op header()
     * and an exit, so it ends with status 0 and nothing written down.
     *
     * So this is the only durable record that a statement was refused, which is
     * what a database at its default settings does to writes a lax one accepted.
     * It is also the only evidence anyone has when an installation misbehaves and
     * the user reports a blank page.
     *
     * Appends, never truncates, and never throws: a logger that can bring down the
     * request it is describing is worse than no logger.
     */
    private function log_sql_error($sql, $e) {
        if (!defined('DIR_FS_MY_FILES')) return;   // too early, or a harness without one
        try {
            $caller = '';
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                if (isset($frame['file']) && basename($frame['file']) <> 'query_factory.php') {
                    $caller = (defined('DIR_FS_ADMIN') ? str_replace(DIR_FS_ADMIN, '', $frame['file']) : $frame['file'])
                            . ':' . ($frame['line'] ?? '?');
                    break;
                }
            }
            $page = $_POST['page'] ?? $_GET['page'] ?? (PHP_SAPI === 'cli' ? 'cli' : '');
            $line = sprintf("%s  %s  %s  at %s  page=%s%s  sql=%s%s",
                date('Y-m-d H:i:s'), $e->getCode(), preg_replace('/\s+/', ' ', $e->getMessage()),
                $caller, $page, PHP_EOL, preg_replace('/\s+/', ' ', trim($sql)), PHP_EOL);
            // common_functions.php is loaded before this file by application_top,
            // but a CLI harness may load the database layer on its own.
            if (function_exists('gen_log_append')) {
                gen_log_append(DIR_FS_MY_FILES . 'sql_errors.log', $line);
            } else {
                @error_log($line, 3, DIR_FS_MY_FILES . 'sql_errors.log');
            }
        } catch (Throwable $ignored) {   // never let logging break the request
        }
    }

    function Execute($zf_sql, $zf_limit = false, $zf_cache = false, $zf_cachetime = 0) {
        global $zc_cache, $messageStack;
        if ($zf_limit !== false) {
            $zf_sql .= ' LIMIT ' . $zf_limit;
        }
        if ($zf_cache && $zc_cache->sql_cache_exists($zf_sql) && !$zc_cache->sql_cache_is_expired($zf_sql, $zf_cachetime)) {
            $obj            = new queryFactoryResult;
            $obj->is_cached = true;
            $obj->sql_query = $zf_sql;
            $obj->result    = $zc_cache->sql_cache_read($zf_sql);
            $obj->_load_cursor();
            return $obj;
        }
        if ($zf_cache) {
            $zc_cache->sql_cache_expire_now($zf_sql);
        }

        $time_start = microtime(true);
        $obj            = new queryFactoryResult;
        $obj->sql_query = $zf_sql;
        if (!$this->db_connected) {
            $this->set_error('0', DB_ERROR_NOT_CONNECTED);
        }
        $zf_sql = rtrim($zf_sql, "; \t\n\r");
        try {
            $stmt            = $this->link->query($zf_sql);
            $obj->_row_count = $stmt->rowCount();
            $obj->result     = $stmt->columnCount() > 0 ? $stmt->fetchAll() : [];
            $obj->_load_cursor();
        } catch (PDOException $e) {
            $this->log_sql_error($zf_sql, $e);
            $page = $_POST['page'] ?? $_GET['page'] ?? '';
            if ($page === 'ajax') {
                if (is_object($messageStack) && method_exists($messageStack, 'debug')) {
                    $messageStack->debug("\n\nThe failing sql was: " . $zf_sql);
                    $messageStack->debug("\n\nPDO returned: " . $e->getCode() . ' ' . $e->getMessage());
                    if (defined('FILENAME_DEFAULT')) $messageStack->write_debug();
                }
                echo createXmlHeader() . xmlEntry('error', 'There was a SQL Error: ' . $e->getMessage()) . createXmlFooter();
                die();
            }
            if (is_object($messageStack) && method_exists($messageStack, 'debug')) {
                $messageStack->debug("\n\nThe failing sql was: " . $zf_sql);
                $messageStack->debug("\n\nPDO returned: " . $e->getCode() . ' ' . $e->getMessage());
                if (defined('FILENAME_DEFAULT')) {
                    $messageStack->write_debug();
                    $messageStack->add('The last transaction had a SQL database error.', 'error');
                    gen_redirect(html_href_link(FILENAME_DEFAULT, 'cat=phreedom&page=main&amp;action=crash', 'SSL'));
                } else {
                    echo str_replace("\n", '<br />', $messageStack->debug_info ?? '');
                    die;
                }
            } else {
                echo str_replace("\n", '<br />',
                    is_object($messageStack) ? ($messageStack->debug_info ?? $e->getMessage())
                                             : $e->getMessage());
                die;
            }
        }

        if ($zf_cache) {
            $zc_cache->sql_cache_store($zf_sql, $obj->result);
            $obj->is_cached = true;
        }

        $this->total_query_time += microtime(true) - $time_start;
        $this->count_queries++;
        return $obj;
    }

    /**
     * Execute a statement with bound parameters.
     *
     * Additive counterpart to Execute(), which takes only a finished SQL string
     * and so leaves callers to interpolate their own values — the shape behind
     * the injection defects fixed across popup_gen, gen_db_date() and
     * gen_build_sql_date(). The connection has always been PDO; this exposes
     * what it could already do.
     *
     * Returns the same queryFactoryResult cursor as Execute(), so callers use
     * MoveNext()/EOF()/fields identically. Deliberately not cached: the cache is
     * keyed on the SQL text, which is no longer unique once the values live in
     * parameters.
     *
     * @param string             $zf_sql    statement containing ? placeholders
     * @param array<int, scalar|null> $zf_params values, in placeholder order
     */
    function ExecutePrepared($zf_sql, array $zf_params = []) {
        global $messageStack;

        $time_start      = microtime(true);
        $obj             = new queryFactoryResult;
        $obj->sql_query  = $zf_sql;
        if (!$this->db_connected) {
            $this->set_error('0', DB_ERROR_NOT_CONNECTED);
        }
        $zf_sql = rtrim($zf_sql, "; \t\n\r");
        try {
            $stmt = $this->link->prepare($zf_sql);
            $stmt->execute(array_values($zf_params));
            $obj->_row_count = $stmt->rowCount();
            $obj->result     = $stmt->columnCount() > 0 ? $stmt->fetchAll() : [];
            $obj->_load_cursor();
        } catch (PDOException $e) {
            if (is_object($messageStack) && method_exists($messageStack, 'debug')) {
                $messageStack->debug("\n\nThe failing sql was: " . $zf_sql);
                $messageStack->debug("\n\nPDO returned: " . $e->getCode() . ' ' . $e->getMessage());
            }
            throw $e;   // the caller decides; this method never redirects or dies
        }

        $this->total_query_time += microtime(true) - $time_start;
        $this->count_queries++;
        return $obj;
    }

    function Execute_return_error($zf_sql) {
        $time_start = microtime(true);
        $obj = new queryFactoryResult;
        if (!$this->db_connected) {
            $this->set_error('0', DB_ERROR_NOT_CONNECTED);
        }
        $zf_sql = rtrim($zf_sql, "; \t\n\r");
        try {
            $stmt            = $this->link->query($zf_sql);
            $obj->_row_count = $stmt->rowCount();
            $obj->result     = $stmt->columnCount() > 0 ? $stmt->fetchAll() : [];
            $obj->_load_cursor();
            $this->error_number = '';
            $this->error_text   = '';
        } catch (PDOException $e) {
            $this->set_error($e->getCode(), $e->getMessage(), false);
            $obj->EOF = true;
        }
        $this->total_query_time += microtime(true) - $time_start;
        $this->count_queries++;
        return $obj;
    }

    function insert_ID() {
        return $this->link->lastInsertId();
    }
}

class queryFactoryResult {
    public $cursor     = 0;
    public $fields     = [];
    public $is_cached  = false;
    public $EOF        = true;
    public $result     = [];
    public $resource   = null; // retained for API compatibility
    public $sql_query  = '';
    public $_row_count = 0;   // PDOStatement::rowCount() saved before fetchAll()

    // Populates $fields from $result[0] and sets EOF.
    function _load_cursor() {
        if (count($this->result) > 0) {
            $this->cursor = 0;
            $this->fields = $this->result[0];
            $this->EOF    = false;
        } else {
            $this->EOF = true;
        }
    }

    function MoveNext() {
        $this->cursor++;
        if ($this->cursor >= count($this->result)) {
            $this->EOF = true;
        } else {
            $this->fields = $this->result[$this->cursor];
        }
    }

    function RecordCount() {
        return count($this->result);
    }

    function AffectedRows() {
        return $this->_row_count;
    }

    function Move($zp_row) {
        if ($zp_row >= 0 && $zp_row < count($this->result)) {
            $this->cursor = $zp_row;
            $this->fields = $this->result[$zp_row];
            $this->EOF    = false;
        } else {
            $this->EOF = true;
        }
    }
}
?>
