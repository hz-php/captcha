<?php

set_time_limit(0);
//Только 25 запросов в день
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


$license_code = '';
$username = 'SIA05';

$db = new PDO('mysql:host=localhost;dbname=img_captcha', 'root', '');


$url = 'http://www.ocrwebservice.com/restservices/processDocument?gettext=true&language=english,german,spanish,russian';

if ($handle = opendir($directory)) {
   while (false !== ($file = readdir($handle))) {
       if ($file != "." && $file != "..") {
           $filePath = $directory . '/' . $file;

           $fp = fopen($filePath, 'r');
           $session = curl_init();

           curl_setopt($session, CURLOPT_URL, $url);
           curl_setopt($session, CURLOPT_USERPWD, "$username:$license_code");
           curl_setopt($session, CURLOPT_UPLOAD, true);
           curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'POST');
           curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($session, CURLOPT_TIMEOUT, 200);
           curl_setopt($session, CURLOPT_HEADER, false);

           curl_setopt($session, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

           curl_setopt($session, CURLOPT_INFILE, $fp);
           curl_setopt($session, CURLOPT_INFILESIZE, filesize($filePath));

           $result = curl_exec($session);

           $httpCode = curl_getinfo($session, CURLINFO_HTTP_CODE);
           curl_close($session);
           fclose($fp);

           if ($httpCode == 401) {
               $captcha_text = 'Unauthorized request';
               $status = 'failure';
           }

           $data = json_decode($result);

           if ($httpCode != 200) {
               $captcha_text = $data->ErrorMessage;
               $status = 'failure';
           } else {
               $captcha_text = $data->OCRText[0][0];
               $status = 'success';
           }
           // Запись результатов в базу данных
           $stmt = $db->prepare("INSERT INTO ocr_api (filename, status, result) VALUES (:filename, :status, :result)");
           $stmt->bindParam(':filename', $filePath);
           $stmt->bindParam(':status', $status);
           $stmt->bindParam(':result', $captcha_text);
           $stmt->execute();
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

