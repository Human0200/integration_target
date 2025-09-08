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

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!check_bitrix_sessid()) {
    die();
}

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

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
$targetLogin = isset($userFields['UF_TARGET_LOGIN']) ? $userFields['UF_TARGET_LOGIN'] : '';
$targetPassword = isset($userFields['UF_TARGET_PASSWORD']) ? $userFields['UF_TARGET_PASSWORD'] : '';

// Формируем URL для iframe
$url = 'https://test.targetco.ru';
$params = [];

// Добавляем параметры сущности
if ($entityType !== '' && $entityId > 0) {
    $params['entityType'] = $entityType;
    $params['entityId'] = $entityId;
}

// Добавляем пользоватательские поля
if (!empty($targetLogin)) {
    $params['login'] = $targetLogin;
}
if (!empty($targetPassword)) {
    $params['password'] = $targetPassword;
}

// Добавляем ID пользователя
$params['userId'] = $userId;

// Формируем полный URL
if (!empty($params)) {
    $url .= '?' . http_build_query($params);
}

// Выводим iframe с адаптивной высотой
?>
<div style="width: 100%; height: 800px;">
    <iframe 
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
</script>