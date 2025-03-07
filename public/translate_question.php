<?php
function translateQuestion($question_text, $target_language) {
    $api_key = "YOUR_OPENAI_API_KEY";
    $url = "https://api.openai.com/v1/chat/completions";
    
    $headers = ["Content-Type: application/json", "Authorization: Bearer $api_key"];
    $data = json_encode([
        "model" => "gpt-4",
        "messages" => [["role" => "system", "content" => "Translate this: $question_text to $target_language"]],
        "temperature" => 0.7
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true)['choices'][0]['message']['content'] ?? "Translation failed";
}
?>
