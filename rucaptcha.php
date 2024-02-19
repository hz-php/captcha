<?php

set_time_limit(0);

$url = "http://centms3.kln.msudrf.ru/captcha.php";
$directory = "img";

if (!file_exists($directory)) {
    mkdir($directory, 0777, true);
}
if (count_files($directory) < 3000) {
    for ($i = 0; $i < 3000; $i++) {
        $ch = curl_init($url);
        $fp = fopen($directory . "/captcha$i.png", 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }
}


$db = new PDO('mysql:host=localhost;dbname=img_captcha', 'root', '');
// Ваш ключ API от RuCaptcha
$rucaptcha_key = '';


if ($handle = opendir($directory)) {
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            $filepath = $directory . '/' . $file;

            $data = array(
                'clientKey' => $rucaptcha_key,
                'task' => array(
                    'type' => 'ImageToTextTask',
                    'body' => base64_encode(file_get_contents($filepath)),
                    "phrase" => false,
                    "case" => true,
                    "numeric" => 0,
                    "math" => false,
                    "minLength" => 1,
                    "maxLength" => 5,
                    "comment" => "введите текст, который вы видите на изображении"
                )
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.rucaptcha.com/createTask');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
            $response = curl_exec($ch);
            $response_data = json_decode($response, true);

            if ($response_data['errorId'] == 0) {
                $captcha_id = $response_data['taskId'];

                do {
                    sleep(1);

                    $params = array('clientKey' => $rucaptcha_key, 'taskId' => $captcha_id);

                    curl_setopt($ch, CURLOPT_URL, 'https://api.rucaptcha.com/getTaskResult');
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
                    $response = curl_exec($ch);
                    $response_data = json_decode($response, true);
              

                } while ($response_data['status'] == 'processing');

                if ($response_data['status'] == 'ready') {
                    $captcha_text = $response_data['solution']['text'];
                    $status = 'success';
                } else {
                    $captcha_text = $response_data['errorDescription'];
                    $status = 'failure';
                }

                // Запись результатов в базу данных
                $stmt = $db->prepare("INSERT INTO images (filename, status, result) VALUES (:filename, :status, :result)");
                $stmt->bindParam(':filename', $filepath);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':result', $captcha_text);
                $stmt->execute();
            }
        }
    }
    closedir($handle);
}

function count_files($dir)
{
    $c = 0;
    $d = dir($dir);
    while ($str = $d->read()) {
        if ($str[0] != '.') {
            if (is_dir($dir . '/' . $str)) $c += count_files($dir . '/' . $str);
            else $c++;
        };
    }
    $d->close();
    return $c;
}

//1804 штуки дальше забанили Ваш аккаунт был заблокирован навсегда за подозрительную активность или нарушение Условий обслуживания. Вы больше не можете войти в аккаунт
?>
