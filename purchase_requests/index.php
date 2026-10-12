```php
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

$sql = "SELECT p.id, p.request_number, p.request_date,
               p.status, d.department_name,
               u.full_name,
               COUNT(ri.id) AS item_count
        FROM purchase_requests p
        LEFT JOIN departments d ON p.department_id = d.id
        JOIN users u ON p.requested_by = u.id
        LEFT JOIN request_items ri ON ri.request_id = p.id
        GROUP BY p.id, p.request_number, p.request_date,
                 p.status, d.department_name, u.full_name
        ORDER BY p.id DESC";

$result = $conn->query($sql);
$requests = $result->fetch_all(MYSQLI_ASSOC);
$totalRequests = count($requests);

$pendingCount = 0;
$approvedCount = 0;

foreach ($requests as $request) {
    $status = strtolower(trim($request["status"] ?? ""));

    if ($status === "pending") {
        $pendingCount++;
    }

    if ($status === "approved") {
        $approvedCount++;
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function statusClass($status) {
    $status = strtolower(trim((string)$status));

    if (in_array($status, ["approved", "completed"], true)) {
        return "approved";
    }

    if (in_array($status, ["rejected", "cancelled"], true)) {
        return "rejected";
    }

    if ($status === "pending") {
        return "pending";
    }

    return "neutral";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Purchase Requests | College Inventory</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            display: block;
            padding: 28px 20px;
        }

        .requests-page {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 26px;
        }

        .page-header h1 {
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

        .requests-page .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .summary-value {
            margin: 0;
            font-size: 30px;
            font-weight: 750;
        }

        .requests-panel {
            padding: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .requests-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin: 18px 0;
        }

        .requests-toolbar input {
            max-width: 430px;
            margin: 0;
        }

        .requests-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .requests-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .requests-table th {
            padding: 15px 14px;
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            letter-spacing: .4px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .requests-table td {
            padding: 15px 14px;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
        }

        .requests-table tbody tr:hover {
            background: #f8fafc;
        }

        .request-number {
            color: #1d4ed8;
            font-weight: 700;
        }

        .request-meta {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 3px;
        }

        .request-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .request-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .request-status.approved {
            background: #dcfce7;
            color: #166534;
        }

        .request-status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .request-status.neutral {
            background: #f1f5f9;
            color: #475569;
        }

        .view-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 7px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 650;
            font-size: 12px;
            white-space: nowrap;
        }

        .view-link:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-state h3 {
            color: #334155;
        }

        .table-count {
            margin-top: 15px;
            color: #64748b;
            font-size: 12px;
        }

        .success-message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid #bbf7d0;
            border-radius: 9px;
            background: #f0fdf4;
            color: #166534;
        }

        @media (max-width: 700px) {
            body {
                padding: 16px 10px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .summary-card {
                padding: 16px;
            }

            .summary-value {
                font-size: 25px;
            }

            .requests-panel {
                padding: 15px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions a {
                flex: 1;
            }

            .requests-toolbar input {
                max-width: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
<main class="requests-page">

    <header class="page-header">
        <div>
            <h1>Purchase Requests</h1>
            <p class="subtitle">
                Track departmental requirements and request progress.
            </p>
        </div>

        <div class="header-actions">
            <a class="btn btn-secondary" href="../dashboard.php">
                ← Dashboard
            </a>

            <a class="btn" href="add.php">
                + Create Request
            </a>
        </div>
    </header>

    <?php if (isset($_GET["created"])): ?>
        <div class="success-message">
            Purchase request created successfully.
        </div>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Requests</div>
            <p class="summary-value"><?= $totalRequests ?></p>
        </div>

        <div class="summary-card">
            <div class="summary-label">Pending Requests</div>
            <p class="summary-value"><?= $pendingCount ?></p>
        </div>

        <div class="summary-card">
            <div class="summary-label">Approved Requests</div>
            <p class="summary-value"><?= $approvedCount ?></p>
        </div>
    </section>

    <section class="requests-panel">
        <h2 style="margin-top:0">Request Register</h2>
        <p class="subtitle">
            Review requests, departments, requested items, and statuses.
        </p>

        <div class="requests-toolbar">
            <input
                type="search"
                id="requestSearch"
                placeholder="Search request number, department or requester..."
                aria-label="Search purchase requests"
            >

            <span class="subtitle">
                <?= $totalRequests ?> request(s) recorded
            </span>
        </div>

        <div class="requests-table-wrap">
            <table class="requests-table">
                <thead>
                <tr>
                    <th>Request Number</th>
                    <th>Department</th>
                    <th>Requested By</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody id="requestTableBody">
                <?php if ($totalRequests > 0): ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <span class="request-number">
                                    <?= h($request["request_number"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= h($request["department_name"] ?? "Not assigned") ?>
                            </td>

                            <td>
                                <?= h($request["full_name"]) ?>
                            </td>

                            <td>
                                <?= h($request["request_date"]) ?>
                            </td>

                            <td>
                                <?= (int)$request["item_count"] ?>
                            </td>

                            <td>
                                <span class="request-status <?= statusClass($request["status"]) ?>">
                                    <?= h($request["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="view-link"
                                    href="view.php?id=<?= (int)$request["id"] ?>"
                                >View Request →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h3>No purchase requests yet</h3>
                                Create a request to start tracking departmental requirements.

                                <p style="margin-top:16px">
                                    <a class="btn" href="add.php">
                                        + Create First Request
                                    </a>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="table-count" id="requestCount">
            Showing <?= $totalRequests ?> request(s)
        </p>
    </section>

</main>

<script>
const requestSearch = document.getElementById("requestSearch");
const requestBody = document.getElementById("requestTableBody");
const requestCount = document.getElementById("requestCount");

requestSearch.addEventListener("input", function () {
    const query = this.value.trim().toLowerCase();
    const rows = requestBody.querySelectorAll("tr");
    let visible = 0;

    rows.forEach(function (row) {
        const matches = row.innerText.toLowerCase().includes(query);
        row.hidden = !matches;

        if (matches) {
            visible++;
        }
    });

    requestCount.textContent =
        "Showing " + visible + " matching row(s)";
});
</script>

</body>
</html>
```