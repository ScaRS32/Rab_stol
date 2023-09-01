<?php
require 'vendor/autoload.php';

require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', '***');
define('CLIENT_ID', '****');
define('CLIENT_SECRET', '***');
define('CODE', '*****');
define('REDIRECT_URL', '****');


$midnightUnixTime = strtotime('today');

try {
    $amoV4Client = new AmoCrmV4Client(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);

    // Ассоциативный массив для хранения сумм по проектам
    $projectSummaries = [];

    // Получаем события по изменению этапов
    $eventsResponse = $amoV4Client->GETRequestApi('events', [
        'filter[created_at][from]' => $midnightUnixTime,
        'filter[type]' => 'lead_status_changed',
        'filter[entity_type]' => 'lead',
        'filter[value_after][leads_statuses][0][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][0][status_id]' => 57502230,
        'filter[value_after][leads_statuses][1][pipeline_id]' => 6808230,
        'filter[value_after][leads_statuses][1][status_id]' => 59637470
    ])['_embedded']['events'];

    // Массив для хранения уникальных entity_id (ID сделок)
    $uniqueLeadIds = [];
    $projectNames = [
        '734373' => 'Аликсон',
        '734375' => 'Нембус',
        '734903' => 'Маркетплейс',
    ];

    foreach ($eventsResponse as $event) {
        $entityId = $event['entity_id'];
        if (!in_array($entityId, $uniqueLeadIds)) {
            $uniqueLeadIds[] = $entityId;
        }
    }

    // Получаем информацию о сделках с уникальными ID
    foreach ($uniqueLeadIds as $leadId) {
        $leadResponse = $amoV4Client->GETRequestApi("leads/$leadId");

        // Проверяем, что есть цена и enum_id
        if (isset($leadResponse['price'])) {
            $price = $leadResponse['price'];

            if (isset($leadResponse['responsible_user_id']) && $leadResponse['responsible_user_id'] != 9698626) {
                $enumId = null;
                foreach ($leadResponse['custom_fields_values'] as $field) {
                    if ($field['field_id'] == 1268923) {
                        $enumId = $field['values'][0]['enum_id'];
                        break;
                    }
                }

                // Если enum_id найден и соответствует проекту, добавляем сумму к проекту
                if (!is_null($enumId) && isset($projectNames[$enumId])) {
                    $projectName = $projectNames[$enumId];
                    if (!isset($projectSummaries[$projectName])) {
                        $projectSummaries[$projectName] = 0;
                    }
                    $projectSummaries[$projectName] += $price;
                }
            }
        }
    }

    // Сортируем массив сумм по проектам по убыванию
    arsort($projectSummaries);

    // Выводим результаты
    $htmlResults = '<div class="title">СУММЫ СДЕЛОК ПО ПРОЕКТАМ</div>';
    foreach ($projectSummaries as $projectName => $sum) {
        $progressWidth = min(($sum / 300000) * 100, 100); // Рассчитываем ширину полоски прогресса, ограничивая максимум 100%
        $htmlResults .= '<li>
    <div class="project-and-sum">
        <div class="project">' . $projectName . '</div>
        <div class="sum">' . $sum . '</div>
    </div>
    <div class="progress-container">
        <div class="progress thin-progress" style="width: ' . $progressWidth . '%;"></div>
    </div>
</li>';
    }
    echo $htmlResults;
} catch (Exception $e) {
    // Обработка ошибок, например, логирование или вывод сообщения об ошибке
    echo 'Произошла ошибка: ' . $e->getMessage();
}