<?php

namespace LeadSpace\Classes\Company;

use CCrmCompany;
use CCrmFieldMulti;

class CompanyManager
{
    private static function log($message)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/company_manager.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    public static function findOrCreateCompany($properties)
    {
        self::log("=== Начало findOrCreateCompany ===");
        self::log("Входящие данные: " . print_r($properties, true));

        $phone = $properties['PHONE'] ?? null;
        $email = $properties['EMAIL'] ?? null;
        $title = $properties['TITLE'] ?? $properties['COMPANY'] ?? 'Компания из интернет-магазина';
        $inn = $properties['INN'] ?? null;

        self::log("PHONE: " . ($phone ?? 'не задан'));
        self::log("EMAIL: " . ($email ?? 'не задан'));
        self::log("TITLE: " . $title);
        self::log("INN: " . ($inn ?? 'не задан'));

        // Проверяем обязательные поля
        if (!$title && !$inn && !$phone && !$email) {
            self::log("Ошибка: не переданы обязательные поля");
            return null;
        }

        // Ищем компанию по ИНН, телефону или email
        $companyId = null;

        if ($inn) {
            self::log("Поиск компании по ИНН...");
            $companyId = self::findCompanyByINN($inn);
            self::log("Результат поиска по ИНН: " . ($companyId ?? 'не найдено'));
        }

        if (!$companyId && $phone) {
            self::log("Поиск компании по телефону...");
            $companyId = self::findCompanyByPhone($phone);
            self::log("Результат поиска по телефону: " . ($companyId ?? 'не найдено'));
        }

        if (!$companyId && $email) {
            self::log("Поиск компании по email...");
            $companyId = self::findCompanyByEmail($email);
            self::log("Результат поиска по email: " . ($companyId ?? 'не найдено'));
        }

        // Если компания не найдена - создаем новую
        if (!$companyId) {
            self::log("Компания не найдена, создаем новую...");

            $companyFields = [
                'TITLE' => $title,
                'COMPANY_TYPE' => 'CUSTOMER',
                'ASSIGNED_BY_ID' => $properties['ASSIGNED_BY_ID'] ?? 1,
                'CREATED_BY_ID' => $properties['ASSIGNED_BY_ID'] ?? 1,
            ];

            // Добавляем все остальные поля из properties (кроме мультиполей)
            foreach ($properties as $key => $value) {
                if (!in_array($key, ['PHONE', 'EMAIL'])) {
                    $companyFields[$key] = $value;
                }
            }

            // Добавляем мультиполя
            $companyFields['FM'] = [];

            if ($phone) {
                $companyFields['FM']['PHONE'] = [
                    'n0' => [
                        'VALUE' => $phone,
                        'VALUE_TYPE' => 'WORK'
                    ]
                ];
            }

            if ($email) {
                $companyFields['FM']['EMAIL'] = [
                    'n0' => [
                        'VALUE' => $email,
                        'VALUE_TYPE' => 'WORK'
                    ]
                ];
            }

            self::log("Поля для создания компании: " . print_r($companyFields, true));
            $companyId = self::createCompany($companyFields);
            self::log("Результат создания компании: " . ($companyId ?? 'ошибка'));
        } else {
            self::log("Компания найдена ID: " . $companyId . ", обновляем поля...");

            // Используем универсальный метод обновления
            $result = self::updateCompanyFields($companyId, $properties);

            if ($result) {
                self::log("Компания успешно обновлена");
            } else {
                self::log("Ошибка обновления компании");
            }
        }

        self::log("=== Завершение findOrCreateCompany, результат: " . ($companyId ?? 'null') . " ===\n");
        return $companyId;
    }

