
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Invalid inventory item ID.");
}

$stmt = $conn->prepare(
    "SELECT * FROM inventory_items WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    exit("Inventory item not found.");
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $item_name = trim($_POST["item_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $specifications = trim($_POST["specifications"] ?? "");
    $stock = filter_var($_POST["stock_quantity"] ?? null, FILTER_VALIDATE_INT);
    $reorder = filter_var($_POST["reorder_level"] ?? null, FILTER_VALIDATE_INT);

    if ($item_name === "") {
        $errors[] = "Item name is required.";
    }

    if ($stock === false || $stock === null || $stock < 0) {
        $errors[] = "Stock must be a non-negative whole number.";
    }

    if ($reorder === false || $reorder === null || $reorder < 0) {
        $errors[] = "Reorder level must be a non-negative whole number.";
    }

    if (!$errors) {
        $update = $conn->prepare(
            "UPDATE inventory_items
             SET item_name = ?, category = ?, brand = ?,
                 model = ?, specifications = ?,
                 stock_quantity = ?, reorder_level = ?
             WHERE id = ?"
        );

        $update->bind_param(
            "sssssiii",
            $item_name,
            $category,
            $brand,
            $model,
            $specifications,
            $stock,
            $reorder,
            $id
        );

        $update->execute();
        $update->close();

        header("Location: index.php?updated=1");
        exit;
    }

    // Preserve the submitted values if validation fails.
    $item["item_name"] = $item_name;
    $item["category"] = $category;
    $item["brand"] = $brand;
    $item["model"] = $model;
    $item["specifications"] = $specifications;
    $item["stock_quantity"] = $_POST["stock_quantity"];
    $item["reorder_level"] = $_POST["reorder_level"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Inventory Item</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { display: block; padding: 30px; }
        .form-card {
            max-width: 700px; margin: auto; background: white;
            padding: 25px; border-radius: 12px;
        }
        label { display: block; margin-top: 15px; }
        input, textarea {
            width: 100%; padding: 10px; margin-top: 6px;
            border: 1px solid #cbd5e1; border-radius: 6px;
        }
        .error { color: #991b1b; background: #fee2e2; padding: 10px; }
    </style>
</head>
<body>
<div class="form-card">
    <h1>Edit Inventory Item</h1>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Item Name *</label>
        <input name="item_name" required value="<?= h($item["item_name"]) ?>">

        <label>Category</label>
        <input name="category" value="<?= h($item["category"]) ?>">

        <label>Brand</label>
        <input name="brand" value="<?= h($item["brand"]) ?>">

        <label>Model</label>
        <input name="model" value="<?= h($item["model"]) ?>">

        <label>Specifications</label>
        <textarea name="specifications"><?= h($item["specifications"]) ?></textarea>

        <label>Stock Quantity</label>
        <input type="number" name="stock_quantity" min="0" required
               value="<?= h($item["stock_quantity"]) ?>">

        <label>Reorder Level</label>
        <input type="number" name="reorder_level" min="0" required
               value="<?= h($item["reorder_level"]) ?>">

        <button type="submit">Update Item</button>
    </form>

    <p><a href="index.php">Back to Inventory</a></p>
</div>
</body>
</html>
