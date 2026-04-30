<?php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed. Please check your configuration.');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert($table, $data) {
        $cols = array_keys($data);
        $placeholders = ':' . implode(', :', $cols);
        $sql = "INSERT INTO " . DB_PREFIX . "$table (`" . implode('`, `', $cols) . "`) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $params = []) {
        $sets = [];
        foreach ($data as $k => $v) $sets[] = "`$k` = :__$k";
        $sql = "UPDATE " . DB_PREFIX . "$table SET " . implode(', ', $sets) . " WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $k => $v) $stmt->bindValue(":__$k", $v);
        foreach ($params as $i => $v) $stmt->bindValue(is_int($i) ? $i + 1 : $i, $v);
        return $stmt->execute();
    }
    
    public function pdo() {
        return $this->pdo;
    }
}
