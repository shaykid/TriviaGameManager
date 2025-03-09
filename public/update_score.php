<?php
require __DIR__ . '/../config/db_connection.php'; // Database connection

// Retrieve parameters safely
$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] : null;
$score = isset($_GET['score']) ? (float)$_GET['score'] : null;
$qr_id = isset($_GET['qr_id']) ? $_GET['qr_id'] : null;

if (!$contact_id || !$qr_id || $score === null) {
    die(json_encode(["status" => "error", "message" => "Missing required parameters."]));
}

try {
    // Connect to MySQL database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Start transaction
    $pdo->beginTransaction();

    // Retrieve user_id based on contact_id
    $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE contact_id = ?");
    $stmt->execute([$contact_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }

    $user_id = $user['user_id'];

    // Check if the user has already scanned this QR code today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS scan_count
        FROM QrScans
        WHERE user_id = ? AND qr_id = ? AND DATE(timestamp) = CURDATE()
    ");
    $stmt->execute([$user_id, $qr_id]);
    $scanData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($scanData['scan_count'] > 0) {
        throw new Exception("QR code already scanned today.");
    }

    // Determine the scan order for today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS scan_order
        FROM QrScans
        WHERE DATE(timestamp) = CURDATE()
    ");
    $stmt->execute();
    $scanOrderData = $stmt->fetch(PDO::FETCH_ASSOC);
    $scanOrder = $scanOrderData['scan_order'];

    // Apply score multipliers
    if ($score == 5) {
        $score += 2;
    }
    if ($scanOrder == 1) {
        $score *= 5;
    } elseif ($scanOrder == 2) {
        $score *= 2;
    }

    // Update the user's score
    $stmt = $pdo->prepare("
        INSERT INTO UserScores (user_id, total_score, last_updated)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE total_score = total_score + VALUES(total_score), last_updated = NOW()
    ");
    $stmt->execute([$user_id, $score]);

    // Insert the scan record
    $stmt = $pdo->prepare("
        INSERT INTO QrScans (user_id, qr_id, timestamp)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$user_id, $qr_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Score updated successfully."]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
