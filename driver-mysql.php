<?php

// a pilot to drive mysql-based pads:

class mysqlpads {
    private $db=false;
    private $enum_stmt=false;
    
    /* dsn be like: "mysql:host=localhost;dbname=testdb"
     */
    function mysqlpads($dsn,$user,$pass) {
        $this->db = new PDO($dsn,$user,$pass);
        if (!$this->db) {
            echo "Can't connect to MySQL, exiting\n";
            exit();
        }
    }

    /* returns the value of a key. */
    function get($key) { 
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $stmt = $this->db->prepare("SELECT `value` FROM store WHERE `key`='".addslashes($key)."';");
        $stmt->execute();
        $res=$stmt->fetch(PDO::FETCH_NUM);
        return $res[0];
    }

    function enumerate($keyprefix="") {
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $sql="";
        if ($keyprefix) $sql=" WHERE `key` LIKE '".addslashes($keyprefix)."%' ";
        $stmt = $this->db->prepare("SELECT `key` FROM store $sql;");
        $stmt->execute();
        $this->enum_stmt=$stmt;
        return true;
    }

    function next() {
        if ($this->enum_stmt) {
            $value=$this->enum_stmt->fetch(PDO::FETCH_NUM);
            if ($value===false) return false;
            return $value[0];
        }
        return false;
    }

    function end() {
        if ($this->enum_stmt) {
            $this->enum_stmt->closeCursor();
            $this->enum_stmt=false;
        }
    }

    function del($key) {
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $stmt = $this->db->prepare("DELETE FROM store WHERE `key`='".addslashes($key)."';");
        $stmt->execute();       
    }
    
}
