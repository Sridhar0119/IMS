
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/db.php";

$sql = "SELECT q.id, q.quotation_number, q.quotation_date,
               q.total_amount, q.status,
               v.vendor_name, p.request_number
        FROM quotations q
        JOIN vendors v ON q.vendor_id = v.id
        JOIN purchase_requests p ON q.request_id = p.id
        ORDER BY q.id DESC";

$result = $conn->query($sql);
$quotations = $result->fetch_all(MYSQLI_ASSOC);

$totalQuotations = count($quotations);
$totalValue = 0;
$pendingCount = 0;

foreach ($quotations as $quotation) {
    $totalValue += (float)$quotation["total_amount"];

    if (strtolower(trim((string)$quotation["status"])) === "pending") {
        $pendingCount++;
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function quotationStatusClass($status) {
    $status = strtolower(trim((string)$status));

    if (in_array($status, ["approved", "accepted", "completed"], true)) {
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

    <title>Quotation Management | College Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            display: block;
            padding: 28px 20px;
        }

        .quotation-page {
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
            color: #64748b;
            margin: 0;
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .quotation-page .btn {
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
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .summary-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .summary-value {
            margin: 0;
            color: #0f172a;
            font-size: 28px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }

        .quotation-panel {
            padding: 24px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, .04);
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin: 18px 0;
        }

        .toolbar input {
            max-width: 440px;
            margin: 0;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .quotation-table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        .quotation-table th {
            padding: 15px 14px;
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            letter-spacing: .4px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .quotation-table td {
            padding: 15px 14px;
            border-top: 1px solid #eef2f7;
            vertical-align: middle;
        }

        .quotation-table tbody tr:hover {
            background: #f8fafc;
        }

        .quotation-number {
            color: #1d4ed8;
            font-weight: 700;
        }

        .request-number {
            color: #334155;
            font-weight: 600;
        }

        .amount {
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.neutral {
            background: #f1f5f9;
            color: #475569;
        }

        .action-links {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 650;
            text-decoration: none;
            white-space: nowrap;
        }

        .manage-link {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .manage-link:hover {
            background: #dbeafe;
        }

        .report-link {
            background: #dcfce7;
            color: #166534;
        }

        .report-link:hover {
            background: #bbf7d0;
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
                font-size: 24px;
            }

            .quotation-panel {
                padding: 15px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions a {
                flex: 1;
            }

            .toolbar input {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>

<body>
<main class="quotation-page">

    <header class="page-header">
        <div>
            <h1>Quotation Management</h1>
            <p class="subtitle">
                Review vendor quotations, quoted amounts, and market reports.
            </p>
        </div>

        <div class="header-actions">
            <a class="btn btn-secondary" href="../dashboard.php">
                ← Dashboard
            </a>

            <a class="btn" href="add.php">
                + Register Quotation
            </a>
        </div>
    </header>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Quotations</div>
            <p class="summary-value"><?= $totalQuotations ?></p>
        </div>

        <div class="summary-card">
            <div class="summary-label">Pending Quotations</div>
            <p class="summary-value"><?= $pendingCount ?></p>
        </div>

        <div class="summary-card">
            <div class="summary-label">Combined Quotation Value</div>
            <p class="summary-value">
                ₹<?= number_format($totalValue, 2) ?>
            </p>
        </div>
    </section>

    <section class="quotation-panel">
        <h2 style="margin-top:0">Registered Quotations</h2>
        <p class="subtitle">
            Manage quotation items or open a market report for a quotation.
        </p>

        <div class="toolbar">
            <input
                type="search"
                id="quotationSearch"
                placeholder="Search quotation, vendor or request..."
                aria-label="Search quotations"
            >

            <span class="subtitle">
                <?= $totalQuotations ?> quotation(s) recorded
            </span>
        </div>

        <div class="table-wrap">
            <table class="quotation-table">
                <thead>
                <tr>
                    <th>Quotation No.</th>
                    <th>Vendor</th>
                    <th>Purchase Request</th>
                    <th>Date</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody id="quotationTableBody">
                <?php if ($totalQuotations > 0): ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td>
                                <span class="quotation-number">
                                    <?= h($q["quotation_number"]) ?>
                                </span>
                            </td>

                            <td><?= h($q["vendor_name"]) ?></td>

                            <td>
                                <span class="request-number">
                                    <?= h($q["request_number"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= h($q["quotation_date"] ?? "Not provided") ?>
                            </td>

                            <td class="amount">
                                ₹<?= number_format((float)$q["total_amount"], 2) ?>
                            </td>

                            <td>
                                <span class="status-badge <?= quotationStatusClass($q["status"]) ?>">
                                    <?= h($q["status"]) ?>
                                </span>
                            </td>

                            <td>
                                <div class="action-links">
                                    <a
                                        class="action-link manage-link"
                                        href="items.php?id=<?= (int)$q["id"] ?>"
                                    >Manage Items</a>

                                    <a
                                        class="action-link report-link"
                                        href="market_report.php?id=<?= (int)$q["id"] ?>"
                                    >Market Report</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <h3>No quotations registered yet</h3>
                                Register a vendor quotation to get started.

                                <p style="margin-top:16px">
                                    <a class="btn" href="add.php">
                                        + Register First Quotation
                                    </a>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="table-count" id="quotationCount">
            Showing <?= $totalQuotations ?> quotation(s)
        </p>
    </section>

</main>

<script>
const quotationSearch = document.getElementById("quotationSearch");
const quotationBody = document.getElementById("quotationTableBody");
const quotationCount = document.getElementById("quotationCount");

quotationSearch.addEventListener("input", function () {
    const query = this.value.trim().toLowerCase();
    const rows = quotationBody.querySelectorAll("tr");
    let visible = 0;

    rows.forEach(function (row) {
        const matches = row.innerText.toLowerCase().includes(query);
        row.hidden = !matches;

        if (matches) {
            visible++;
        }
    });

    quotationCount.textContent =
        "Showing " + visible + " matching row(s)";
});
</script>

</body>
</html>
