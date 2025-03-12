<?php
// Set headers for UTF-8 support
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding("UTF-8");

// Get parameters from URL, with defaults
$wappnum = isset($_GET['WAPPNUM']) ? $_GET['WAPPNUM'] : '972527229106';
$msgtext = isset($_GET['MSGTEXT']) ? $_GET['MSGTEXT'] : 'אני רוצה לשחק במשחקי שיח';
$id = isset($_GET['ID']) ? $_GET['ID'] : '1000001';

// Ensure proper UTF-8 encoding
$msgtext = mb_convert_encoding($msgtext, 'UTF-8', 'auto');
$msgtext = htmlspecialchars_decode($msgtext, ENT_QUOTES); // Decode any special characters

// Generate the new ID format
$currentMinute = date('i');
$currentHour = date('H');
$new_id = $currentMinute . $id . $currentHour;

// Correctly encode the message for iOS & Android compatibility
$full_message = urlencode($msgtext . ' ID=' . $new_id);
$whatsappURL = "https://wa.me/{$wappnum}?text={$full_message}";

// Debugging: Uncomment to test the URL before redirection
// echo $whatsappURL; exit();

// Redirect to WhatsApp
header("Location: $whatsappURL");
exit();
?>
