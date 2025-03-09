<?php
require __DIR__ . '/../config/db_connection.php'; // Database connection

// Retrieve parameters safely
$wappnum = isset($_GET['WAPPNUM']) ? $_GET['WAPPNUM'] : '972544506093';
$msgtext = isset($_GET['MSGTEXT']) ? $_GET['MSGTEXT'] : 'Lets Play';
$qr_id = isset($_GET['QR_ID']) ? $_GET['QR_ID'] : 'QR123456';

try {
    // Connect to MySQL database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Find user ID by phone number
    $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE phone_number = ?");
    $stmt->execute([$wappnum]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_id = $user['user_id'];

        // Insert scan record into QrScans table
        $stmt = $pdo->prepare("INSERT INTO QrScans (user_id, qr_id, timestamp) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $qr_id]);
    } else {
        // If user not found, log error (optional)
        error_log("User not found for phone number: " . $wappnum);
    }

    // Prepare WhatsApp URL
    $encodedMessage = urlencode($msgtext . " 🪴 " . $qr_id);
    $whatsappURL = "https://wa.me/{$wappnum}?text=🪴 {$encodedMessage} 🪴";

    // Redirect user to WhatsApp
    header("Location: $whatsappURL");
    exit;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die(json_encode(["status" => "error", "message" => "Database connection failed."]));
}
?>
