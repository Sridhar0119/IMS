
<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$isProduction = getenv('DB_HOST') !== false
    && getenv('DB_HOST') !== '';

$dbHost = $isProduction ? getenv('DB_HOST') : 'localhost';
$dbUser = $isProduction ? getenv('DB_USER') : 'root';
$dbPass = $isProduction ? getenv('DB_PASSWORD') : '';
$dbName = $isProduction ? getenv('DB_NAME') : 'college_inventory';
$dbPort = $isProduction ? (int) getenv('DB_PORT') : 3306;

try {
    $conn = mysqli_init();

    if ($isProduction) {
        mysqli_ssl_set($conn, null, null, null, null, null);
    }

    $conn->real_connect(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName,
        $dbPort,
        null,
        $isProduction ? MYSQLI_CLIENT_SSL : 0
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    exit("Database connection failed. Check your database configuration.");
}
