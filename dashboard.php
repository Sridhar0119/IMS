<?php

session_start();

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");

    exit;

}

require_once __DIR__ . "/config/db.php";

$items = (int) $conn->query(

    "SELECT COUNT(*) AS total FROM inventory_items"

)->fetch_assoc()["total"];

$vendors = (int) $conn->query(

    "SELECT COUNT(*) AS total FROM vendors"

)->fetch_assoc()["total"];

$quotations = (int) $conn->query(

    "SELECT COUNT(*) AS total FROM quotations"

)->fetch_assoc()["total"];

$requests = (int) $conn->query(

    "SELECT COUNT(*) AS total FROM purchase_requests"

)->fetch_assoc()["total"];

$recentQuotations = $conn->query(

    "SELECT q.id, q.quotation_number, q.total_amount,

            q.status, q.created_at, v.vendor_name

     FROM quotations q

     LEFT JOIN vendors v ON q.vendor_id = v.id

     ORDER BY q.created_at DESC

     LIMIT 5"

);

function h($value) {

    return htmlspecialchars(

        (string)($value ?? ""),

        ENT_QUOTES,

        "UTF-8"

    );

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | College Inventory</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>

        * {

            box-sizing: border-box;

        }

        body {

            display: block;

            padding: 0;

            background: #f4f7fb;

            color: #172033;

        }

        a {

            text-decoration: none;

        }

        .app-layout {

            display: flex;

            min-height: 100vh;

        }

        /* Sidebar */

        .sidebar {

            width: 250px;

            flex-shrink: 0;

            padding: 28px 18px;

            background: #111c35;

            color: #fff;

        }

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 0 10px 30px;

            border-bottom: 1px solid #29344c;

        }

        .brand-icon {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 10px;
            background: #ffffff;
            overflow: hidden;
        }

        .brand-icon img {
            display: block;
            width: 100%;
            height: 100%;
            padding: 3px;
            object-fit: contain;
        }

        .brand h2 {

            margin: 0;

            color: white;

            font-size: 16px;

        }

        .brand small {

            color: #aab6cc;

            font-size: 11px;

        }

        .nav-label {

            margin: 28px 10px 12px;

            color: #8e9bb4;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 1.5px;

            text-transform: uppercase;

        }

        .nav-link {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px 13px;

            margin: 5px 0;

            border-radius: 9px;

            color: #c5cee0;

            font-size: 13px;

            font-weight: 500;

            transition: 0.2s ease;

        }

        .nav-link:hover,

        .nav-link.active {

            background: #263653;

            color: white;

        }

        .nav-link.active {

            box-shadow: inset 3px 0 #60a5fa;

        }

        .nav-icon {

            width: 22px;

            text-align: center;

            font-size: 17px;

        }

        .sidebar-bottom {

            margin-top: 35px;

            padding-top: 20px;

            border-top: 1px solid #29344c;

        }

        .logout-link {

            color: #fca5a5;

        }

        /* Main content */

        .main-content {

            min-width: 0;

            flex: 1;

            padding: 30px;

        }

        .topbar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 32px;

        }

        .topbar h1 {

            margin: 0 0 5px;

            font-size: 27px;

            letter-spacing: -0.7px;

        }

        .subtitle {

            margin: 0;

            color: #718096;

            font-size: 13px;

        }

        .profile {

            display: flex;

            align-items: center;

            gap: 11px;

            padding: 10px 14px;

            border: 1px solid #e5eaf2;

            border-radius: 12px;

            background: white;

        }

        .avatar {

            display: grid;

            place-items: center;

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background: #dbeafe;

            color: #1d4ed8;

            font-weight: 800;

        }

        .profile strong {

            display: block;

            font-size: 12px;

        }

        .profile small {

            color: #718096;

            font-size: 11px;

        }

        /* Statistics */

        .section-heading {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 12px;

            margin: 0 0 17px;

        }

        .section-heading h2 {

            margin: 0;

            font-size: 17px;

        }

        .stats-grid {

            display: grid;

            grid-template-columns: repeat(4, minmax(0, 1fr));

            gap: 18px;

        }

        .stat-card {

            min-width: 0;

            padding: 22px;

            border: 1px solid #e8edf5;

            border-radius: 15px;

            background: white;

            box-shadow: 0 4px 15px rgba(20, 35, 60, 0.035);

            transition: transform 0.2s, box-shadow 0.2s;

        }

        .stat-card:hover {

            transform: translateY(-3px);

            box-shadow: 0 10px 24px rgba(20, 35, 60, 0.07);

        }

        .stat-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 8px;

            margin-bottom: 18px;

        }

        .stat-icon {

            display: grid;

            place-items: center;

            width: 44px;

            height: 44px;

            border-radius: 12px;

            font-size: 21px;

        }

        .blue { background: #dbeafe; }

        .purple { background: #ede9fe; }

        .green { background: #dcfce7; }

        .orange { background: #ffedd5; }

        .stat-label {

            margin: 0 0 5px;

            color: #718096;

            font-size: 12px;

        }

        .stat-number {

            margin: 0;

            color: #172033;

            font-size: 30px;

            font-weight: 750;

            line-height: 1.2;

        }

        .stat-note {

            margin: 12px 0 0;

            color: #8a96a8;

            font-size: 11px;

        }

        /* Quick actions */

        .quick-actions {

            display: grid;

            grid-template-columns: repeat(3, minmax(0, 1fr));

            gap: 14px;

            margin-bottom: 32px;

        }

        .quick-card {

            display: flex;

            align-items: center;

            gap: 13px;

            min-width: 0;

            padding: 18px;

            border: 1px solid #e6ebf3;

            border-radius: 13px;

            background: white;

            color: #172033;

            transition: 0.2s ease;

        }

        .quick-card:hover {

            border-color: #93c5fd;

            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.07);

            transform: translateY(-2px);

            color: #172033;

        }

        .quick-icon {

            display: grid;

            place-items: center;

            flex-shrink: 0;

            width: 42px;

            height: 42px;

            border-radius: 11px;

            background: #eff6ff;

            font-size: 20px;

        }

        .quick-card strong {

            display: block;

            font-size: 12px;

        }

        .quick-card small {

            display: block;

            margin-top: 4px;

            color: #7b8798;

            font-size: 11px;

        }

        /* Recent quotations */

        .recent-panel {

            overflow: hidden;

            border: 1px solid #e6ebf3;

            border-radius: 15px;

            background: white;

            box-shadow: 0 4px 15px rgba(20, 35, 60, 0.035);

        }

        .panel-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 12px;

            padding: 22px;

            border-bottom: 1px solid #edf0f5;

        }

        .panel-header h2 {

            margin: 0;

            font-size: 16px;

        }

        .view-all {

            font-size: 12px;

            font-weight: 700;

        }

        .table-wrap {

            width: 100%;

            overflow-x: auto;

        }

        .recent-panel table {

            width: 100%;

            border-collapse: collapse;

        }

        .recent-panel th {

            padding: 14px 20px;

            background: #f8fafc;

            color: #758196;

            font-size: 10px;

            text-align: left;

            text-transform: uppercase;

            letter-spacing: 0.7px;

        }

        .recent-panel td {

            padding: 16px 20px;

            border-top: 1px solid #f0f2f6;

            font-size: 12px;

            white-space: nowrap;

        }

        .recent-panel tbody tr:hover {

            background: #fafcff;

        }

        .quote-number {

            color: #1d4ed8;

            font-weight: 700;

        }

        .status-badge {

            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            background: #f1f5f9;

            color: #475569;

            font-size: 10px;

            font-weight: 700;

        }

        .report-link {

            color: #2563eb;

            font-weight: 700;

        }

        .report-link:hover {

            text-decoration: underline;

        }

        .empty-state {

            padding: 35px 20px;

            color: #718096;

            text-align: center;

        }

        .footer {

            margin-top: 28px;

            color: #8792a5;

            font-size: 11px;

            text-align: center;

        }

        /* Responsive layout */

        @media (max-width: 1100px) {

            .stats-grid {

                grid-template-columns: repeat(2, minmax(0, 1fr));

            }

            .quick-actions {

                grid-template-columns: repeat(2, minmax(0, 1fr));

            }

            .main-content {

                padding: 24px;

            }

        }

        @media (max-width: 720px) {

            .app-layout {

                display: block;

            }

            .sidebar {

                width: 100%;

                padding: 16px;

            }

            .brand {

                padding-bottom: 15px;

            }

            .nav-label {

                margin-top: 18px;

            }

            .sidebar nav {

                display: grid;

                grid-template-columns: repeat(2, minmax(0, 1fr));

                gap: 3px 8px;

            }

            .sidebar-bottom {

                margin-top: 15px;

                padding-top: 10px;

            }

            .main-content {

                padding: 22px 15px;

            }

            .topbar {

                align-items: flex-start;

                flex-direction: column;

                margin-bottom: 25px;

            }

            .topbar h1 {

                font-size: 23px;

            }

            .quick-actions {

                grid-template-columns: 1fr;

            }

            .stat-card {

                padding: 17px;

            }

            .stat-number {

                font-size: 26px;

            }

        }

        @media (max-width: 380px) {

            .stats-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>

<body>

<div class="app-layout">

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-icon">
                <img src="assets/css/images/gmit-logo.png" alt="GMIT Davangere Logo">
            </div>

            <div>

                <h2>College Inventory</h2>

                <small>Management System</small>

            </div>

        </div>

        <p class="nav-label">Workspace</p>

        <nav>

            <a class="nav-link active" href="dashboard.php">

                <span class="nav-icon">⌂</span> Dashboard

            </a>

            <a class="nav-link" href="inventory/index.php">

                <span class="nav-icon">▤</span> Inventory

            </a>

            <a class="nav-link" href="vendors/index.php">

                <span class="nav-icon">♧</span> Vendors

            </a>

            <a class="nav-link" href="purchase_requests/index.php">

                <span class="nav-icon">☷</span> Purchase Requests

            </a>

            <a class="nav-link" href="quotations/index.php">

                <span class="nav-icon">▧</span> Quotations

            </a>

            <a class="nav-link" href="quotations/compare.php">

                <span class="nav-icon">⇄</span> Compare Quotations

            </a>

            <a class="nav-link" href="quotations/market_prices.php">

                <span class="nav-icon">⌕</span> Market Prices

            </a>

        </nav>

        <div class="sidebar-bottom">

            <a class="nav-link logout-link" href="logout.php">

                <span class="nav-icon">↪</span> Logout

            </a>

        </div>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div>

                <h1>Dashboard</h1>

                <p class="subtitle">

                    Monitor inventory, purchasing and vendor quotations.

                </p>

            </div>

            <div class="profile">

                <div class="avatar">

                    <?= h(strtoupper(substr(

                        $_SESSION["full_name"] ?? "A",

                        0,

                        1

                    ))) ?>

                </div>

                <div>

                    <strong><?= h($_SESSION["full_name"] ?? "Administrator") ?></strong>

                    <small>Administrator</small>

                </div>

            </div>

        </header>

        <div class="section-heading">

            <h2>Overview</h2>

        </div>

        <section class="stats-grid">

            <article class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">Inventory Items</span>

                    <div class="stat-icon blue">▤</div>

                </div>

                <p class="stat-number"><?= $items ?></p>

                <p class="stat-note">Items recorded in the system</p>

            </article>

            <article class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">Registered Vendors</span>

                    <div class="stat-icon purple">♧</div>

                </div>

                <p class="stat-number"><?= $vendors ?></p>

                <p class="stat-note">Available supplier records</p>

            </article>

            <article class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">Purchase Requests</span>

                    <div class="stat-icon orange">☷</div>

                </div>

                <p class="stat-number"><?= $requests ?></p>

                <p class="stat-note">Requests recorded in the system</p>

            </article>

            <article class="stat-card">

                <div class="stat-top">

                    <span class="stat-label">Quotations</span>

                    <div class="stat-icon green">▧</div>

                </div>

                <p class="stat-number"><?= $quotations ?></p>

                <p class="stat-note">Vendor quotations received</p>

            </article>

        </section>

        <div class="section-heading" style="margin-top: 34px;">

            <h2>Quick Actions</h2>

        </div>

        <section class="quick-actions">

            <a class="quick-card" href="inventory/index.php">

                <span class="quick-icon">＋</span>

                <span>

                    <strong>Manage Inventory</strong>

                    <small>View and manage stock items</small>

                </span>

            </a>

            <a class="quick-card" href="purchase_requests/index.php">

                <span class="quick-icon">☷</span>

                <span>

                    <strong>Purchase Requests</strong>

                    <small>Review purchasing requirements</small>

                </span>

            </a>

            <a class="quick-card" href="quotations/index.php">

                <span class="quick-icon">▧</span>

                <span>

                    <strong>Quotation Management</strong>

                    <small>View vendor quotations</small>

                </span>

            </a>

            <a class="quick-card" href="quotations/compare.php">

                <span class="quick-icon">⇄</span>

                <span>

                    <strong>Compare Quotations</strong>

                    <small>Compare supplier offers</small>

                </span>

            </a>

            <a class="quick-card" href="quotations/market_prices.php">

                <span class="quick-icon">⌕</span>

                <span>

                    <strong>Search Market Prices</strong>

                    <small>Find online price listings</small>

                </span>

            </a>

            <a class="quick-card" href="quotations/index.php">

                <span class="quick-icon">▣</span>

                <span>

                    <strong>Market Reports</strong>

                    <small>Open a quotation to view its report</small>

                </span>

            </a>

        </section>

        <section class="recent-panel">

            <div class="panel-header">

                <h2>Recent Quotations</h2>

                <a class="view-all" href="quotations/index.php">

                    View all →

                </a>

            </div>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>Quotation No.</th>

                            <th>Vendor</th>

                            <th>Total Amount</th>

                            <th>Status</th>

                            <th>Report</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($recentQuotations->num_rows > 0): ?>

                        <?php while ($q = $recentQuotations->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <span class="quote-number">

                                        <?= h($q["quotation_number"]) ?>

                                    </span>

                                </td>

                                <td><?= h($q["vendor_name"] ?: "Unknown vendor") ?></td>

                                <td>

                                    ₹<?= number_format((float)$q["total_amount"], 2) ?>

                                </td>

                                <td>

                                    <span class="status-badge">

                                        <?= h(ucwords(str_replace(

                                            "_",

                                            " ",

                                            $q["status"] ?: "Not specified"

                                        ))) ?>

                                    </span>

                                </td>

                                <td>

                                    <a class="report-link"

                                       href="quotations/market_report.php?id=<?= (int)$q["id"] ?>">

                                        View report →

                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="5" class="empty-state">

                                No quotations found yet.

                                <br>

                                <a href="quotations/index.php">

                                    Open quotation management

                                </a>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <footer class="footer">

            College Inventory &amp; Smart Quotation Management System

        </footer>

    </main>

</div>

</body>

</html>
