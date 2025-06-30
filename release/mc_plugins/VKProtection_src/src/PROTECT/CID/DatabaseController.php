<?php
namespace PROTECT\CID;

use mysqli;

class DatabaseController {
    
    private $db;
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->connect();
        $this->createTables();
    }

    private function connect() {
        $this->db = new mysqli(
            "18.1", 
            "hlebuser", 
            "hRN", 
            "server_data"
        );

        if ($this->db->connect_error) {
            $this->logMySqlError("Ошибка подключения: " . $this->db->connect_error);
            return;
        }
    }
    
    public function close(): void {
       if ($this->db) {
           $this->db->close();
           $this->db = null;
       }
   }

    private function createTables() {
        $tables = [
            "skin" => "CREATE TABLE IF NOT EXISTS skin (
                player VARCHAR(32) NOT NULL,
                hash VARCHAR(8) NOT NULL,
                UNIQUE KEY player_unique (player)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            
            "cid" => "CREATE TABLE IF NOT EXISTS cid (
                player VARCHAR(32) NOT NULL,
                hash VARCHAR(8) NOT NULL,
                UNIQUE KEY player_unique (player)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
            
            "uuid" => "CREATE TABLE IF NOT EXISTS uuid (
                player VARCHAR(32) NOT NULL,
                hash VARCHAR(8) NOT NULL,
                UNIQUE KEY player_unique (player)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        ];

        foreach ($tables as $name => $query) {
            if (!$this->db->query($query)) {
                $this->logMySqlError("Ошибка создания таблицы $name: " . $this->db->error);
            }
        }
    }

    public function addSkinProtection(string $playerName, $skinData) : bool {
        $playerName = strtolower($playerName);
        $skinHash = hash('crc32', $skinData);

        $stmt = $this->db->prepare("
            INSERT INTO skin (player, hash) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE hash = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (addSkinProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("sss", $playerName, $skinHash, $skinHash);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (addSkinProtection): " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function checkSkinProtection(string $playerName, $skinData) : bool {
        $playerName = strtolower($playerName);
        $skinHash = hash('crc32', $skinData);

        $stmt = $this->db->prepare("
            SELECT hash FROM skin 
            WHERE player = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (checkSkinProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("s", $playerName);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (checkSkinProtection): " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $stmt->close();

        if($result->num_rows === 0) return true;
        
        $data = $result->fetch_assoc();
        return $data['hash'] === $skinHash;
    }

    public function removeSkinProtection(string $playerName) : bool {
        $playerName = strtolower($playerName);
        
        $stmt = $this->db->prepare("
            DELETE FROM skin 
            WHERE player = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (removeSkinProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("s", $playerName);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (removeSkinProtection): " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Аналогичные методы для CID и UUID

    public function addCidProtection(string $playerName, $cid) : bool {
        $playerName = strtolower($playerName);
        $cidHash = hash('crc32', $cid);

        $stmt = $this->db->prepare("
            INSERT INTO cid (player, hash) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE hash = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (addCidProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("sss", $playerName, $cidHash, $cidHash);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (addCidProtection): " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function checkCidProtection(string $playerName, $cid) : bool {
        $playerName = strtolower($playerName);
        $cidHash = hash('crc32', $cid);

        $stmt = $this->db->prepare("
            SELECT hash FROM cid 
            WHERE player = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (checkCidProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("s", $playerName);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (checkCidProtection): " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $stmt->close();

        if($result->num_rows === 0) return true;
        
        $data = $result->fetch_assoc();
        return $data['hash'] === $cidHash;
    }

    public function removeCidProtection(string $playerName) : bool {
        $playerName = strtolower($playerName);
        
        $stmt = $this->db->prepare("
            DELETE FROM cid 
            WHERE player = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (removeCidProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("s", $playerName);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (removeCidProtection): " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function addUuidProtection(string $playerName, $uuidString) : bool {
        $playerName = strtolower($playerName);
        $uuidHash = hash('crc32', $uuidString);

        $stmt = $this->db->prepare("
            INSERT INTO uuid (player, hash) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE hash = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (addUuidProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("sss", $playerName, $uuidHash, $uuidHash);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (addUuidProtection): " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function checkUuidProtection(string $playerName, $uuidString) : bool {
        $playerName = strtolower($playerName);
        $uuidHash = hash('crc32', $uuidString);

        $stmt = $this->db->prepare("
            SELECT hash FROM uuid 
            WHERE player = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (checkUuidProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("s", $playerName);
        if (!$stmt->execute()) {
            $this->logMySqlError("Ошибка выполнения запроса (checkUuidProtection): " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $stmt->close();

        if($result->num_rows === 0) return true;
        
        $data = $result->fetch_assoc();
        return $data['hash'] === $uuidHash;
    }

    public function removeUuidProtection(string $playerName) : bool {
        $playerName = strtolower($playerName);
        
        $stmt = $this->db->prepare("
            DELETE FROM uuid 
            WHERE player = ?
        ");
        
        if ($stmt === false) {
            $this->logMySqlError("Ошибка подготовки запроса (removeUuidProtection): " . $this->db->error);
            return false;
        }
        
        $stmt->bind_param("s", $playerName);
        $result = $stmt->execute();
        if (!$result) {
            $this->logMySqlError("Ошибка выполнения запроса (removeUuidProtection): " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function __destruct() {
       $this->close();
   }
   
    private function logMySqlError(string $message): void {
        $this->plugin->getLogger()->error("Ошибка MySQL: " . $message);
    }
}