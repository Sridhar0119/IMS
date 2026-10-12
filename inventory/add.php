
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

$errors = [];

$item_name = "";
$category = "";
$brand = "";
$model = "";
$specifications = "";
$unit = "piece";
$stock_quantity = "0";
$reorder_level = "0";
$department_id = "";

$departments = $conn->query(
    "SELECT id, department_name FROM departments ORDER BY department_name"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $item_name = trim($_POST["item_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $specifications = trim($_POST["specifications"] ?? "");
    $unit = trim($_POST["unit"] ?? "piece");
    $stock_quantity = trim($_POST["stock_quantity"] ?? "0");
    $reorder_level = trim($_POST["reorder_level"] ?? "0");
    $department_id = trim($_POST["department_id"] ?? "");

    if ($item_name === "") {
        $errors[] = "Item name is required.";
    }

    if (!in_array($unit, ["piece", "box", "kg", "litre", "set"], true)) {
        $errors[] = "Select a valid unit.";
    }

    if (
        filter_var($stock_quantity, FILTER_VALIDATE_INT) === false ||
        (int) $stock_quantity < 0
    ) {
        $errors[] = "Stock must be a non-negative whole number.";
    }

    if (
        filter_var($reorder_level, FILTER_VALIDATE_INT) === false ||
        (int) $reorder_level < 0
    ) {
        $errors[] = "Reorder level must be a non-negative whole number.";
    }

    $departmentValue = null;

    if ($department_id !== "") {
        if (
            filter_var($department_id, FILTER_VALIDATE_INT) === false ||
            (int) $department_id < 1
        ) {
            $errors[] = "Select a valid department.";
        } else {
            $check = $conn->prepare(
                "SELECT id FROM departments WHERE id = ?"
            );
            $deptId = (int) $department_id;
            $check->bind_param("i", $deptId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$exists) {
                $errors[] = "Selected department does not exist.";
            } else {
                $departmentValue = $deptId;
            }
        }
    }

    if (!$errors) {
        $stock = (int) $stock_quantity;
        $reorder = (int) $reorder_level;

        $stmt = $conn->prepare(
            "INSERT INTO inventory_items
            (item_name, category, brand, model, specifications,
             unit, stock_quantity, reorder_level, department_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssssiii",
            $item_name,
            $category,
            $brand,
            $model,
            $specifications,
            $unit,
            $stock,
            $reorder,
            $departmentValue
        );

        $stmt->execute();
        $stmt->close();

        header("Location: index.php?added=1");
        exit;
    }
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Inventory Item</title>
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
        .error {
            color:#991b1b; background:#fee2e2;
            padding:10px; border-radius:6px;
        }
    </style>
</head>
<body>
<div class="form-card">
    <h1>Add Inventory Item</h1>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Item Name *</label>
        <input name="item_name" required value="<?= h($item_name) ?>">

        <label>Category</label>
        <input name="category" value="<?= h($category) ?>">

        <label>Brand</label>
        <input name="brand" value="<?= h($brand) ?>">

        <label>Model</label>
        <input name="model" value="<?= h($model) ?>">

        <label>Specifications</label>
        <textarea name="specifications"><?= h($specifications) ?></textarea>

        <label>Unit</label>
        <select name="unit">
            <?php foreach (["piece", "box", "kg", "litre", "set"] as $option): ?>
                <option value="<?= h($option) ?>" <?= $unit === $option ? "selected" : "" ?>>
                    <?= h(ucfirst($option)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Stock Quantity</label>
        <input type="number" name="stock_quantity" min="0"
               required value="<?= h($stock_quantity) ?>">

        <label>Reorder Level</label>
        <input type="number" name="reorder_level" min="0"
               required value="<?= h($reorder_level) ?>">

        <label>Department</label>
        <select name="department_id">
            <option value="">Not assigned</option>
            <?php while ($dept = $departments->fetch_assoc()): ?>
                <option value="<?= (int) $dept["id"] ?>"
                    <?= (string) $dept["id"] === $department_id ? "selected" : "" ?>>
                    <?= h($dept["department_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">Save Item</button>
    </form>

    <p><a href="index.php">Back to Inventory</a></p>
</div>
</body>
</html>
