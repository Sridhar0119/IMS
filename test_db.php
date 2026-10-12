<?php

require_once __DIR__ . "/config/db.php";

$result = $conn->query("SHOW TABLES");

echo "<h2>College Inventory System</h2>";
echo "<p>Database connected successfully!</p>";
echo "<p>Tables found: " . $result->num_rows . "</p>";

$conn->close();