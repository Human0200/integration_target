<?php

define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('NO_AGENT_CHECK', true);
define('PUBLIC_AJAX_MODE', true);
define('DisableEventsCheck', true);

$siteID = isset($_REQUEST['site']) ? mb_substr(preg_replace('/[^a-z0-9_]/i', '', $_REQUEST['site']), 0, 2) : '';
if ($siteID !== '') {
    define('SITE_ID', $siteID);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!check_bitrix_sessid()) {
    die();
}

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Crm\CompanyTable;
use Bitrix\Crm\DealTable;

if (!Loader::includeModule('crm') || !Loader::includeModule('main')) {
    die('Необходимые модули не подключены');
}

// Получаем параметры через правильный способ
$request = Application::getInstance()->getContext()->getRequest();
$componentData = $request->get('PARAMS');

$entityTypeId = 0;
$entityId = 0;

if (is_array($componentData) && isset($componentData['params'])) {
    $entityTypeId = (int)$componentData['params']['ENTITY_TYPE_ID'];
    $entityId = (int)$componentData['params']['ENTITY_ID'];
}

// Определяем тип сущности для передачи в iframe
$entityType = '';
switch ($entityTypeId) {
    case \CCrmOwnerType::Deal:
        $entityType = 'deal';
        break;
    case \CCrmOwnerType::Contact:
        $entityType = 'contact';
        break;
    case \CCrmOwnerType::Company:
        $entityType = 'company';
        break;
}

// Получаем ID текущего пользователя
$userId = $USER->GetID();

// Получаем пользовательские поля пользователя
$userFields = \CUser::GetByID($userId)->Fetch();
$targetApiKey = isset($userFields['UF_APIKEY']) ? $userFields['UF_APIKEY'] : '';

// Получаем Target ID компании
$targetCompanyId = null;

if ($entityType === 'company' && $entityId > 0) {
    // Если это компания - берем напрямую
    $company = CompanyTable::getList([
        'filter' => ['=ID' => $entityId],
        'select' => ['ID', 'UF_CRM_1760515262922']
    ])->fetch();
    
    if ($company && !empty($company['UF_CRM_1760515262922'])) {
        $targetCompanyId = $company['UF_CRM_1760515262922'];
    }
} elseif ($entityType === 'deal' && $entityId > 0) {
    // Если это сделка - находим связанную компанию
    $deal = DealTable::getList([
        'filter' => ['=ID' => $entityId],
        'select' => ['ID', 'COMPANY_ID']
    ])->fetch();
    
    if ($deal && !empty($deal['COMPANY_ID'])) {
        $company = CompanyTable::getList([
            'filter' => ['=ID' => $deal['COMPANY_ID']],
            'select' => ['ID', 'UF_CRM_1760515262922']
        ])->fetch();
        
        if ($company && !empty($company['UF_CRM_1760515262922'])) {
            $targetCompanyId = $company['UF_CRM_1760515262922'];
        }
    }
}

// Формируем URL для iframe
$url = 'https://targetco.ru';

// Формируем параметры URL
$params = [];
$params['entityType'] = $entityType;
$params['entityId'] = $entityId;
$params['userId'] = $userId;

// Передаем только API ключ для аутентификации
if (!empty($targetApiKey)) {
    $params['token'] = $targetApiKey;
}

// Добавляем Target ID компании, если найден
if (!empty($targetCompanyId)) {
    $params['targetCompanyId'] = $targetCompanyId;
}

// Специальная обработка для компаний с Target ID
if ($entityType === 'company' && !empty($targetCompanyId)) {
    $url = 'https://targetco.ru/offers?' . http_build_query($params) . '#user_id=' . $targetCompanyId;
} else {
    $url .= '?' . http_build_query($params);
}

// Генерируем уникальный ID для iframe на основе времени и пользователя
$iframeId = 'target_iframe_' . $userId . '_' . time();

// Выводим iframe с адаптивной высотой
?>
<div style="width: 100%; height: 800px; ">
    <iframe
        id="<?= $iframeId ?>"
        src="<?= htmlspecialcharsbx($url) ?>"
        width="100%"
        height="100%"
        frameborder="0"
        style="border: none; display: block;"
        onload="this.style.height = (this.contentWindow.document.body.scrollHeight + 20) + 'px';">
    </iframe>
</div>

<script>
console.log('URL: ' + '<?= htmlspecialcharsbx($url) ?>');
console.log('Target Company ID: ' + '<?= $targetCompanyId ?>');
console.log('User ID: ' + '<?= $userId ?>');
console.log('Iframe ID: ' + '<?= $iframeId ?>');

// Дополнительная защита от кэширования
document.getElementById('<?= $iframeId ?>').contentWindow.location.reload(true);
</script>