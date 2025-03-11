<?php
// Get parameters from URL, with defaults
$wappnum = isset($_GET['WAPPNUM']) ? $_GET['WAPPNUM'] : '972544506093';
$msgtext = isset($_GET['MSGTEXT']) ? $_GET['MSGTEXT'] : '🪴 אני רוצה לשחק 😂 ';
$id = isset($_GET['ID']) ? $_GET['ID'] : '1000001';

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
