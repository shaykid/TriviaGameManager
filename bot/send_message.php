<?php
function sendMessageToUser($chat_id, $message) {
    $api_url = "https://api.telegram.org/botYOUR_BOT_TOKEN/sendMessage";
    $params = ["chat_id" => $chat_id, "text" => $message];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
