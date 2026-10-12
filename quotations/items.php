
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/db.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$quotationId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
if (!$quotationId || $quotationId < 1) {
    exit("Invalid quotation ID. Open this page with a valid quotation ID.");
}

$stmt = $conn->prepare("
    SELECT q.id, q.quotation_number, q.request_id,
           v.vendor_name, pr.request_number
    FROM quotations q
    JOIN vendors v ON q.vendor_id = v.id
    JOIN purchase_requests pr ON q.request_id = pr.id
    WHERE q.id = ?
");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) {
    exit("Quotation not found.");
}

$message = "";
$error = "";

// Save a quoted item
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestItemId = filter_input(INPUT_POST, "request_item_id", FILTER_VALIDATE_INT);
    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $unitPrice = filter_input(INPUT_POST, "unit_price", FILTER_VALIDATE_FLOAT);
    $taxRate = filter_input(INPUT_POST, "tax_rate", FILTER_VALIDATE_FLOAT);
    $warranty = filter_input(INPUT_POST, "warranty_months", FILTER_VALIDATE_INT);
    $delivery = filter_input(INPUT_POST, "delivery_days", FILTER_VALIDATE_INT);

    if (
        !$requestItemId || $requestItemId < 1 ||
        $unitPrice === false || $unitPrice === null || $unitPrice < 0 ||
        $taxRate === false || $taxRate === null || $taxRate < 0 || $taxRate > 100 ||
        $warranty === false || $warranty === null || $warranty < 0 ||
        $delivery === false || $delivery === null || $delivery < 0
    ) {
        $error = "Enter valid item, price, tax, warranty and delivery values.";
    } else {
        // Only allow items belonging to this quotation's purchase request
        $stmt = $conn->prepare("
            SELECT item_name, specifications, quantity
            FROM request_items
            WHERE id = ? AND request_id = ?
        ");
        $stmt->bind_param("ii", $requestItemId, $quotation["request_id"]);
        $stmt->execute();
        $requestItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$requestItem) {
            $error = "That item does not belong to this purchase request.";
        } else {
            // Avoid adding the same requested item twice to one quotation
            $stmt = $conn->prepare("
                SELECT id FROM quotation_items
                WHERE quotation_id = ? AND request_item_id = ?
            ");
            $stmt->bind_param("ii", $quotationId, $requestItemId);
            $stmt->execute();
            $alreadyExists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($alreadyExists) {
                $error = "This requested item is already recorded for this quotation.";
            } else {
                $itemName = $requestItem["item_name"];
                $specifications = $requestItem["specifications"] ?? "";
                $quantity = (int)$requestItem["quantity"];

                $stmt = $conn->prepare("
                    INSERT INTO quotation_items
                    (quotation_id, request_item_id, item_description,
                     brand, model, specifications, quantity, unit_price,
                     tax_rate, warranty_months, delivery_days)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "iissssiddii",
                    $quotationId,
                    $requestItemId,
                    $itemName,
                    $brand,
                    $model,
                    $specifications,
                    $quantity,
                    $unitPrice,
                    $taxRate,
                    $warranty,
                    $delivery
                );

                $stmt->execute();
                $stmt->close();

                $message = "Quotation item saved successfully.";
            }
        }
    }
}

// Requested items available for this quotation
$stmt = $conn->prepare("
    SELECT ri.id, ri.item_name, ri.specifications,
           ri.quantity, ri.estimated_unit_price
    FROM request_items ri
    WHERE ri.request_id = ?
    ORDER BY ri.id
");
$stmt->bind_param("i", $quotation["request_id"]);
$stmt->execute();
$requestedItems = $stmt->get_result();
$stmt->close();

// Items already recorded for this quotation
$stmt = $conn->prepare("
    SELECT item_description, brand, model, quantity,
           unit_price, tax_rate, warranty_months, delivery_days
    FROM quotation_items
    WHERE quotation_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$savedItems = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Items</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        .panel { max-width: 1000px; margin: 20px auto; padding: 22px;
                 border: 1px solid #ddd; border-radius: 10px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; margin-top: 5px;
                        box-sizing: border-box; }
        button { margin-top: 18px; padding: 10px 16px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 9px; text-align: left; }
        th { background: #f1f4f8; }
        .success { color: #176b36; }
        .error { color: #b42318; }
    </style>
</head>
<body>
<div class="panel">
    <p><a href="index.php">← Back to Quotations</a></p>

    <h2>Add Quoted Items</h2>
    <p><strong>Quotation:</strong> <?= e($quotation["quotation_number"]) ?></p>
    <p><strong>Vendor:</strong> <?= e($quotation["vendor_name"]) ?></p>
    <p><strong>Purchase Request:</strong> <?= e($quotation["request_number"]) ?></p>

    <?php if ($message): ?>
        <p class="success"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <h3>Record an Item</h3>

    <?php if ($requestedItems->num_rows === 0): ?>
        <p>No items were found in this purchase request.</p>
    <?php else: ?>
        <form method="POST">
            <label for="request_item_id">Requested Item *</label>
            <select name="request_item_id" id="request_item_id" required>
                <option value="">Select item</option>
                <?php while ($item = $requestedItems->fetch_assoc()): ?>
                    <option value="<?= (int)$item["id"] ?>">
                        <?= e($item["item_name"]) ?>
                        — Qty: <?= (int)$item["quantity"] ?>
                        — Estimated ₹<?= number_format((float)$item["estimated_unit_price"], 2) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="brand">Brand</label>
            <input type="text" name="brand" id="brand" maxlength="150">

            <label for="model">Model</label>
            <input type="text" name="model" id="model" maxlength="150">

            <label for="unit_price">Quoted Unit Price (₹) *</label>
            <input type="number" name="unit_price" id="unit_price"
                   min="0" step="0.01" required>

            <label for="tax_rate">Tax Rate (%) *</label>
            <input type="number" name="tax_rate" id="tax_rate"
                   min="0" max="100" step="0.01" value="18" required>

            <label for="warranty_months">Warranty (months) *</label>
            <input type="number" name="warranty_months" id="warranty_months"
                   min="0" value="0" required>

            <label for="delivery_days">Delivery Time (days) *</label>
            <input type="number" name="delivery_days" id="delivery_days"
                   min="0" value="7" required>

            <button type="submit">Save Quoted Item</button>
        </form>
    <?php endif; ?>

    <h3>Items Already Recorded</h3>
    <table>
        <thead>
        <tr>
            <th>Item</th>
            <th>Brand / Model</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Tax %</th>
            <th>Warranty</th>
            <th>Delivery</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($savedItems->num_rows === 0): ?>
            <tr><td colspan="7">No items recorded yet.</td></tr>
        <?php else: ?>
            <?php while ($item = $savedItems->fetch_assoc()): ?>
                <tr>
                    <td><?= e($item["item_description"]) ?></td>
                    <td><?= e(trim($item["brand"] . " " . $item["model"])) ?></td>
                    <td><?= (int)$item["quantity"] ?></td>
                    <td>₹<?= number_format((float)$item["unit_price"], 2) ?></td>
                    <td><?= number_format((float)$item["tax_rate"], 2) ?>%</td>
                    <td><?= (int)$item["warranty_months"] ?> months</td>
                    <td><?= (int)$item["delivery_days"] ?> days</td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
