<?php
// Set content-type header to ensure UTF-8 encoding
header('Content-Type: text/html; charset=utf-8');

// Get parameters from URL, with defaults
$wappnum = isset($_GET['WAPPNUM']) ? $_GET['WAPPNUM'] : '972527229106';
$msgtext = isset($_GET['MSGTEXT']) ? $_GET['MSGTEXT'] : '🪴  אני רוצה לשחק במשחקי שיח 😂 ';
$id = isset($_GET['ID']) ? $_GET['ID'] : '1000001';

// Ensure the message is in UTF-8 encoding
$msgtext = mb_convert_encoding($msgtext, 'UTF-8', 'auto');

// Generate the new ID format as requested
$currentMinute = date('i');  // minute at start
$currentHour = date('H');    // hour at end
$new_id = $currentMinute . $id . $currentHour;

// Prepare WhatsApp URL
$full_message = urlencode($msgtext . ' ID=' . $new_id);
$whatsappURL = "https://wa.me/{$wappnum}?text={$full_message}";

// Redirect immediately to WhatsApp
header("Location: $whatsappURL");
exit();
?>
