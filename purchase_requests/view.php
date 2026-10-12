
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid request ID.");
}

$stmt = $conn->prepare(
    "SELECT p.*, d.department_name, u.full_name
     FROM purchase_requests p
     LEFT JOIN departments d ON p.department_id = d.id
     JOIN users u ON p.requested_by = u.id
     WHERE p.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    exit("Purchase request not found.");
}

$stmt = $conn->prepare(
    "SELECT item_name, specifications, quantity, estimated_unit_price
     FROM request_items WHERE request_id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Purchase Request</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { display:block; padding:30px; }
        .container {
            max-width:900px; margin:auto; background:white;
            padding:25px; border-radius:12px;
        }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th,td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
        th { background:#f1f5f9; }
    </style>
</head>
<body>
<div class="container">
    <h1>Purchase Request Details</h1>

    <p><strong>Request Number:</strong> <?= h($request["request_number"]) ?></p>
    <p><strong>Department:</strong> <?= h($request["department_name"] ?? "Not assigned") ?></p>
    <p><strong>Requested By:</strong> <?= h($request["full_name"]) ?></p>
    <p><strong>Date:</strong> <?= h($request["request_date"]) ?></p>
    <p><strong>Status:</strong> <?= h($request["status"]) ?></p>
    <p><strong>Purpose:</strong> <?= nl2br(h($request["purpose"] ?? "")) ?></p>

    <h2>Requested Items</h2>
    <table>
        <thead>
        <tr>
            <th>Item</th>
            <th>Specifications</th>
            <th>Quantity</th>
            <th>Estimated Unit Price</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?= h($item["item_name"]) ?></td>
                <td><?= h($item["specifications"] ?? "") ?></td>
                <td><?= (int)$item["quantity"] ?></td>
                <td>₹<?= number_format((float)$item["estimated_unit_price"], 2) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <p><a href="index.php">Back to Purchase Requests</a></p>
</div>
</body>
</html>
