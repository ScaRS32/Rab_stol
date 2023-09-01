<?php
require 'vendor/autoload.php';

require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', '');
define('CLIENT_ID', '');
define('CLIENT_SECRET', '');
define('CODE', '');
define('REDIRECT_URL', '');



$responsibleUserIds = [9649906, 9649914, 9810786, 9823110, 9860838, 9900262, 9962898, 9963002];
$sumsByManager = [];
$managerNames = []; // Инициализация массива для имен менеджеров
$uniqueLeadIds =[];
$midnightUnixTime = strtotime('today');

try {
    $amoV4Client = new AmoCrmV4Client(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
    foreach ($responsibleUserIds as $managerId) {
        $managerData = $amoV4Client->GETRequestApi('users/' . $managerId);
        $managerName = $managerData['name'];
        $managerNames[$managerId] = $managerName;
    }


    // Получаем события по изменению этапов
    $eventsResponse = $amoV4Client->GETRequestApi('events', [
        'filter[created_at][from]' => $midnightUnixTime,
        'filter[type]' => 'lead_status_changed',
        'filter[entity_type]'=>'lead',
        'filter[value_after][leads_statuses][0][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][0][status_id]' => 57502230,
        'filter[value_after][leads_statuses][1][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][1][status_id]' => 59637470,
        'filter[value_after][leads_statuses][2][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][2][status_id]' => 142,
    ])['_embedded']['events'];




    foreach ($eventsResponse as $event) {
        $entityId = $event['entity_id'];
        if (!in_array($entityId, $uniqueLeadIds)) {
            $uniqueLeadIds[] = $entityId;
        }
    }

// Получаем информацию о сделках с уникальными ID
    foreach ($uniqueLeadIds as $leadId) {
        $leadResponse = $amoV4Client->GETRequestApi("leads/$leadId");
        if (isset($leadResponse['price']) && isset($leadResponse['responsible_user_id'])) {
            $price = $leadResponse['price'];
            $managerId = $leadResponse['responsible_user_id'];

            // Суммируем бюджеты по менеджерам
            if (!isset($sumsByManager[$managerId])) {
                $sumsByManager[$managerId] = 0;
            }
            $sumsByManager[$managerId] += $price;
        }
    }


    // Сортируем менеджеров по убыванию суммы
    arsort($sumsByManager);
    $htmlResults = '<div class="title">СУММЫ СДЕЛОК ПО МЕНЕДЖЕРАМ</div>';
    foreach ($sumsByManager as $manager => $sum) {
        // Проверяем, что значения не являются пустыми
        if (!empty($manager) && $sum !== null && $sum > 15000) {
            $progressWidth = min(($sum / 300000) * 100, 100); // Рассчитываем ширину полоски прогресса, ограничивая максимум 100%
            $htmlResults .= '<li>
    <div class="manager-and-sum">
        <div class="manager">' . $manager . '</div>
        <div class="sum">' . $sum . '</div>
    </div>
    <div class="progress-container">
        <div class="progress thin-progress" style="width: ' . $progressWidth . '%;"></div>
    </div>
</li>';
        }
    }

// Выводим HTML-код на страницу
    echo $htmlResults;



} catch (Exception $ex) {
    var_dump($ex);
    file_put_contents("ErrLog.txt", 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки: ' . $ex->getCode());
}
