<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        "localhost",
        "root",
        "",
        "college_inventory"
    );

    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    exit("Database connection failed. Check your XAMPP configuration.");
}