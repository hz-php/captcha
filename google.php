<?php

// Подключение к базе данных
$db = new PDO('mysql:host=localhost;dbname=img_captcha', 'root', '');

// Путь к вашему каталогу с изображениями
$directory = '/path/to/your/images';

if ($handle = opendir($directory)) {
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            $filepath = $directory . '/' . $file;

            // Использование Google Cloud Vision API для распознавания текста на изображении
            $image = file_get_contents($filepath);
            $image_base64 = base64_encode($image);

            $url = 'https://vision.googleapis.com/v1/images:annotate?key=YOUR_API_KEY';
            $data = array(
                'requests' => array(
                    'image' => array(
                        'content' => $image_base64
                    ),
                    'features' => array(
                        'type' => 'TEXT_DETECTION'
                    )
                )
            );
            $options = array(
                'http' => array(
                    'header' => "Content-type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($data)
                )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $result = json_decode($result, true);

            $captcha_text = $result['responses'][0]['textAnnotations'][0]['description'];

            if ($captcha_text) {
                $status = 'success';
            } else {
                $captcha_text = '';
                $status = 'failure';
            }

            // Запись результатов в базу данных
            $stmt = $db->prepare("INSERT INTO google (filename, status, result) VALUES (:filename, :status, :result)");
            $stmt->bindParam(':filename', $filepath);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':result', $captcha_text);
            $stmt->execute();
        }
    }
    closedir($handle);
}