<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2014 PhreeSoft      (www.PhreeSoft.com)       |
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
        $this->link->beginTransaction();
    }

    function transCommit() {
        $this->link->commit();
    }

    function transRollback() {
        $this->link->rollBack();
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
        echo $this->error_number . ' ' . $this->error_text;
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
            $page = $_POST['page'] ?? $_GET['page'] ?? '';
            if ($page === 'ajax') {
                if (method_exists($messageStack, 'debug')) {
                    $messageStack->debug("\n\nThe failing sql was: " . $zf_sql);
                    $messageStack->debug("\n\nPDO returned: " . $e->getCode() . ' ' . $e->getMessage());
                    if (defined('FILENAME_DEFAULT')) $messageStack->write_debug();
                }
                echo createXmlHeader() . xmlEntry('error', 'There was a SQL Error: ' . $e->getMessage()) . createXmlFooter();
                die();
            }
            if (method_exists($messageStack, 'debug')) {
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
                echo str_replace("\n", '<br />', $messageStack->debug_info ?? $e->getMessage());
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
