<?php
class _database {
    private $link        = null;
    private $result      = [];   // all rows from last SELECT
    private $row         = false;
    private $cursor      = 0;
    private $affected    = 0;    // rowCount() from last statement
    private $last_error  = '';

    public $settings = [
        "servername" => "localhost",
        "serverport" => "3306",
        "username"   => false,
        "password"   => false,
        "database"   => false,
        "persist"    => false,
        "dieonerror" => false,
        "showerror"  => false,
        "error_file" => true,
    ];

    function __construct() {
        global $db_config;
        $this->settings = array_merge($this->settings, $db_config);
        if ($this->settings["error_file"] === true) {
            $this->settings["error_file"] = dirname(__FILE__) . "/__pdo_errors.log";
        }
    }

    function connect() {
        if (!$this->link) {
            $dsn = "mysql:host={$this->settings['servername']};port={$this->settings['serverport']}"
                 . ";dbname={$this->settings['database']};charset=utf8";
            try {
                $this->link = new PDO($dsn, $this->settings["username"], $this->settings["password"], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
                    PDO::ATTR_PERSISTENT         => (bool)$this->settings["persist"],
                ]);
            } catch (PDOException $e) {
                $this->error($e->getMessage());
                return false;
            }
        }
        return true;
    }

    function query($sql) {
        if (!$this->link && !$this->connect()) return false;
        try {
            $stmt           = $this->link->query($sql);
            $this->affected = $stmt->rowCount();
            $this->result   = $stmt->fetchAll();
            $this->cursor   = 0;
            $this->row      = false;
        } catch (PDOException $e) {
            $this->error($e->getMessage(), $sql);
            return false;
        }
        return true;
    }

    function nextr() {
        if ($this->result === [] && $this->cursor === 0) return false;
        if ($this->cursor >= count($this->result)) return false;
        $this->row = $this->result[$this->cursor];
        $this->cursor++;
        return true;
    }

    function get_row($mode = "both") {
        if (!$this->row) return false;
        $return = [];
        switch ($mode) {
            case "assoc":
                foreach ($this->row as $k => $v) {
                    if (!is_int($k)) $return[$k] = $v;
                }
                break;
            case "num":
                foreach ($this->row as $k => $v) {
                    if (is_int($k)) $return[$k] = $v;
                }
                break;
            default:
                $return = $this->row;
                break;
        }
        return array_map(fn($v) => is_string($v) ? stripslashes($v) : $v, $return);
    }

    function get_all($mode = "both", $key = false) {
        $return = [];
        while ($this->nextr()) {
            if ($key !== false) $return[$this->f($key)] = $this->get_row($mode);
            else $return[] = $this->get_row($mode);
        }
        return $return;
    }

    function f($index) {
        return stripslashes($this->row[$index]);
    }

    function go_to($row) {
        if ($row < 0 || $row >= count($this->result)) {
            $this->error("Row $row out of range");
            return false;
        }
        $this->cursor = $row;
        $this->row    = $this->result[$row];
        return true;
    }

    function nf() {
        return count($this->result);
    }

    function af() {
        return $this->affected;
    }

    function error($string = "", $sql = "") {
        $this->last_error = $string;
        if ($this->settings["show_error"]) echo $string;
        if ($this->settings["error_file"] !== false) {
            $handle = @fopen($this->settings["error_file"], "a+");
            if ($handle) {
                @fwrite($handle, "[" . date("Y-m-d H:i:s") . "] " . $sql . " <" . $string . ">\n");
                @fclose($handle);
            }
        }
        if ($this->settings["dieonerror"]) {
            $this->link = null;
            die();
        }
    }

    function insert_id() {
        if (!$this->link) return false;
        return $this->link->lastInsertId();
    }

    function escape($string) {
        if (!$this->link) return addslashes($string);
        return substr($this->link->quote($string), 1, -1);
    }

    function destroy() {
        $this->link   = null;
        $this->result = [];
    }
}
?>
