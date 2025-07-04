<?php
require_once '../includes/config.php';

// Check if user is logged in as client
if (!isset($_SESSION['client_id'])) {
    header('Location: index.php');
    exit();
}

// Get inquiry_id from GET
$inquiry_id = isset($_GET['inquiry_id']) ? intval($_GET['inquiry_id']) : 0;
if (!$inquiry_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch hire record for this inquiry and client
$stmt = $conn->prepare('
    SELECT h.*, b.band_name, b.name as busker_name
    FROM hire h
    JOIN inquiry i ON h.inquiry_id = i.inquiry_id
    JOIN busker b ON h.busker_id = b.busker_id
    WHERE h.inquiry_id = ? AND i.client_id = ?
');
$stmt->execute([$inquiry_id, $_SESSION['client_id']]);
$hire = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$hire) {
    $_SESSION['error'] = 'No payment record found for this inquiry.';
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $payer_name = $_POST['payer_name'] ?? '';
    $reference_number = $_POST['reference_number'] ?? '';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_status = 'verifying'; // Set to verifying for admin review
    $payment_proof = $hire['payment_proof'];

    // Handle file upload
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = time() . '_' . basename($_FILES['payment_proof']['name']);
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
            $payment_proof = 'uploads/' . $file_name;
        }
    }

    // Update hire table
    $stmt = $conn->prepare('
        UPDATE hire SET
            payment_status = ?,
            payment_method = ?,
            payer_name = ?,
            reference_number = ?,
            payment_date = ?,
            payment_proof = ?
        WHERE inquiry_id = ?
    ');
    $stmt->execute([
        $payment_status,
        $payment_method,
        $payer_name,
        $reference_number,
        $payment_date,
        $payment_proof,
        $inquiry_id
    ]);
    $_SESSION['success'] = 'Payment details updated successfully!';
    header('Location: dashboard.php');
    exit();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Payment - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
    <style>
        .payment-container { max-width: 600px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group input[type="file"] { padding: 3px; }
        .btn-primary { background: #3498db; color: #fff; border: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; }
        .btn-primary:hover { background: #217dbb; }
        .proof-preview { margin-top: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="payment-container">
        <h2>Update Payment for Inquiry #<?php echo htmlspecialchars($inquiry_id); ?></h2>
        <p>Busker: <strong><?php echo htmlspecialchars($hire['band_name'] ?: $hire['busker_name']); ?></strong></p>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="">Select Method</option>
                    <option value="Bank Transfer" <?php if($hire['payment_method']==='Bank Transfer') echo 'selected'; ?>>Bank Transfer</option>
                    <option value="GCash" <?php if($hire['payment_method']==='GCash') echo 'selected'; ?>>GCash</option>
                    <option value="PayMaya" <?php if($hire['payment_method']==='PayMaya') echo 'selected'; ?>>PayMaya</option>
                    <option value="Credit Card" <?php if($hire['payment_method']==='Credit Card') echo 'selected'; ?>>Credit Card</option>
                    <option value="Cash" <?php if($hire['payment_method']==='Cash') echo 'selected'; ?>>Cash</option>
                </select>
            </div>
            <div class="form-group">
                <label for="payer_name">Payer Name</label>
                <input type="text" name="payer_name" id="payer_name" value="<?php echo htmlspecialchars($hire['payer_name'] ?? $_SESSION['client_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="reference_number">Reference Number</label>
                <input type="text" name="reference_number" id="reference_number" value="<?php echo htmlspecialchars($hire['reference_number'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="payment_date">Payment Date</label>
                <input type="date" name="payment_date" id="payment_date" value="<?php echo htmlspecialchars($hire['payment_date'] ? date('Y-m-d', strtotime($hire['payment_date'])) : date('Y-m-d')); ?>" required>
            </div>
            <div class="form-group">
                <label for="payment_proof">Proof of Payment (image or PDF)</label>
                <input type="file" name="payment_proof" id="payment_proof" accept="image/*,application/pdf">
                <?php if (!empty($hire['payment_proof'])): ?>
                    <div class="proof-preview">
                        <a href="../<?php echo htmlspecialchars($hire['payment_proof']); ?>" target="_blank">View Current Proof</a>
                    </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-primary">Submit Payment</button>
            <a href="dashboard.php" class="btn-primary" style="background:#888; margin-left:10px;">Cancel</a>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html> 