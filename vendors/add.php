
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

$errors = [];
$vendor_name = "";
$contact_person = "";
$email = "";
$phone = "";
$address = "";
$website_url = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vendor_name = trim($_POST["vendor_name"] ?? "");
    $contact_person = trim($_POST["contact_person"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $website_url = trim($_POST["website_url"] ?? "");

    if ($vendor_name === "") {
        $errors[] = "Vendor name is required.";
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    if ($website_url !== "" &&
        (!filter_var($website_url, FILTER_VALIDATE_URL) ||
         !in_array(strtolower(parse_url($website_url, PHP_URL_SCHEME) ?? ""),
                   ["http", "https"], true))) {
        $errors[] = "Enter a valid website URL beginning with http:// or https://.";
    }

    if (!$errors) {
        $stmt = $conn->prepare(
            "INSERT INTO vendors
             (vendor_name, contact_person, email, phone, address, website_url)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssss",
            $vendor_name,
            $contact_person,
            $email,
            $phone,
            $address,
            $website_url
        );

        $stmt->execute();
        $stmt->close();

        header("Location: index.php?added=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Vendor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { display:block; padding:30px; }
        .form-card {
            max-width:700px; margin:auto; background:white;
            padding:25px; border-radius:12px;
        }
        label { display:block; margin-top:15px; }
        input,textarea {
            width:100%; padding:10px; margin-top:6px;
            border:1px solid #cbd5e1; border-radius:6px;
        }
        .error { background:#fee2e2; color:#991b1b; padding:10px; }
    </style>
</head>
<body>
<div class="form-card">
    <h1>Register Vendor</h1>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Vendor Name *</label>
        <input name="vendor_name" required value="<?= h($vendor_name) ?>">

        <label>Contact Person</label>
        <input name="contact_person" value="<?= h($contact_person) ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?= h($email) ?>">

        <label>Phone</label>
        <input name="phone" value="<?= h($phone) ?>">

        <label>Address</label>
        <textarea name="address"><?= h($address) ?></textarea>

        <label>Website URL</label>
        <input type="url" name="website_url"
               placeholder="https://example.com"
               value="<?= h($website_url) ?>">

        <button type="submit">Save Vendor</button>
    </form>

    <p><a href="index.php">Back to Vendors</a></p>
</div>
</body>
</html>
