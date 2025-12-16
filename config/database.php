<?php
// Get database connection
function getDB() {
    static $connection = null;
    
    if ($connection === null) {
        // Get Info from the environment file
        $env = parse_ini_file(__DIR__ . "/../env/connect.env");
        
        // Create a connection
        $connection = new mysqli(
            $env["server"], 
            $env["user"], 
            $env["password"], 
            $env["database"], 
            $env["port"]
        );
        
        // Check if connection established
        if ($connection->connect_error) {
            die("Database connection failed: " . $connection->connect_error);
        }
        
        // Set charset to utf8mb4
        $connection->set_charset("utf8mb4");
    }
    
    return $connection;
}
