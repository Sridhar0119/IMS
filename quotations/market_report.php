
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

$quotationId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$quotationId) {
    exit("Invalid quotation ID. Open the report from Quotation Management.");
}

$stmt = $conn->prepare("
    SELECT q.id, q.quotation_number, q.total_amount,
           v.vendor_name, p.request_number
    FROM quotations q
    JOIN vendors v ON v.id = q.vendor_id
    JOIN purchase_requests p ON p.id = q.request_id
    WHERE q.id = ?
");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) {
    exit("Quotation not found.");
}

$stmt = $conn->prepare("
    SELECT qi.id, qi.item_description, qi.brand, qi.model,
           qi.specifications, qi.quantity, qi.unit_price,
           qi.tax_rate
    FROM quotation_items qi
    WHERE qi.quotation_id = ?
");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$reports = [];

foreach ($items as $item) {
    $query = trim(
        $item["item_description"] . " " .
        $item["brand"] . " " .
        $item["model"] . " " .
        $item["specifications"] . " price India"
    );

    $url = "http://localhost:8080/search?q=" .
           urlencode($query) . "&format=json";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $candidates = [];

    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);

        foreach (($data["results"] ?? []) as $r) {
            $title = $r["title"] ?? "";
            $snippet = $r["content"] ?? "";
            $link = $r["url"] ?? "";
            $host = strtolower(parse_url($link, PHP_URL_HOST) ?? "");

            $trusted = false;

            foreach ([
                "amazon.in",
                "flipkart.com",
                "indiamart.com",
                "tradeindia.com",
                "moglix.com",
                "industrybuying.com",
                "jiomart.com",
                "gem.gov.in"
            ] as $domain) {
                if ($host === $domain ||
                    str_ends_with($host, "." . $domain)) {
                    $trusted = true;
                    break;
                }
            }

            if (!$trusted || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }

            if (preg_match(
                '/(?:₹|Rs\.?|INR)\s*([\d,]+(?:\.\d{1,2})?)/i',
                $title . " " . $snippet,
                $match
            )) {
                $price = (float) str_replace(",", "", $match[1]);

                if ($price > 0) {
                    $candidates[] = [
                        "title" => $title,
                        "host" => $host,
                        "url" => $link,
                        "price" => $price
                    ];
                }
            }

            if (count($candidates) >= 5) {
                break;
            }
        }
    }

    usort($candidates, fn($a, $b) =>
        $a["price"] <=> $b["price"]
    );

    $quotedPrice = (float)$item["unit_price"] *
        (1 + (float)$item["tax_rate"] / 100);

    $lowest = $candidates[0] ?? null;
    $difference = $lowest
        ? $quotedPrice - $lowest["price"]
        : null;

    $reports[] = [
        "item" => $item,
        "query" => $query,
        "quoted" => $quotedPrice,
        "lowest" => $lowest,
        "difference" => $difference,
        "candidates" => $candidates
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Automatic Market Price Report</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
body { display:block; padding:25px; }
.container {
    max-width:1150px; margin:auto; padding:25px;
    background:white; border-radius:12px; overflow-x:auto;
}
.card {
    border:1px solid #ddd; border-radius:9px;
    padding:18px; margin:18px 0;
}
table { width:100%; border-collapse:collapse; }
th,td { padding:10px; border:1px solid #ddd; text-align:left; }
th { background:#f1f5f9; }
.notice { background:#fff7df; padding:12px; border-radius:8px; }
</style>
</head>
<body>
<div class="container">

<p>
<a href="../dashboard.php">Dashboard</a> |
<a href="index.php">Quotations</a> |
<a href="compare.php">Compare Quotations</a>
<button onclick="window.print()">Print / Save as PDF</button>
</p>

<h1>Automatic Market Price Report</h1>

<p>
<strong>Quotation:</strong>
<?= h($quotation["quotation_number"]) ?><br>
<strong>Vendor:</strong>
<?= h($quotation["vendor_name"]) ?><br>
<strong>Purchase Request:</strong>
<?= h($quotation["request_number"]) ?><br>
<strong>Quotation Total:</strong>
₹<?= number_format((float)$quotation["total_amount"], 2) ?>
</p>

<div class="notice">
<strong>Price verification:</strong>
Prices extracted from search snippets are unverified candidates.
Confirm the exact product, pack size, GST, shipping, stock and seller
before making a purchase decision.
</div>

<?php if (empty($items)): ?>
    <h2>No quotation items found</h2>
    <p>
        <a href="items.php?id=<?= (int)$quotationId ?>">
            Open Manage Items
        </a> and add the items to this quotation.
    </p>
<?php endif; ?>

<?php foreach ($reports as $report): ?>
    <?php $item = $report["item"]; ?>

    <div class="card">
        <h2><?= h($item["item_description"]) ?></h2>

        <p>
            <strong>Quantity:</strong>
            <?= (int)$item["quantity"] ?><br>

            <strong>Quoted unit price before tax:</strong>
            ₹<?= number_format((float)$item["unit_price"], 2) ?><br>

            <strong>Quoted unit price including entered tax:</strong>
            ₹<?= number_format($report["quoted"], 2) ?><br>

            <strong>Search query:</strong>
            <?= h($report["query"]) ?>
        </p>

        <?php if ($report["lowest"]): ?>
            <p>
                <strong>Lowest detected candidate:</strong>
                ₹<?= number_format($report["lowest"]["price"], 2) ?>
                on <?= h($report["lowest"]["host"]) ?><br>

                <?php if ($report["difference"] > 0): ?>
                    <strong>Suggestion:</strong>
                    Review this item for possible savings of up to
                    ₹<?= number_format(
                        $report["difference"] * (int)$item["quantity"],
                        2
                    ) ?> before verifying tax, shipping and specifications.
                <?php elseif ($report["difference"] < 0): ?>
                    <strong>Suggestion:</strong>
                    The quotation is lower than this detected candidate.
                <?php else: ?>
                    <strong>Suggestion:</strong>
                    The prices match; verify the product listing.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p>
                <strong>Suggestion:</strong>
                No usable price was detected. Try a more specific product
                description or check the market manually.
            </p>
        <?php endif; ?>

        <h3>Detected market listings</h3>

        <?php if (empty($report["candidates"])): ?>
            <p>No priced listings found on recognized websites.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Product listing</th>
                    <th>Website</th>
                    <th>Candidate price</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($report["candidates"] as $c): ?>
                    <tr>
                        <td><?= h($c["title"]) ?></td>
                        <td><?= h($c["host"]) ?></td>
                        <td>
                            ₹<?= number_format($c["price"], 2) ?>
                            (unverified)
                        </td>
                        <td>
                            <a href="<?= h($c["url"]) ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                Check listing
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

</div>
</body>
</html>
```