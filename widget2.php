<?php
require 'vendor/autoload.php';

require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', 'alikson');
define('CLIENT_ID', '60d0e22a-fb64-47f0-856e-05b7c0bfe2e5');
define('CLIENT_SECRET', 'ByJeFXBo3eTM9WWUJHkXK4F1OfLCw3FPwshjTYhdv1JNJHWDwtU4QNa3868UtnBG');
define('CODE', 'def50200e85064322469fc80b6995c5303f2a6488ecd865fe39525cc59159531938ef122627ea3d2991a9e951997d39b064bf5ae4db66344e2e5984da6be5e1c0b0e6859a2383560d95527a425346b3f39ed093e10534dc535779a89f4898dcba1f0e8d16afc8a52e1f40d6d626a8ebb66f54f0732acdb0c8264d9bdd3f14133c5dd210a3a78ea4d56d84ae94673737466cb698ca43d46b060e0e1723ae32e3a4ba91e8f1bb861a1b11acca52d81e04868ab831f3661a6059ed7ee944c8c71e479a7b349d97e43eb5c9ed544a46288708d55599a344ea8ee58320e9c16e3df11ddf8c1ad4010919572e2603af87ee6239beabd1e76934c9588d6e6ff9e028d2f1b82e066f2aa9215e9ca4f8bfb33610f7c14625a58617643759ad540ea2553214e0d1c9083e291eb8cfb33ce036cc2bccaf9c554f4a11aa9469588597b821d8e359d94bcc1db4610c1a47dd99dc4bb7eb1973626cf56de3de2c5e3af70d1cc0483eb7e215ea7a3581ca74fc7f7eb1a03a0924c65182e4a8189ef18181a80c51a0fe553288e3bc7a3320f4d38930a037a789c3e7b418666cf23d8ffa082267f56e63e50d5997eec98b4e09521e613fc93546d020c9576f533a5b77abca01348b3278227247c2526f59811deb495ce4e8e640ff1b2ee5d9a1a0351fa751da730ac683b82fc3f6fe7ee9d');
define('REDIRECT_URL', 'https://alikson.amocrm.ru');



$responsibleUserIds = [9649906, 9649914, 9810786, 9823110, 9860838, 9900262, 9962898, 9963002];
$sumsByManager = [];
$managerNames = []; // Инициализация массива для имен менеджеров
$telegram = new \TelegramBot\Api\BotApi('6098750394:AAEukzP6v28GowwvNoNFVZ5wsJJzVM1AJFQ');
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
