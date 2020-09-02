<?php

chdir(__DIR__);

/**
 * @param string $url
 * @return bool|string
 * @throws Exception
 */
function get_content($url)
{
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);

    $data = curl_exec($curl);

    if ($data === false) {
        curl_close($curl);
        throw new Exception(curl_error($curl));
    }

    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($status !== 200) {
        curl_close($curl);
        throw new Exception("HTTP status: $status");
    }
    curl_close($curl);
    return $data;
}

/**
 * Функция получения ссылки на актуальный файл
 *
 * @return mixed
 * @throws Exception
 */
function getLatestCsvLink()
{
    $baseUrl = 'https://data.gov.ru/opendata/7708660670-proizvcalendar';
    $body = get_content($baseUrl);

    $pattern = '#href="(' . preg_quote($baseUrl, '#') . '/data-[^.]+\.csv)#';
    $links = preg_match_all($pattern, $body, $regs) ? array_unique($regs[1]) : [];
    rsort($links);
    $link = isset($links[0]) ? $links[0] : null;

    if (null === $link)
        throw new Exception("Не удалось определить ссылку на csv файл с данными");

    return $link;
}

// статус операции
$success = false;

// есть ли обновление
$has_changes = false;

try {
    // получаем ссылку на актуальный файл
    $link = getLatestCsvLink();

    // получаем данные по ссылке
    $csv = get_content($link);

//    $link = __DIR__ . '/calendar.csv';
//    $csv = file_get_contents($link);

    $filename = basename($link);

    // минимальная проверка на валидность данных (текущий год должен быть в данных)
    if (!preg_match('/^'.date('Y').',/m', $csv))
        throw new Exception("Полученные данные не валидны ({$filename})");

    $md5 = md5($csv);
    $md5_old = preg_split('/\s+/', file_get_contents('./md5sum'))[0];

    $success = true;
    $has_changes = $md5 !== $md5_old;

    $message = ($has_changes) ? "УСПЕШНО: Данные изменены ({$filename})" : "УСПЕШНО: Нет изменений ({$filename})";

    // записываем данные
    file_put_contents('./calendar.csv', $csv);
    file_put_contents('./md5sum', "{$md5}  calendar.csv\n");

} catch (Exception $e) {
    $message = "ОШИБКА: " . $e->getMessage();
}

if ($has_changes || (int) date('w') === 0) {
    passthru('git add calendar.csv md5sum');
    passthru('git commit --allow-empty -m ' . escapeshellarg($message));
    passthru('git push');
}
