<?php
    // Get Info from the environment file
    $env = parse_ini_file(__DIR__ . "/../env/connect.env");

    // Create a connection
    $connection = new mysqli($env["server"], $env["user"], $env["password"], $env["database"], $env["port"]);

    // Check if connection established
    if($connection->connect_error){
        die("Connection Failed " . $connection->connect_error);
    }

    // Message
    echo "Connected Successfully to the Database!\n";
?>