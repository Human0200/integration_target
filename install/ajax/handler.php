<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit(json_encode([
        'success' => false,
        'error' => 'Доступ запрещен!',
    ]));
}

header('Content-Type: application/json');

use Bitrix\Main\Loader;
use LeadSpace\Classes\Contacts\FindContact;
use LeadSpace\Classes\Company\CompanyManager;

// Функция для логирования
function writeHandlerLog($message)
{
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/handler_debug.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// Загружаем необходимые модули
if (!Loader::includeModule('crm')) {
    die(json_encode([
        'success' => false,
        'error' => 'CRM module not loaded'
    ]));
}

if (!Loader::includeModule('leadspace.integrationtarget')) {
    die(json_encode([
        'success' => false,
        'error' => 'Module leadspace.integrationtarget not installed'
    ]));
}

// Получаем данные из запроса
$requestMethod = $_SERVER['REQUEST_METHOD'];
$action = '';
$params = [];

writeHandlerLog('=== New request ===');
writeHandlerLog('REQUEST_METHOD: ' . $requestMethod);
writeHandlerLog('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Обработка разных типов запросов
if ($requestMethod === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Если JSON
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');
        writeHandlerLog('Raw JSON input: ' . $rawInput);

        $jsonData = json_decode($rawInput, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $action = $jsonData['action'] ?? '';
            $params = $jsonData['params'] ?? [];
        } else {
            writeHandlerLog('JSON decode error: ' . json_last_error_msg());
        }
    }
    // Если обычный POST (form-data или x-www-form-urlencoded)
    else {
        writeHandlerLog('POST data: ' . print_r($_POST, true));
        $action = $_POST['action'] ?? '';
        $params = $_POST['params'] ?? [];
    }
}
// Если GET запрос
elseif ($requestMethod === 'GET') {
    writeHandlerLog('GET data: ' . print_r($_GET, true));
    $action = $_GET['action'] ?? '';
    $params = $_GET['params'] ?? [];
}

writeHandlerLog('Parsed action: ' . $action);
writeHandlerLog('Parsed params: ' . print_r($params, true));

$apiFunctions = [
    'findOrCreateContact' => function ($params) {
        try {
            writeHandlerLog('Calling FindContact::findOrCreateContact with: ' . print_r($params, true));

            $contactId = FindContact::findOrCreateContact($params['properties'] ?? $params);

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
            writeHandlerLog('Error in findOrCreateContact: ' . $e->getMessage());
            writeHandlerLog('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
            ];
        }
    },

    'updateContact' => function ($params) {
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
            writeHandlerLog('Error in updateContact: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
            ];
        }
    },

    'deleteContact' => function ($params) {
        try {
            $contactId = $params['contactId'] ?? null;

            if (!$contactId) {
                return [
                    'success' => false,
                    'error' => 'Не указан ID контакта'
                ];
            }

            $result = FindContact::deleteContact($contactId);

            if ($result) {
                return [
                    'success' => true,
                    'data' => [
                        'message' => 'Контакт успешно удален',
                        'contactId' => $contactId
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Не удалось удалить контакт'
                ];
            }
        } catch (\Exception $e) {
            writeHandlerLog('Error in deleteContact: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
            ];
        }
    },

    'findOrCreateCompany' => function ($params) {
        try {
            $companyId = CompanyManager::findOrCreateCompany($params['properties'] ?? $params);

            if ($companyId) {
                return [
                    'success' => true,
                    'data' => [
                        'companyId' => $companyId
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Не удалось найти или создать компанию'
                ];
            }
        } catch (\Exception $e) {
            writeHandlerLog('Error in findOrCreateCompany: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
            ];
        }
    },

    'updateCompany' => function ($params) {
        try {
            writeHandlerLog('=== updateCompany вызван ===');
            writeHandlerLog('Params: ' . print_r($params, true));

            $companyId = $params['companyId'] ?? null;
            $data = $params['data'] ?? [];

            if (!$companyId) {
                $error = 'Не указан ID компании (companyId отсутствует в запросе)';
                writeHandlerLog('Ошибка: ' . $error);
                return [
                    'success' => false,
                    'error' => $error
                ];
            }

            if (empty($data)) {
                $error = 'Не переданы данные для обновления (data пустой)';
                writeHandlerLog('Ошибка: ' . $error);
                return [
                    'success' => false,
                    'error' => $error
                ];
            }

            writeHandlerLog('Вызываем CompanyManager::updateCompanyFields');
            $result = CompanyManager::updateCompanyFields($companyId, $data);
            writeHandlerLog('Результат: ' . print_r($result, true));

            // Если результат - массив с ключом success
            if (is_array($result)) {
                if ($result['success']) {
                    return [
                        'success' => true,
                        'data' => [
                            'companyId' => $result['companyId']
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $result['error'] ?? 'Неизвестная ошибка обновления компании'
                    ];
                }
            }
            // Если результат - просто ID (для обратной совместимости)
            elseif ($result) {
                return [
                    'success' => true,
                    'data' => [
                        'companyId' => $result
                    ]
                ];
            }
            // Если false или null
            else {
                return [
                    'success' => false,
                    'error' => 'Не удалось обновить компанию. Проверьте логи: /local/logs/company_manager.log'
                ];
            }
        } catch (\Exception $e) {
            $error = 'Исключение в updateCompany: ' . $e->getMessage();
            writeHandlerLog($error);
            writeHandlerLog('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $error,
                'trace' => $e->getTraceAsString()
            ];
        }
    },

    'deleteCompany' => function ($params) {
        try {
            $companyId = $params['companyId'] ?? null;

            if (!$companyId) {
                return [
                    'success' => false,
                    'error' => 'Не указан ID компании'
                ];
            }

            $result = CompanyManager::deleteCompany($companyId);

            if ($result) {
                return [
                    'success' => true,
                    'data' => [
                        'message' => 'Компания успешно удалена',
                        'companyId' => $companyId
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Не удалось удалить компанию'
                ];
            }
        } catch (\Exception $e) {
            writeHandlerLog('Error in deleteCompany: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
            ];
        }
    },

    'createRequisites' => function ($params) {
        try {
            $companyId = $params['companyId'] ?? null;
            $requisites = $params['requisites'] ?? [];

            if (!$companyId) {
                return [
                    'success' => false,
                    'error' => 'Не указан ID компании'
                ];
            }

            $result = CompanyManager::createRequisites($companyId, $requisites);

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Exception $e) {
            writeHandlerLog('Error in createRequisites: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
            ];
        }
    },
];

// Валидация action
if (empty($action)) {
    exit(json_encode([
        'success' => false,
        'error' => 'Не указано действие (action)',
        'debug' => [
            'method' => $requestMethod,
            'action' => $action,
            'params' => $params
        ]
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
    writeHandlerLog('Result: ' . print_r($result, true));
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    writeHandlerLog('Handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
