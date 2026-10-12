
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/db.php";

$results = [];
$error = "";
$query = trim($_GET["q"] ?? "");

if ($query !== "") {
    $url = "http://localhost:8080/search?q="
         . urlencode($query)
         . "&format=json";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ["Accept: application/json"]
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $error = "Search connection failed: " . $curlError;
    } elseif ($status !== 200) {
        $error = "Search service returned HTTP " . $status;
    } else {
        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data["results"])) {
            $error = "Invalid search response.";
        } else {
            foreach ($data["results"] as $result) {
                $title = $result["title"] ?? "";
                $snippet = $result["content"] ?? "";
                $link = $result["url"] ?? "";

                if (!filter_var($link, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $host = strtolower(
                    parse_url($link, PHP_URL_HOST) ?? ""
                );

                $allowed = [
                    "amazon.in",
                    "www.amazon.in",
                    "flipkart.com",
                    "www.flipkart.com",
                    "indiamart.com",
                    "www.indiamart.com",
                    "dir.indiamart.com",
                    "tradeindia.com",
                    "www.tradeindia.com"
                ];

                $trustedSource = in_array($host, $allowed, true);

                // Extract possible rupee prices from snippets.
                // These are candidates, not verified live prices.
                $price = null;
                $text = $title . " " . $snippet;

                if (preg_match(
                    '/(?:₹|Rs\.?|INR)\s*([\d,]+(?:\.\d{1,2})?)/i',
                    $text,
                    $matches
                )) {
                    $price = (float) str_replace(",", "", $matches[1]);
                }

                $results[] = [
                    "title" => $title,
                    "snippet" => $snippet,
                    "url" => $link,
                    "host" => $host,
                    "trusted" => $trustedSource,
                    "price" => $price
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Automatic Market Search</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <h1>Automatic Market Search</h1>

    <p>
        <a href="../dashboard.php">Dashboard</a> |
        <a href="index.php">Quotations</a> |
        <a href="compare.php">Compare Quotations</a>
    </p>

    <form method="GET">
        <label>Product name and specifications</label>
        <input
            type="text"
            name="q"
            value="<?= htmlspecialchars($query) ?>"
            placeholder="Example: JK A4 copier paper 75 GSM 500 sheets"
            required
        >
        <button type="submit">Search Market</button>
    </form>

    <?php if ($error !== ""): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($query !== "" && $error === ""): ?>
        <h2>Search results</h2>
        <p>
            <?= count($results) ?> results found.
            Prices from snippets are unverified.
        </p>

        <?php foreach ($results as $item): ?>
            <div style="border:1px solid #ccc;padding:12px;margin:12px 0">
                <h3><?= htmlspecialchars($item["title"]) ?></h3>

                <p>
                    Source:
                    <?= htmlspecialchars($item["host"]) ?>
                    <?php if ($item["trusted"]): ?>
                        — Recognized source
                    <?php endif; ?>
                </p>

                <p><?= htmlspecialchars($item["snippet"]) ?></p>

                <p>
                    Candidate price:
                    <?php if ($item["price"] !== null): ?>
                        ₹<?= number_format($item["price"], 2) ?>
                        (unverified)
                    <?php else: ?>
                        Not detected in search snippet
                    <?php endif; ?>
                </p>

                <p>
                    <a href="<?= htmlspecialchars($item["url"]) ?>"
                       target="_blank" rel="noopener noreferrer">
                        Open product or search page
                    </a>
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
```