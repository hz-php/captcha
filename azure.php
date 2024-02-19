<?php

// Подключение к базе данных
$db = new PDO('mysql:host=localhost;dbname=img_captcha', 'root', '');

// Путь к вашему каталогу с изображениями
$directory = '/path/to/your/images';

if ($handle = opendir($directory)) {
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            $filepath = $directory . '/' . $file;

            // Использование Microsoft Azure Computer Vision API для распознавания текста на изображении
            $image = file_get_contents($filepath);
            $image_base64 = base64_encode($image);

            $url = 'https://westcentralus.api.cognitive.microsoft.com/vision/v2.0/ocr?language=unk&detectOrientation=true';
            $headers = array(
                'Content-Type: application/octet-stream',
                'Ocp-Apim-Subscription-Key: YOUR_API_KEY'
            );

            $options = array(
                'http' => array(
                    'header' => implode("\r\n", $headers),
                    'method' => 'POST',
                    'content' => $image_base64
                )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $result = json_decode($result, true);

            $captcha_text = $result['regions'][0]['lines'][0]['words'][0]['text'];

            if ($captcha_text) {
                $status = 'success';
            } else {
                $captcha_text = '';
                $status = 'failure';
            }

            // Запись результатов в базу данных
            $stmt = $db->prepare("INSERT INTO azure (filename, status, result) VALUES (:filename, :status, :result)");
            $stmt->bindParam(':filename', $filepath);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':result', $captcha_text);
            $stmt->execute();
        }
    }
    closedir($handle);
}