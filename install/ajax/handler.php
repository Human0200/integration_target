<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit(json_encode([
        'success' => false,
        'error' => 'Доступ запрещен!',
    ]));
}

header('Content-Type: application/json');

use Bitrix\Main\Loader;
use LeadSpace\Classes\Contacts\FindContact;

if (!Loader::includeModule('leadspace.integrationtarget')) {
    die(json_encode([
        'success' => false,
        'error' => 'Module leadspace.integrationtarget not installed'
    ]));
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode([
        'success' => false,
        'error' => 'Разрешены только POST запросы'
    ]));
}

$action = $_POST['action'] ?? '';
$params = $_POST['params'] ?? [];

$apiFunctions = [
    'findOrCreateContact' => function($params) {
        try {
            $contactId = FindContact::findOrCreateContact($params['properties'] ?? []);
            
            if ($contactId) {
                return [
                    'success' => true,
                    'data' => [
                        'contactId' => $contactId
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Не удалось найти или создать контакт'
                ];
            }
        } catch (\Exception $e) {
            error_log('Error in findOrCreateContact: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера'
            ];
        }
    },
    
    'updateContact' => function($params) {
        try {
            $contactId = $params['contactId'] ?? null;
            $data = $params['data'] ?? [];
            
            if (!$contactId) {
                return [
                    'success' => false,
                    'error' => 'Не указан ID контакта'
                ];
            }
            
            $result = FindContact::updateContactFields($contactId, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'data' => [
                        'contactId' => $result
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Не удалось обновить контакт'
                ];
            }
        } catch (\Exception $e) {
            error_log('Error in updateContact: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера'
            ];
        }
    },
    
    'createAddress' => function($params) {
        try {
            $requisites = $params['requisites'] ?? [];
            $result = FindContact::createAddress($requisites);
            
            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Exception $e) {
            error_log('Error in createAddress: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера'
            ];
        }
    },
];

// Валидация action
if (empty($action)) {
    exit(json_encode([
        'success' => false,
        'error' => 'Не указано действие (action)'
    ]));
}

if (!array_key_exists($action, $apiFunctions)) {
    exit(json_encode([
        'success' => false,
        'error' => 'Неизвестное действие: ' . $action
    ]));
}

// Выполняем запрошенное действие
try {
    $result = $apiFunctions[$action]($params);
    echo json_encode($result);
} catch (\Exception $e) {
    error_log('Handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Внутренняя ошибка сервера'
    ]);
}
?>