    /**
     * Создает новую компанию
     */
    private static function createCompany($fields)
    {
        try {
            self::log("Создание компании через CCrmCompany::Add()");
            $company = new CCrmCompany(false);
            $companyId = $company->Add($fields);

            if ($companyId > 0) {
                self::log("Компания успешно создана, ID: " . $companyId);
                return $companyId;
            } else {
                self::log("Ошибка создания компании: " . $company->LAST_ERROR);
                return null;
            }
        } catch (\Exception $e) {
            self::log("Исключение при создании компании: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получает данные компании по ID
     */
    private static function getCompany($companyId)
    {
        try {
            self::log("Получение данных компании ID: " . $companyId);

            // Используем GetListEx для надежности (работает стабильнее чем GetByID)
            $res = CCrmCompany::GetListEx(
                [],
                [
                    'ID' => $companyId,
                    'CHECK_PERMISSIONS' => 'N'
                ],
                false,
                ['nTopCount' => 1],
                ['ID', 'TITLE', 'COMPANY_TYPE', 'STAGE_ID']
            );

            if ($company = $res->Fetch()) {
                self::log("Компания найдена успешно: ID=" . $company['ID'] . ", TITLE=" . ($company['TITLE'] ?? 'не указано'));
                return $company;
            }

            self::log("Компания с ID {$companyId} не найдена в базе");
            return null;
        } catch (\Exception $e) {
            self::log("Исключение при получении компании: " . $e->getMessage());
            self::log("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Универсальное обновление полей компании
     * Принимает любые поля для обновления
     */
    public static function updateCompanyFields($companyId, $fields)
    {
        try {
            self::log("Входящие поля для обновления: " . print_r($fields, true));

            if (empty($companyId)) {
                $error = "Пустой ID компании";
                self::log("Ошибка: " . $error);
                return ['success' => false, 'error' => $error];
            }

            // Проверяем существование компании
            $company = self::getCompany($companyId);
            if (!$company) {
                $error = "Компания с ID {$companyId} не найдена в системе";
                self::log("Ошибка: " . $error);
                return ['success' => false, 'error' => $error];
            }

            // Разделяем поля на обычные и мультиполя
            $updateFields = [];
            $multiFields = [];

            foreach ($fields as $fieldName => $fieldValue) {
                // Обрабатываем мультиполя отдельно
                if ($fieldName === 'PHONE' || $fieldName === 'EMAIL') {
                    $multiFields[$fieldName] = $fieldValue;
                    self::log("Мультиполе найдено: {$fieldName} = {$fieldValue}");
                }
                // Все остальные поля - обычные поля компании
                else {
                    $updateFields[$fieldName] = $fieldValue;
                    self::log("Обычное поле найдено: {$fieldName} = " . (is_array($fieldValue) ? print_r($fieldValue, true) : $fieldValue));
                }
            }

            self::log("Обычные поля для обновления: " . print_r($updateFields, true));
            self::log("Мультиполя для обновления: " . print_r($multiFields, true));

            // Обновляем обычные поля если они есть
            if (!empty($updateFields)) {
                self::log("Начинаем обновление обычных полей...");
                $crmCompany = new CCrmCompany(false);
                $result = $crmCompany->Update($companyId, $updateFields);

                if ($result) {
                    self::log("Обычные поля успешно обновлены");
                } else {
                    $errorMsg = $crmCompany->LAST_ERROR;
                    $detailedError = "Ошибка обновления полей компании: " . ($errorMsg ?: 'Неизвестная ошибка');
                    $detailedError .= ". Поля: " . implode(', ', array_keys($updateFields));

                    self::log($detailedError);
                    self::log("Детали полей: " . print_r($updateFields, true));

                    return ['success' => false, 'error' => $detailedError];
                }
            } else {
                self::log("Нет обычных полей для обновления");
            }

            // Обновляем мультиполя если они есть
            if (!empty($multiFields)) {
                self::log("Начинаем обновление мультиполей...");
                $result = self::updateMultiFields($companyId, $multiFields);

                if (is_array($result) && !$result['success']) {
                    return $result;
                } elseif (!$result) {
                    $error = "Ошибка обновления мультиполей (PHONE/EMAIL)";
                    self::log($error);
                    return ['success' => false, 'error' => $error];
                }

                self::log("Мультиполя успешно обновлены");
            } else {
                self::log("Нет мультиполей для обновления");
            }

            self::log("=== Компания успешно обновлена ===");
            return ['success' => true, 'companyId' => $companyId];
        } catch (\Exception $e) {
            $error = "Исключение при обновлении компании: " . $e->getMessage();
            self::log($error);
            self::log("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'error' => $error, 'trace' => $e->getTraceAsString()];
        }
    }

    // Обновленный метод updateMultiFields
    private static function updateMultiFields($companyId, $multiFields)
    {
        try {
            self::log("Обновление мультиполей для компании {$companyId}");

            $fieldMulti = new CCrmFieldMulti();
            $updateData = [];

            foreach ($multiFields as $fieldType => $fieldValue) {
                // Получаем существующие значения
                $existingFields = CCrmFieldMulti::GetList(
                    ['ID' => 'asc'],
                    [
                        'ENTITY_ID' => 'COMPANY',
                        'ELEMENT_ID' => $companyId,
                        'TYPE_ID' => $fieldType
                    ]
                );

                // Проверяем, нужно ли добавлять новое значение
                $valueExists = false;
                $counter = 0;

                while ($field = $existingFields->Fetch()) {
                    $updateData[$fieldType]['n' . $counter] = [
                        'VALUE' => $field['VALUE'],
                        'VALUE_TYPE' => $field['VALUE_TYPE']
                    ];

                    // Проверяем совпадение значения
                    if ($fieldType === 'PHONE') {
                        if (self::normalizePhone($field['VALUE']) === self::normalizePhone($fieldValue)) {
                            $valueExists = true;
                        }
                    } else {
                        if (strtolower(trim($field['VALUE'])) === strtolower(trim($fieldValue))) {
                            $valueExists = true;
                        }
                    }

                    $counter++;
                }

                // Добавляем новое значение если его еще нет
                if (!$valueExists) {
                    $updateData[$fieldType]['n' . $counter] = [
                        'VALUE' => $fieldValue,
                        'VALUE_TYPE' => 'WORK'
                    ];
                    self::log("Добавляем новое значение {$fieldType}: {$fieldValue}");
                } else {
                    self::log("Значение {$fieldType}: {$fieldValue} уже существует");
                }
            }

            // Применяем изменения
            if (!empty($updateData)) {
                $result = $fieldMulti->SetFields('COMPANY', $companyId, $updateData);
                if (!$result) {
                    $error = "Ошибка SetFields для мультиполей";
                    self::log($error);
                    return ['success' => false, 'error' => $error];
                }
                self::log("Мультиполя успешно обновлены");
            }

            return ['success' => true];
        } catch (\Exception $e) {
            $error = "Исключение при обновлении мультиполей: " . $e->getMessage();
            self::log($error);
            return ['success' => false, 'error' => $error];
        }
    }

    /**
     * Удаляет компанию по ID
     */
    public static function deleteCompany($companyId, $options = [])
    {
        if (empty($companyId)) {
            self::log('DeleteCompany: Company ID is empty');
            return false;
        }

        try {
            self::log("=== Удаление компании ID: {$companyId} ===");

            $bCheckRight = false;
            $entityObject = new \CCrmCompany($bCheckRight);

            $deleteOptions = [
                'CURRENT_USER' => 1,
                'PROCESS_BIZPROC' => true,
                'ENABLE_DEFERRED_MODE' => \Bitrix\Crm\Settings\CompanySettings::getCurrent()->isDeferredCleaningEnabled(),
                'ENABLE_DUP_INDEX_INVALIDATION' => true,
            ];

            self::log("Параметры удаления: " . print_r($deleteOptions, true));

            $deleteResult = $entityObject->Delete($companyId, $deleteOptions);

            if ($deleteResult) {
                self::log("Компания {$companyId} успешно удалена");
                return true;
            } else {
                self::log("Ошибка: " . $entityObject->LAST_ERROR);
                return false;
            }
        } catch (\Exception $e) {
            self::log('Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Создает реквизиты компании
     */
    public static function createRequisites($companyId, $requisites)
    {
        self::log("=== Создание реквизитов для компании ID: {$companyId} ===");
        self::log("Реквизиты: " . print_r($requisites, true));

        try {
            if (!\Bitrix\Main\Loader::includeModule('crm')) {
                throw new \Exception('CRM module not loaded');
            }

            $requisite = new \Bitrix\Crm\EntityRequisite();

            // Получаем существующие реквизиты компании
            $rs = $requisite->getList([
                "filter" => ["ENTITY_ID" => $companyId, "ENTITY_TYPE_ID" => \CCrmOwnerType::Company]
            ]);
            $reqData = $rs->fetchAll();

            self::log("Найдено существующих реквизитов: " . count($reqData));

            // Если реквизиты существуют, удаляем их
            if (!empty($reqData)) {
                self::log("Удаляем существующие реквизиты");
                $requisite->deleteByEntity(\CCrmOwnerType::Company, $companyId);
            }

            // Подготавливаем данные для реквизитов
            $fields = [
                'ENTITY_ID' => $companyId,
                'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                'PRESET_ID' => !empty($reqData[0]['PRESET_ID']) ? $reqData[0]['PRESET_ID'] : 1,
                'NAME' => $requisites['NAME'] ?? 'Реквизиты компании',
                'SORT' => 500,
                'ACTIVE' => 'Y'
            ];

            // Добавляем поля реквизитов
            $requisiteFields = [
                'INN' => 'RQ_INN',
                'KPP' => 'RQ_KPP',
                'OGRN' => 'RQ_OGRN',
                'ADDRESS' => 'RQ_ADDR',
                'PHONE' => 'RQ_PHONE',
                'EMAIL' => 'RQ_EMAIL',
                'CONTACT_PERSON' => 'RQ_CONTACT',
                'RESPONSIBLE_PERSON' => 'RQ_DIRECTOR',
                'COMMENT' => 'COMMENTS'
            ];

            foreach ($requisiteFields as $key => $fieldName) {
                if (!empty($requisites[$key])) {
                    $fields[$fieldName] = $requisites[$key];
                }
            }

            self::log("Поля реквизитов для создания: " . print_r($fields, true));

            // Создаем новые реквизиты
            $result = $requisite->add($fields);

            if ($result) {
                $requisiteId = is_object($result) ? $result->getId() : $result;
                self::log("Реквизиты успешно созданы, ID: " . $requisiteId);
                return [
                    'success' => true,
                    'message' => 'Реквизиты успешно созданы',
                    'requisiteId' => $requisiteId
                ];
            } else {
                self::log("Ошибка создания реквизитов");
                return [
                    'success' => false,
                    'message' => 'Ошибка создания реквизитов'
                ];
            }
        } catch (\Exception $e) {
            self::log("Исключение при создании реквизитов: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Исключение: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Поиск компании по ИНН
     */
    private static function findCompanyByINN($inn)
    {
        if (empty($inn)) {
            return null;
        }

        self::log("Поиск компании по ИНН: " . $inn);

        try {
            $filter = ['UF_CRM_INN' => $inn];

            $res = CCrmCompany::GetListEx(
                [],
                $filter,
                false,
                ['nTopCount' => 1],
                ['ID']
            );

            if ($company = $res->Fetch()) {
                self::log("Компания найдена по ИНН, ID: " . $company['ID']);
                return $company['ID'];
            }
        } catch (\Exception $e) {
            self::log("Ошибка поиска компании по ИНН: " . $e->getMessage());
        }

        self::log("Компания по ИНН не найдена");
        return null;
    }

    /**
     * Поиск компании по телефону
     */
    private static function findCompanyByPhone($phone)
    {
        if (empty($phone)) {
            return null;
        }

        self::log("Поиск компании по телефону: " . $phone);
        $phoneVariations = self::getPhoneVariations($phone);

        foreach ($phoneVariations as $phoneVariation) {
            try {
                $result = CCrmFieldMulti::GetList(
                    ['ID' => 'asc'],
                    [
                        'ENTITY_ID' => 'COMPANY',
                        'TYPE_ID' => 'PHONE',
                        'VALUE' => $phoneVariation
                    ]
                );

                if ($phoneData = $result->Fetch()) {
                    self::log("Компания найдена по телефону, ID: " . $phoneData['ELEMENT_ID']);
                    return $phoneData['ELEMENT_ID'];
                }
            } catch (\Exception $e) {
                self::log("Ошибка поиска компании по телефону: " . $e->getMessage());
            }
        }

        self::log("Компания по телефону не найдена");
        return null;
    }

    /**
     * Поиск компании по email
     */
    private static function findCompanyByEmail($email)
    {
        if (empty($email)) {
            return null;
        }

        self::log("Поиск компании по email: " . $email);

        try {
            $result = CCrmFieldMulti::GetList(
                ['ID' => 'asc'],
                [
                    'ENTITY_ID' => 'COMPANY',
                    'TYPE_ID' => 'EMAIL',
                    'VALUE' => $email
                ]
            );

            if ($emailData = $result->Fetch()) {
                self::log("Компания найдена по email, ID: " . $emailData['ELEMENT_ID']);
                return $emailData['ELEMENT_ID'];
            }
        } catch (\Exception $e) {
            self::log("Ошибка поиска компании по email: " . $e->getMessage());
        }

        self::log("Компания по email не найдена");
        return null;
    }

    /**
     * Поиск компании по targetid (пользовательское поле UF_CRM_)
     */
    public static function findCompanyByTargetID($targetid)
    {
        if (empty($targetid)) {
            return null;
        }

        try {
            
            $dbCompanies = CCrmCompany::GetList(
                ['ID' => 'asc'], 
                [
                    'UF_CRM_1760515262922' => $targetid, 
                    'CHECK_PERMISSIONS' => 'N' // игнорировать проверку прав
                ],
                ['ID'] 
            );

            if ($company = $dbCompanies->Fetch()) {
                self::log("Компания найдена по targetid, ID: " . $company['ID']);
                return $company['ID'];
            }
        } catch (\Exception $e) {
            self::log("Ошибка поиска компании по targetid: " . $e->getMessage());
        }

        self::log("Компания по targetid '{$targetid}' не найдена");
        return null;
    }

    /**
     * Генерирует варианты форматирования телефона
     */
    private static function getPhoneVariations($phone)
    {
        $normalizedPhone = self::normalizePhone($phone);

        if (empty($normalizedPhone) || strlen($normalizedPhone) !== 11) {
            return [$phone];
        }

        $variations = [];

        $variations[] = '+' . $normalizedPhone[0] . ' ' .
            substr($normalizedPhone, 1, 3) . ' ' .
            substr($normalizedPhone, 4, 3) . '-' .
            substr($normalizedPhone, 7, 2) . '-' .
            substr($normalizedPhone, 9, 2);

        $variations[] = '8 ' .
            substr($normalizedPhone, 1, 3) . ' ' .
            substr($normalizedPhone, 4, 3) . '-' .
            substr($normalizedPhone, 7, 2) . '-' .
            substr($normalizedPhone, 9, 2);

        $variations[] = '+' . $normalizedPhone;
        $variations[] = $normalizedPhone;
        $variations[] = '8' . substr($normalizedPhone, 1);

        $variations[] = '+' . $normalizedPhone[0] . '(' .
            substr($normalizedPhone, 1, 3) . ')' .
            substr($normalizedPhone, 4, 3) . '-' .
            substr($normalizedPhone, 7, 2) . '-' .
            substr($normalizedPhone, 9, 2);

        $variations[] = $phone;

        return array_unique($variations);
    }

    /**
     * Нормализует номер телефона
     */
    private static function normalizePhone($phone)
    {
        if (empty($phone)) {
            return '';
        }

        $cleanPhone = preg_replace('/[^\d]/', '', $phone);

        if (strlen($cleanPhone) === 11) {
            if ($cleanPhone[0] === '8') {
                $cleanPhone = '7' . substr($cleanPhone, 1);
            }
        } elseif (strlen($cleanPhone) === 10) {
            $cleanPhone = '7' . $cleanPhone;
        }

        return $cleanPhone;
    }
}
