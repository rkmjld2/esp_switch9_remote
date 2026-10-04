```php
<?php

/*
====================================================
ESP-SWITCH9 REMOTE
Database connection for Render + TiDB Cloud
====================================================
*/

date_default_timezone_set("Asia/Kolkata");

/*
----------------------------------------------------
Read environment variables from Render
----------------------------------------------------
*/

$db_host = getenv("DB_HOST");
$db_user = getenv("DB_USER");
$db_pass = getenv("DB_PASS");
$db_name = getenv("DB_NAME");
$db_port = getenv("DB_PORT");

if (!$db_port) {
    $db_port = 4000;
}

/*
----------------------------------------------------
Check database credentials
----------------------------------------------------
*/

if (!$db_host || !$db_user || !$db_name) {

    die("Database environment variables are missing.");
}

/*
----------------------------------------------------
Create MySQL connection
----------------------------------------------------
*/

$conn = mysqli_init();

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL
);

/*
----------------------------------------------------
Connect to TiDB Cloud
----------------------------------------------------
*/

if (!mysqli_real_connect(
    $conn,
    $db_host,
    $db_user,
    $db_pass,
    $db_name,
    $db_port,
    NULL,
    MYSQLI_CLIENT_SSL
)) {

    die("Database connection failed: " . mysqli_connect_error());
}

/*
----------------------------------------------------
Set UTF-8
----------------------------------------------------
*/

mysqli_set_charset($conn, "utf8mb4");

/*
----------------------------------------------------
Connection successful
----------------------------------------------------
*/

?>
```
