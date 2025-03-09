<?php
// Get URL parameters or set defaults
$wappnum = isset($_GET['WAPPNUM']) ? $_GET['WAPPNUM'] : '972544506093';
$msgtext = isset($_GET['MSGTEXT']) ? $_GET['MSGTEXT'] : 'Lets Play';
$id = isset($_GET['ID']) ? $_GET['ID'] : '1000001';

// Prepare WhatsApp URL
$encodedMessage = urlencode($msgtext . " 🪴 " . $id);
$whatsappURL = "https://wa.me/{$wappnum}?text=🪴 {$encodedMessage} 🪴";

// Redirect user to WhatsApp
header("Location: $whatsappURL");
exit;
?>
