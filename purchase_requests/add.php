
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

$departments = $conn->query(
    "SELECT id, department_name FROM departments ORDER BY department_name"
);

$errors = [];
$purpose = "";
$department_id = "";
$item_name = "";
$specifications = "";
$quantity = "1";
$estimated_price = "0";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $purpose = trim($_POST["purpose"] ?? "");
    $department_id = trim($_POST["department_id"] ?? "");
    $item_name = trim($_POST["item_name"] ?? "");
    $specifications = trim($_POST["specifications"] ?? "");
    $quantity = trim($_POST["quantity"] ?? "");
    $estimated_price = trim($_POST["estimated_price"] ?? "0");

    $department = filter_var($department_id, FILTER_VALIDATE_INT);
    $qty = filter_var($quantity, FILTER_VALIDATE_INT);
    $price = filter_var($estimated_price, FILTER_VALIDATE_FLOAT);

    if ($department === false || $department < 1) {
        $errors[] = "Select a department.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM departments WHERE id = ?"
        );
        $check->bind_param("i", $department);
        $check->execute();
        $departmentExists = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$departmentExists) {
            $errors[] = "Selected department does not exist.";
        }
    }

    if ($item_name === "") {
        $errors[] = "Item name is required.";
    }

    if ($qty === false || $qty < 1) {
        $errors[] = "Quantity must be at least 1.";
    }

    if (
        $price === false ||
        !is_finite((float)$price) ||
        $price < 0 ||
        $price > 9999999999.99
    ) {
        $errors[] = "Enter a valid non-negative estimated unit price.";
    }

    if (!$errors) {
        $conn->begin_transaction();

        try {
            $requestNumber = "PR-" . date("Ymd-His") . "-" .
                             random_int(100, 999);
            $userId = (int)$_SESSION["user_id"];
            $requestDate = date("Y-m-d");
            $status = "submitted";

            $stmt = $conn->prepare(
                "INSERT INTO purchase_requests
                 (request_number, department_id, requested_by,
                  purpose, status, request_date)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "siisss",
                $requestNumber,
                $department,
                $userId,
                $purpose,
                $status,
                $requestDate
            );

            $stmt->execute();
            $requestId = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO request_items
                 (request_id, item_name, specifications,
                  quantity, estimated_unit_price)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "issid",
                $requestId,
                $item_name,
                $specifications,
                $qty,
                $price
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: index.php?created=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log($e->getMessage());
            $errors[] = "Could not save the request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Request</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { display:block; padding:30px; }
        .form-card {
            max-width:700px; margin:auto; background:white;
            padding:25px; border-radius:12px;
        }
        label { display:block; margin-top:15px; }
        input, select, textarea {
            width:100%; padding:10px; margin-top:6px;
            border:1px solid #cbd5e1; border-radius:6px;
        }
        .error { background:#fee2e2; color:#991b1b; padding:10px; }
    </style>
</head>
<body>
<div class="form-card">
    <h1>Create Purchase Request</h1>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Department *</label>
        <select name="department_id" required>
            <option value="">Select department</option>
            <?php while ($dept = $departments->fetch_assoc()): ?>
                <option value="<?= (int)$dept["id"] ?>"
                    <?= (string)$dept["id"] === $department_id ? "selected" : "" ?>>
                    <?= h($dept["department_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Purpose</label>
        <textarea name="purpose"><?= h($purpose) ?></textarea>

        <label>Item Name *</label>
        <input name="item_name" required value="<?= h($item_name) ?>">

        <label>Specifications</label>
        <textarea name="specifications"><?= h($specifications) ?></textarea>

        <label>Quantity *</label>
        <input type="number" name="quantity" min="1" required
               value="<?= h($quantity) ?>">

        <label>Estimated Unit Price (₹) *</label>
        <input type="number" name="estimated_price" min="0"
               step="0.01" required value="<?= h($estimated_price) ?>">

        <button type="submit">Submit Purchase Request</button>
    </form>

    <p><a href="index.php">Back to Purchase Requests</a></p>
</div>
</body>
</html>
