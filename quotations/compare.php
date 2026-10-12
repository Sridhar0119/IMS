
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

$sql = "
    SELECT
        qi.request_item_id,
        qi.item_description,
        ri.quantity AS requested_quantity,
        ri.estimated_unit_price,
        q.id AS quotation_id,
        q.quotation_number,
        v.vendor_name,
        qi.brand,
        qi.model,
        qi.quantity AS quoted_quantity,
        qi.unit_price,
        qi.tax_rate,
        qi.warranty_months,
        qi.delivery_days,
        (qi.unit_price * (1 + qi.tax_rate / 100)) AS price_with_tax,
        (qi.quantity * qi.unit_price * (1 + qi.tax_rate / 100)) AS line_total
    FROM quotation_items qi
    JOIN quotations q ON qi.quotation_id = q.id
    JOIN vendors v ON q.vendor_id = v.id
    JOIN request_items ri ON qi.request_item_id = ri.id
    ORDER BY qi.item_description, price_with_tax ASC
";

$result = $conn->query($sql);
$items = [];

while ($row = $result->fetch_assoc()) {
    $key = $row["request_item_id"];
    $items[$key]["description"] = $row["item_description"];
    $items[$key]["requested_quantity"] = $row["requested_quantity"];
    $items[$key]["estimated_unit_price"] = $row["estimated_unit_price"];
    $items[$key]["vendors"][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation Comparison</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        .panel { max-width: 1200px; margin: 20px auto; padding: 20px;
                 border: 1px solid #0f0f0f; border-radius: 10px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0 30px; }
        th, td { border: 1px solid #121212; padding: 9px; text-align: left; }
        th { background: #17324d; color: black; }
        .best { background: #e4f6e9; font-weight: bold; }
        .muted { color: #171515; }
    </style>
</head>
<body>
<div class="panel">
    <p><a href="../dashboard.php">← Dashboard</a></p>
    <h2>Quotation Comparison</h2>
    <p class="muted">
        Prices include the item's tax rate. Delivery charges and other quotation-level
        charges are not included in these line totals.
    </p>

    <?php if (empty($items)): ?>
        <p>No quotation items available for comparison yet.</p>
        <p>Add quoted items to at least one quotation first.</p>
    <?php else: ?>
        <?php foreach ($items as $group): ?>
            <?php
            $vendors = $group["vendors"];
            $lowestPrice = min(array_column($vendors, "price_with_tax"));
            ?>
            <h3><?= e($group["description"]) ?></h3>
            <p>
                Requested quantity: <?= (int)$group["requested_quantity"] ?>
                | Estimated unit price:
                ₹<?= number_format((float)$group["estimated_unit_price"], 2) ?>
            </p>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Quotation No.</th>
                        <th>Brand / Model</th>
                        <th>Quoted Qty</th>
                        <th>Unit Price</th>
                        <th>Tax</th>
                        <th>Unit Price incl. Tax</th>
                        <th>Line Total incl. Tax</th>
                        <th>Warranty</th>
                        <th>Delivery</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr class="<?= (float)$vendor["price_with_tax"] === (float)$lowestPrice ? 'best' : '' ?>">
                            <td><?= e($vendor["vendor_name"]) ?></td>
                            <td><?= e($vendor["quotation_number"]) ?></td>
                            <td><?= e(trim($vendor["brand"] . " " . $vendor["model"])) ?></td>
                            <td><?= (int)$vendor["quoted_quantity"] ?></td>
                            <td>₹<?= number_format((float)$vendor["unit_price"], 2) ?></td>
                            <td><?= number_format((float)$vendor["tax_rate"], 2) ?>%</td>
                            <td>₹<?= number_format((float)$vendor["price_with_tax"], 2) ?></td>
                            <td>₹<?= number_format((float)$vendor["line_total"], 2) ?></td>
                            <td><?= (int)$vendor["warranty_months"] ?> months</td>
                            <td><?= (int)$vendor["delivery_days"] ?> days</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p><strong>Lowest quoted unit price including tax:</strong>
                ₹<?= number_format((float)$lowestPrice, 2) ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
