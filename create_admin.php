
<?php
require_once __DIR__ . "/config/db.php";

$username = "admin";
$password = "Admin@123";

$stmt = $conn->prepare(
    "UPDATE users SET password_hash = ? WHERE username = ?"
);

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt->bind_param("ss", $hash, $username);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    echo "Admin password configured successfully.";
}

$stmt->close();
$conn->close();
?>
