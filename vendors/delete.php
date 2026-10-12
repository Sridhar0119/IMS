
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$stmt = $conn->prepare("DELETE FROM vendors WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted > 0) {
    header("Location: index.php?deleted=1");
} else {
    header("Location: index.php?error=not_found");
}
exit;
?>
