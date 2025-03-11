<?php
// Get parameters from URL, with default values
$wappnum = isset($_GET['WAPPNUM']) ? $_GET['WAPPNUM'] : '972527229106';
$msgtext = isset($_GET['MSGTEXT']) ? $_GET['MSGTEXT'] : '🪴 אני רוצה לשחק 😂 ';
$id = isset($_GET['ID']) ? $_GET['ID'] : '1000001';

// Prepare WhatsApp message URL
$full_message = urlencode($msgtext . ' ID=' . $id);
$whatsappURL = "https://wa.me/{$wappnum}?text={$full_message}";

// Immediately redirect the user's browser to WhatsApp
header("Location: $whatsappURL");
exit();
?>
