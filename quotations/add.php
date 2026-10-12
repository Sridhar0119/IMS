
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

$vendors = $conn->query(
    "SELECT id, vendor_name FROM vendors ORDER BY vendor_name"
);

$requests = $conn->query(
    "SELECT id, request_number FROM purchase_requests
     ORDER BY id DESC"
);

$errors = [];
$quotation_number = "";
$vendor_id = "";
$request_id = "";
$quotation_date = date("Y-m-d");
$valid_until = "";
$total_amount = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $quotation_number = trim($_POST["quotation_number"] ?? "");
    $vendor_id = trim($_POST["vendor_id"] ?? "");
    $request_id = trim($_POST["request_id"] ?? "");
    $quotation_date = trim($_POST["quotation_date"] ?? "");
    $valid_until = trim($_POST["valid_until"] ?? "");
    $total_amount = trim($_POST["total_amount"] ?? "");

    $vendor = filter_var($vendor_id, FILTER_VALIDATE_INT);
    $request = filter_var($request_id, FILTER_VALIDATE_INT);
    $total = filter_var($total_amount, FILTER_VALIDATE_FLOAT);

    if ($quotation_number === "") {
        $errors[] = "Quotation number is required.";
    }

    if ($vendor === false || $vendor < 1) {
        $errors[] = "Select a valid vendor.";
    }

    if ($request === false || $request < 1) {
        $errors[] = "Select a valid purchase request.";
    }

    if ($total === false || !is_finite((float)$total) ||
        $total < 0 || $total > 999999999999.99) {
        $errors[] = "Enter a valid non-negative total.";
    }

    $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

    if (!preg_match($datePattern, $quotation_date) ||
        !checkdate(
            (int)substr($quotation_date, 5, 2),
            (int)substr($quotation_date, 8, 2),
            (int)substr($quotation_date, 0, 4)
        )) {
        $errors[] = "Enter a valid quotation date.";
    }

    if ($valid_until !== "" &&
        (!preg_match($datePattern, $valid_until) ||
         !checkdate(
             (int)substr($valid_until, 5, 2),
             (int)substr($valid_until, 8, 2),
             (int)substr($valid_until, 0, 4)
         ))) {
        $errors[] = "Enter a valid quotation expiry date.";
    }

    if (!$errors && $valid_until !== "" &&
        $valid_until < $quotation_date) {
        $errors[] = "Expiry date cannot be before quotation date.";
    }

    if (isset($_FILES["quotation_file"]) &&
        $_FILES["quotation_file"]["error"] !== UPLOAD_ERR_OK) {
        $errors[] = "Select a quotation file smaller than the server upload limit.";
    }

    $file = $_FILES["quotation_file"] ?? null;
    $allowedExtensions = ["pdf", "xls", "xlsx"];
    $extension = $file
        ? strtolower(pathinfo($file["name"], PATHINFO_EXTENSION))
        : "";

    if (!$file || $file["error"] !== UPLOAD_ERR_OK ||
        !in_array($extension, $allowedExtensions, true)) {
        $errors[] = "Upload a PDF, XLS, or XLSX quotation file.";
    }

    if ($file && $file["error"] === UPLOAD_ERR_OK) {
        if ($file["size"] <= 0 || $file["size"] > 5 * 1024 * 1024) {
            $errors[] = "File must be no larger than 5 MB.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file["tmp_name"]);

            $allowedMimes = [
                "pdf"  => ["application/pdf"],
                "xls"  => ["application/vnd.ms-excel",
                           "application/octet-stream"],
                "xlsx" => ["application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                           "application/zip",
                           "application/octet-stream"]
            ];

            if (!in_array($mime, $allowedMimes[$extension] ?? [], true)) {
                $errors[] = "File content does not match an allowed file type.";
            }
        }
    }

    // Confirm that the selected vendor and request exist.
    if (!$errors) {
        $check = $conn->prepare(
            "SELECT id FROM vendors WHERE id = ?"
        );
        $check->bind_param("i", $vendor);
        $check->execute();
        $vendorExists = $check->get_result()->num_rows > 0;
        $check->close();

        $check = $conn->prepare(
            "SELECT id FROM purchase_requests WHERE id = ?"
        );
        $check->bind_param("i", $request);
        $check->execute();
        $requestExists = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$vendorExists) {
            $errors[] = "Selected vendor does not exist.";
        }

        if (!$requestExists) {
            $errors[] = "Selected purchase request does not exist.";
        }
    }

    if (!$errors) {
        $uploadDir = __DIR__ . "/uploads/";

        if (!is_dir($uploadDir) &&
            !mkdir($uploadDir, 0750, true) &&
            !is_dir($uploadDir)) {
            $errors[] = "Could not create upload directory.";
        }
    }

    if (!$errors) {
        $storedFilename = bin2hex(random_bytes(16)) . "." . $extension;
        $destination = $uploadDir . $storedFilename;
        $fileType = $extension;
        $status = "uploaded";
        $userId = (int)$_SESSION["user_id"];
        $validUntilValue = $valid_until !== "" ? $valid_until : null;

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                "INSERT INTO quotations
                 (request_id, vendor_id, quotation_number,
                  quotation_date, valid_until, total_amount,
                  status, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iisssdsi",
                $request,
                $vendor,
                $quotation_number,
                $quotation_date,
                $validUntilValue,
                $total,
                $status,
                $userId
            );

            $stmt->execute();
            $quotationId = $conn->insert_id;
            $stmt->close();

            if (!move_uploaded_file($file["tmp_name"], $destination)) {
                throw new RuntimeException("File could not be stored.");
            }

            $originalFilename = basename($file["name"]);

            $stmt = $conn->prepare(
                "INSERT INTO quotation_files
                 (quotation_id, original_filename, stored_filename, file_type)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isss",
                $quotationId,
                $originalFilename,
                $storedFilename,
                $fileType
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: index.php?uploaded=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();

            if (isset($destination) && is_file($destination)) {
                unlink($destination);
            }

            error_log($e->getMessage());
            $errors[] = "Could not save the quotation. Check your database and upload settings.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Quotation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { display:block; padding:30px; }
        .form-card {
            max-width:700px; margin:auto; padding:25px;
            background:white; border-radius:12px;
        }
        label { display:block; margin-top:15px; }
        input,select {
            width:100%; padding:10px; margin-top:6px;
            border:1px solid #cbd5e1; border-radius:6px;
        }
        .error { background:#fee2e2; color:#991b1b; padding:10px; }
    </style>
</head>
<body>
<div class="form-card">
    <h1>Register Vendor Quotation</h1>
    <p>Accepted files: PDF, XLS, XLSX. Maximum size: 5 MB.</p>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endforeach; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Quotation Number *</label>
        <input name="quotation_number" required
               value="<?= h($quotation_number) ?>">

        <label>Vendor *</label>
        <select name="vendor_id" required>
            <option value="">Select vendor</option>
            <?php while ($v = $vendors->fetch_assoc()): ?>
                <option value="<?= (int)$v["id"] ?>"
                    <?= (string)$v["id"] === $vendor_id ? "selected" : "" ?>>
                    <?= h($v["vendor_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Purchase Request *</label>
        <select name="request_id" required>
            <option value="">Select request</option>
            <?php while ($r = $requests->fetch_assoc()): ?>
                <option value="<?= (int)$r["id"] ?>"
                    <?= (string)$r["id"] === $request_id ? "selected" : "" ?>>
                    <?= h($r["request_number"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Quotation Date *</label>
        <input type="date" name="quotation_date" required
               value="<?= h($quotation_date) ?>">

        <label>Valid Until</label>
        <input type="date" name="valid_until"
               value="<?= h($valid_until) ?>">

        <label>Total Amount (₹) *</label>
        <input type="number" name="total_amount" min="0" step="0.01"
               required value="<?= h($total_amount) ?>">

        <label>Quotation File *</label>
        <input type="file" name="quotation_file"
               accept=".pdf,.xls,.xlsx" required>

        <button type="submit">Upload Quotation</button>
    </form>

    <p><a href="index.php">Back to Quotations</a></p>
</div>
</body>
</html>
