```php
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

$result = $conn->query(
    "SELECT * FROM vendors ORDER BY id DESC"
);

$vendors = $result->fetch_all(MYSQLI_ASSOC);
$totalVendors = count($vendors);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vendor Management | College Inventory</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            display: block;
            padding: 28px 20px;
        }

        .vendor-page {
            max-width: 1400px;
            margin: 0 auto;
        }

        .vendor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 26px;
        }

        .vendor-header h1 {
            margin-bottom: 6px;
        }

        .subtitle {
            margin: 0;
            color: #64748b;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .vendor-page .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .vendor-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .summary-card {
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .summary-label {
            margin-bottom: 8px;
            color: #64748b;
            font-size: 13px;
        }

        .summary-value {
            margin: 0;
            font-size: 30px;
            font-weight: 750;
        }

        .vendor-panel {
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .vendor-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin: 18px 0;
        }

        .vendor-toolbar input {
            max-width: 420px;
            margin: 0;
        }

        .vendor-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .vendor-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .vendor-table th {
            padding: 15px 14px;
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            white-space: nowrap;
        }

        .vendor-table td {
            padding: 15px 14px;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
        }

        .vendor-table tbody tr:hover {
            background: #f8fafc;
        }

        .vendor-name {
            font-weight: 650;
            color: #0f172a;
        }

        .vendor-meta {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
        }

        .website-link {
            color: #2563eb;
            font-weight: 600;
        }

        .vendor-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .vendor-actions a,
        .vendor-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            margin: 0;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 12px;
        }

        .edit-link {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 650;
        }

        .edit-link:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .delete-button {
            border: 1px solid #fecaca;
            background: #fee2e2;
            color: #b91c1c;
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
            margin-top: 15px;
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 600px) {
            body {
                padding: 16px 10px;
            }

            .vendor-panel {
                padding: 15px;
            }

            .vendor-summary {
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
            }

            .summary-value {
                font-size: 24px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions a {
                flex: 1;
            }

            .vendor-toolbar input {
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
<main class="vendor-page">

    <header class="vendor-header">
        <div>
            <h1>Vendor Management</h1>
            <p class="subtitle">
                Manage suppliers and their contact information.
            </p>
        </div>

        <div class="header-actions">
            <a class="btn btn-secondary" href="../dashboard.php">
                ← Dashboard
            </a>

            <a class="btn" href="add.php">
                + Add Vendor
            </a>
        </div>
    </header>

    <section class="vendor-summary">
        <div class="summary-card">
            <div class="summary-label">Registered Vendors</div>
            <p class="summary-value"><?= $totalVendors ?></p>
        </div>

        <div class="summary-card">
            <div class="summary-label">Vendors With Website</div>
            <p class="summary-value">
                <?php
                $withWebsite = 0;

                foreach ($vendors as $vendor) {
                    if (!empty($vendor["website_url"])) {
                        $withWebsite++;
                    }
                }

                echo $withWebsite;
                ?>
            </p>
        </div>
    </section>

    <section class="vendor-panel">
        <h2 style="margin-top:0">Registered Vendors</h2>

        <p class="subtitle">
            Search suppliers and manage their records.
        </p>

        <div class="vendor-toolbar">
            <input
                type="search"
                id="vendorSearch"
                placeholder="Search vendor, contact, email or phone..."
                aria-label="Search vendors"
            >

            <span class="subtitle">
                <?= $totalVendors ?> vendor(s) recorded
            </span>
        </div>

        <div class="vendor-table-wrap">
            <table class="vendor-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Vendor</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Website</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody id="vendorTableBody">
                <?php if ($totalVendors > 0): ?>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td><?= (int)$vendor["id"] ?></td>

                            <td>
                                <span class="vendor-name">
                                    <?= h($vendor["vendor_name"] ?? "") ?>
                                </span>
                            </td>

                            <td>
                                <?= h($vendor["contact_person"] ?? "Not provided") ?>
                            </td>

                            <td>
                                <?= h($vendor["email"] ?? "Not provided") ?>
                            </td>

                            <td>
                                <?= h($vendor["phone"] ?? "Not provided") ?>
                            </td>

                            <td>
                                <?php if (!empty($vendor["website_url"])): ?>
                                    <a
                                        class="website-link"
                                        href="<?= h($vendor["website_url"]) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >Visit website ↗</a>
                                <?php else: ?>
                                    <span class="vendor-meta">Not provided</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="vendor-actions">
                                    <a
                                        class="edit-link"
                                        href="edit.php?id=<?= (int)$vendor["id"] ?>"
                                    >Edit</a>

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        onsubmit="return confirm('Delete this vendor?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$vendor["id"] ?>"
                                        >

                                        <button
                                            class="delete-button"
                                            type="submit"
                                        >Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h3>No vendors registered yet</h3>
                                Add your first vendor to start managing suppliers.

                                <p style="margin-top:16px">
                                    <a class="btn" href="add.php">
                                        + Add First Vendor
                                    </a>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="table-count" id="vendorCount">
            Showing <?= $totalVendors ?> vendor(s)
        </p>
    </section>

</main>

<script>
const vendorSearch = document.getElementById("vendorSearch");
const vendorBody = document.getElementById("vendorTableBody");
const vendorCount = document.getElementById("vendorCount");

vendorSearch.addEventListener("input", function () {
    const query = this.value.trim().toLowerCase();
    const rows = vendorBody.querySelectorAll("tr");
    let visible = 0;

    rows.forEach(function (row) {
        const matches = row.innerText.toLowerCase().includes(query);
        row.hidden = !matches;

        if (matches) {
            visible++;
        }
    });

    vendorCount.textContent =
        "Showing " + visible + " matching row(s)";
});
</script>

</body>
</html>
```