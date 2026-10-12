
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

$sql = "SELECT i.*, d.department_name
        FROM inventory_items i
        LEFT JOIN departments d ON i.department_id = d.id
        ORDER BY i.id DESC";

$result = $conn->query($sql);

$totalItems = $result->num_rows;
$lowStock = 0;
$stockRows = [];

while ($row = $result->fetch_assoc()) {
    $stockRows[] = $row;

    if ((int)$row["stock_quantity"] <= (int)$row["reorder_level"]) {
        $lowStock++;
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management | College Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            display: block;
            padding: 28px 20px;
        }

        .inventory-page {
            max-width: 1400px;
            margin: 0 auto;
        }

        .inventory-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 26px;
        }

        .inventory-header h1 {
            margin-bottom: 6px;
        }

        .subtitle {
            color: #64748b;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .inventory-page .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .inventory-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .inventory-stat {
            padding: 22px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .inventory-stat .label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .inventory-stat .value {
            font-size: 30px;
            font-weight: 750;
            line-height: 1.2;
            margin: 0;
        }

        .inventory-panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .inventory-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin: 18px 0;
        }

        .inventory-toolbar input {
            max-width: 400px;
            margin: 0;
        }

        .inventory-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .inventory-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            letter-spacing: .4px;
            text-transform: uppercase;
            padding: 15px 14px;
            white-space: nowrap;
        }

        .inventory-table td {
            padding: 15px 14px;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
        }

        .inventory-table tbody tr:hover {
            background: #f8fafc;
        }

        .item-name {
            font-weight: 650;
            color: #0f172a;
        }

        .item-meta {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 3px;
        }

        .stock-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .stock-badge.low {
            color: #b91c1c;
            background: #fee2e2;
        }

        .stock-badge.ok {
            color: #166534;
            background: #dcfce7;
        }

        .item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .item-actions a,
        .item-actions button {
            margin: 0;
            min-height: 34px;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
        }

        .edit-link {
            display: inline-flex;
            align-items: center;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 650;
        }

        .edit-link:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .delete-button {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .delete-button:hover {
            background: #fecaca;
            color: #991b1b;
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-state h3 {
            color: #334155;
            margin-bottom: 8px;
        }

        .table-count {
            color: #64748b;
            font-size: 12px;
            margin-top: 15px;
        }

        @media (max-width: 600px) {
            body {
                padding: 16px 10px;
            }

            .inventory-panel {
                padding: 15px;
            }

            .inventory-stats {
                gap: 10px;
            }

            .inventory-stat {
                padding: 15px;
            }

            .inventory-stat .value {
                font-size: 24px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions a {
                flex: 1;
            }

            .inventory-toolbar input {
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
<main class="inventory-page">

    <header class="inventory-header">
        <div>
            <h1>Inventory Management</h1>
            <p class="subtitle">
                Track equipment, monitor stock, and manage inventory records.
            </p>
        </div>

        <div class="header-actions">
            <a class="btn btn-secondary" href="../dashboard.php">
                ← Dashboard
            </a>

            <a class="btn" href="add.php">
                + Add Item
            </a>
        </div>
    </header>

    <section class="inventory-stats">
        <div class="inventory-stat">
            <div class="label">Total Inventory Items</div>
            <p class="value"><?= $totalItems ?></p>
        </div>

        <div class="inventory-stat">
            <div class="label">Items At or Below Reorder Level</div>
            <p class="value"><?= $lowStock ?></p>
        </div>
    </section>

    <section class="inventory-panel">
        <h2 style="margin-top:0">Inventory Items</h2>
        <p class="subtitle">Search and manage your registered equipment.</p>

        <div class="inventory-toolbar">
            <input
                type="search"
                id="inventorySearch"
                placeholder="Search by item, category, brand or department..."
                aria-label="Search inventory items"
            >

            <span class="subtitle">
                <?= $totalItems ?> item(s) recorded
            </span>
        </div>

        <div class="inventory-table-wrap">
            <table class="inventory-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Item Details</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody id="inventoryTableBody">
                <?php if ($totalItems > 0): ?>
                    <?php foreach ($stockRows as $item): ?>
                        <?php
                        $quantity = (int)$item["stock_quantity"];
                        $reorder = (int)$item["reorder_level"];
                        $isLow = $quantity <= $reorder;
                        ?>
                        <tr>
                            <td><?= (int)$item["id"] ?></td>

                            <td>
                                <span class="item-name">
                                    <?= h($item["item_name"] ?? "") ?>
                                </span>
                                <span class="item-meta">
                                    <?= h($item["brand"] ?? "No brand") ?>
                                    /
                                    <?= h($item["model"] ?? "No model") ?>
                                </span>
                            </td>

                            <td><?= h($item["category"] ?? "Uncategorized") ?></td>

                            <td>
                                <span class="stock-badge <?= $isLow ? 'low' : 'ok' ?>">
                                    <?= $quantity ?> units
                                </span>
                                <span class="item-meta">
                                    Reorder at <?= $reorder ?>
                                </span>
                            </td>

                            <td>
                                <?= h($item["department_name"] ?? "Not assigned") ?>
                            </td>

                            <td>
                                <div class="item-actions">
                                    <a
                                        class="edit-link"
                                        href="edit.php?id=<?= (int)$item["id"] ?>"
                                    >Edit</a>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        onsubmit="return confirm('Delete this inventory item?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$item["id"] ?>"
                                        >
                                        <button class="delete-button" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <h3>No inventory items yet</h3>
                                Add your first item to start managing stock.
                                <p style="margin-top:16px">
                                    <a class="btn" href="add.php">+ Add First Item</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="table-count" id="inventoryCount">
            Showing <?= $totalItems ?> item(s)
        </p>
    </section>
</main>

<script>
const inventorySearch = document.getElementById("inventorySearch");
const inventoryBody = document.getElementById("inventoryTableBody");
const inventoryCount = document.getElementById("inventoryCount");

inventorySearch.addEventListener("input", function () {
    const query = this.value.trim().toLowerCase();
    const rows = inventoryBody.querySelectorAll("tr");
    let visible = 0;

    rows.forEach(function (row) {
        const matches = row.innerText.toLowerCase().includes(query);
        row.hidden = !matches;

        if (matches) {
            visible++;
        }
    });

    inventoryCount.textContent =
        "Showing " + visible + " matching row(s)";
});
</script>

</body>
</html>
