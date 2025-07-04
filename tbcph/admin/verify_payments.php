<?php
require_once '../includes/config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hire_id'], $_POST['action'])) {
    $hire_id = intval($_POST['hire_id']);
    $new_status = ($_POST['action'] === 'approve') ? 'paid' : 'rejected';
    $stmt = $conn->prepare('UPDATE hire SET payment_status = ? WHERE order_id = ?');
    $stmt->execute([$new_status, $hire_id]);
    $_SESSION['success'] = 'Payment status updated.';
    header('Location: verify_payments.php');
    exit();
}

// Fetch all hires with payment_status 'verifying'
$stmt = $conn->query('
    SELECT h.*, c.name AS client_name, c.email AS client_email, b.band_name, b.name AS busker_name
    FROM hire h
    JOIN inquiry i ON h.inquiry_id = i.inquiry_id
    JOIN client c ON i.client_id = c.client_id
    JOIN busker b ON h.busker_id = b.busker_id
    WHERE h.payment_status = "verifying"
    ORDER BY h.order_id DESC
');
$hires = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments - Admin - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
    <style>
        .verify-container { max-width: 1100px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f8f9fa; }
        .btn { padding: 8px 18px; border-radius: 6px; font-weight: 600; border: none; cursor: pointer; }
        .btn-approve { background: #2ecc71; color: #fff; }
        .btn-approve:hover { background: #219150; }
        .btn-reject { background: #e74c3c; color: #fff; }
        .btn-reject:hover { background: #c0392b; }
        .proof-link { color: #3498db; text-decoration: none; }
        .proof-link:hover { text-decoration: underline; }
        .success-message { background: #2ecc71; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    <div class="verify-container">
        <h2>Verify Payments</h2>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Client</th>
                    <th>Busker</th>
                    <th>Payment Method</th>
                    <th>Payer Name</th>
                    <th>Reference #</th>
                    <th>Payment Date</th>
                    <th>Proof</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hires)): ?>
                    <tr><td colspan="9" style="text-align:center;">No payments to verify.</td></tr>
                <?php else: foreach ($hires as $hire): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($hire['order_id']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($hire['client_name']); ?><br>
                            <small><?php echo htmlspecialchars($hire['client_email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($hire['band_name'] ?: $hire['busker_name']); ?></td>
                        <td><?php echo htmlspecialchars($hire['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($hire['payer_name']); ?></td>
                        <td><?php echo htmlspecialchars($hire['reference_number']); ?></td>
                        <td><?php echo $hire['payment_date'] ? date('F j, Y', strtotime($hire['payment_date'])) : '-'; ?></td>
                        <td>
                            <?php if ($hire['payment_proof']): ?>
                                <a href="../<?php echo htmlspecialchars($hire['payment_proof']); ?>" class="proof-link" target="_blank">View</a>
                            <?php else: ?>
                                No proof
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="hire_id" value="<?php echo $hire['order_id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Reject this payment?');">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html> 