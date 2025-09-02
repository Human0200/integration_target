<?php
namespace LeadSpace\Classes\Company;

use CCrmCompany;
use CCrmFieldMulti;

class CompanyManager
{
    private static function log($message)
    {
        $logFile = __DIR__ . '/company_manager.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }

    public static function findOrCreateCompany($properties)
    {
        self::log("=== Начало findOrCreateCompany ===");
        self::log("Входящие данные: " . print_r($properties, true));

        $phone = $properties['PHONE'] ?? null;
        $email = $properties['EMAIL'] ?? null;
        $title = $properties['TITLE'] ?? $properties['COMPANY'] ?? 'Компания из интернет-магазина';
        $name = $properties['NAME'] ?? $title;

        self::log("PHONE: " . ($phone ?? 'не задан'));
        self::log("EMAIL: " . ($email ?? 'не задан'));
        self::log("TITLE: " . $title);

        if (!$phone && !$email) {
            self::log("Ошибка: не переданы PHONE и EMAIL");
            return null;
        }

        // Ищем компанию по телефону или email
        $companyId = null;

        if ($phone) {
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
                'COMPANY_TYPE' => 'CUSTOMER', // Клиент
            ];

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
            self::log("Компания найдена, обновляем данные. ID: " . $companyId);
            // Если компания найдена, обновляем её данные
            self::updateCompany($companyId, $title, $phone, $email);
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
            $company = new CCrmCompany(false);
            $result = $company->GetByID($companyId);
            if ($result === false) {
                self::log("Компания не найдена по ID: " . $companyId);
                return null;
            }
            return $result;
        } catch (\Exception $e) {
            self::log("Ошибка получения компании: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Обновляет данные компании
     */
    private static function updateCompany($companyId, $title, $phone, $email)
    {
        try {
            self::log("Обновление компании ID: " . $companyId);
            $updateFields = [
                'TITLE' => $title,
            ];
            
            // Получаем текущие данные компании
            $currentCompany = self::getCompany($companyId);
            
            if ($currentCompany) {
                self::log("Текущие данные компании получены");
                // Получаем текущие телефоны и emails
                $currentPhones = CCrmFieldMulti::GetList(
                    ['ID' => 'asc'],
                    ['ENTITY_ID' => 'COMPANY', 'ELEMENT_ID' => $companyId, 'TYPE_ID' => 'PHONE']
                );
                
                $currentEmails = CCrmFieldMulti::GetList(
                    ['ID' => 'asc'],
                    ['ENTITY_ID' => 'COMPANY', 'ELEMENT_ID' => $companyId, 'TYPE_ID' => 'EMAIL']
                );
                
                $updateFields['FM'] = [];
                
                // Проверяем и добавляем телефон, если его еще нет
                if ($phone && !self::companyHasPhoneValue($currentPhones, $phone)) {
                    self::log("Добавляем новый телефон: " . $phone);
                    $phoneCount = 0;
                    $currentPhones = CCrmFieldMulti::GetList(
                        ['ID' => 'asc'],
                        ['ENTITY_ID' => 'COMPANY', 'ELEMENT_ID' => $companyId, 'TYPE_ID' => 'PHONE']
                    );
                    while ($phoneData = $currentPhones->Fetch()) {
                        $updateFields['FM']['PHONE']['n' . $phoneCount] = [
                            'VALUE' => $phoneData['VALUE'],
                            'VALUE_TYPE' => $phoneData['VALUE_TYPE']
                        ];
                        $phoneCount++;
                    }
                    $updateFields['FM']['PHONE']['n' . $phoneCount] = [
                        'VALUE' => $phone,
                        'VALUE_TYPE' => 'WORK'
                    ];
                }
                
                // Проверяем и добавляем email, если его еще нет
                if ($email && !self::companyHasEmailValue($currentEmails, $email)) {
                    self::log("Добавляем новый email: " . $email);
                    $emailCount = 0;
                    $currentEmails = CCrmFieldMulti::GetList(
                        ['ID' => 'asc'],
                        ['ENTITY_ID' => 'COMPANY', 'ELEMENT_ID' => $companyId, 'TYPE_ID' => 'EMAIL']
                    );
                    while ($emailData = $currentEmails->Fetch()) {
                        $updateFields['FM']['EMAIL']['n' . $emailCount] = [
                            'VALUE' => $emailData['VALUE'],
                            'VALUE_TYPE' => $emailData['VALUE_TYPE']
                        ];
                        $emailCount++;
                    }
                    $updateFields['FM']['EMAIL']['n' . $emailCount] = [
                        'VALUE' => $email,
                        'VALUE_TYPE' => 'WORK'
                    ];
                }
            }
            
            self::log("Поля для обновления: " . print_r($updateFields, true));
            // Обновляем компанию
            $company = new CCrmCompany(false);
            $result = $company->Update($companyId, $updateFields);
            if ($result) {
                self::log("Компания успешно обновлена");
            } else {
                self::log("Ошибка обновления компании: " . $company->LAST_ERROR);
            }
            
        } catch (\Exception $e) {
            self::log("Исключение при обновлении компании: " . $e->getMessage());
        }
    }
    
    /**
     * Обновляет поля компании
     */
    public static function updateCompanyFields($companyId, $fields)
    {
        try {
            self::log("Обновление полей компании ID: " . $companyId);
            self::log("Поля для обновления: " . print_r($fields, true));
            $company = new CCrmCompany(false);
            $result = $company->Update($companyId, $fields);
            if ($result) {
                self::log("Поля компании успешно обновлены");
                return $companyId;
            } else {
                self::log("Ошибка обновления полей компании: " . $company->LAST_ERROR);
                return false;
            }
        } catch (\Exception $e) {
            self::log("Исключение при обновлении полей компании: " . $e->getMessage());
            return false;
        }
    }
    
/**
 * Создает реквизиты компании
 */
public static function createRequisites($companyId, $requisites)
{
    self::log("Создание реквизитов для компании ID: " . $companyId);
    self::log("Реквизиты: " . print_r($requisites, true));
    
    try {
        // Подключаем необходимые модули
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            throw new \Exception('CRM module not loaded');
        }
        
        // Получаем существующие реквизиты компании
        $requisite = new \Bitrix\Crm\EntityRequisite();
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
            'NAME' => !empty($reqData[0]['NAME']) ? $reqData[0]['NAME'] : 'Реквизиты компании',
            'SORT' => 500,
            'ACTIVE' => 'Y'
        ];
        
        // Добавляем поля реквизитов
        if (!empty($requisites['INN'])) {
            $fields['RQ_INN'] = $requisites['INN'];
        }
        
        if (!empty($requisites['KPP'])) {
            $fields['RQ_KPP'] = $requisites['KPP'];
        }
        
        if (!empty($requisites['OGRN'])) {
            $fields['RQ_OGRN'] = $requisites['OGRN'];
        }
        
        if (!empty($requisites['ADDRESS'])) {
            $fields['RQ_ADDR'] = $requisites['ADDRESS'];
        }
        
        if (!empty($requisites['PHONE'])) {
            $fields['RQ_PHONE'] = $requisites['PHONE'];
        }
        
        if (!empty($requisites['EMAIL'])) {
            $fields['RQ_EMAIL'] = $requisites['EMAIL'];
        }
        
        if (!empty($requisites['CONTACT_PERSON'])) {
            $fields['RQ_CONTACT'] = $requisites['CONTACT_PERSON'];
        }
        
        if (!empty($requisites['RESPONSIBLE_PERSON'])) {
            $fields['RQ_DIRECTOR'] = $requisites['RESPONSIBLE_PERSON'];
        }
        
        if (!empty($requisites['COMMENT'])) {
            $fields['COMMENTS'] = $requisites['COMMENT'];
        }
        
        self::log("Поля реквизитов для создания: " . print_r($fields, true));
        
        // Создаем новые реквизиты
        $result = $requisite->add($fields);
        
        if ($result) {
            // Получаем ID из результата
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
            'message' => 'Исключение при создании реквизитов: ' . $e->getMessage()
        ];
    }
}
    private static function companyHasPhoneValue($phoneResult, $phone)
    {
        $normalizedSearchPhone = self::normalizePhone($phone);
        self::log("Поиск телефона: " . $phone . " (нормализованный: " . $normalizedSearchPhone . ")");
        
        while ($phoneData = $phoneResult->Fetch()) {
            $normalizedCompanyPhone = self::normalizePhone($phoneData['VALUE']);
            self::log("Сравнение с: " . $phoneData['VALUE'] . " (нормализованный: " . $normalizedCompanyPhone . ")");
            if ($normalizedCompanyPhone === $normalizedSearchPhone) {
                self::log("Телефон найден в компании");
                return true;
            }
        }
        
        self::log("Телефон не найден в компании");
        return false;
    }
    
    private static function companyHasEmailValue($emailResult, $email)
    {
        $normalizedSearchEmail = strtolower(trim($email));
        self::log("Поиск email: " . $email . " (нормализованный: " . $normalizedSearchEmail . ")");
        
        while ($emailData = $emailResult->Fetch()) {
            $normalizedCompanyEmail = strtolower(trim($emailData['VALUE']));
            self::log("Сравнение с: " . $emailData['VALUE'] . " (нормализованный: " . $normalizedCompanyEmail . ")");
            if ($normalizedCompanyEmail === $normalizedSearchEmail) {
                self::log("Email найден в компании");
                return true;
            }
        }
        
        self::log("Email не найден в компании");
        return false;
    }

    private static function findCompanyByPhone($phone)
    {
        if (empty($phone)) {
            self::log("Пустой телефон для поиска");
            return null;
        }

        self::log("Поиск компании по телефону: " . $phone);
        // Генерируем все возможные варианты формата телефона
        $phoneVariations = self::getPhoneVariations($phone);
        self::log("Варианты телефона для поиска: " . print_r($phoneVariations, true));
        
        // Ищем по каждому варианту
        foreach ($phoneVariations as $phoneVariation) {
            try {
                self::log("Поиск по варианту: " . $phoneVariation);
                // Ищем через мультиполя
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

    private static function findCompanyByEmail($email)
    {
        if (empty($email)) {
            self::log("Пустой email для поиска");
            return null;
        }
        
        self::log("Поиск компании по email: " . $email);
        try {
            // Ищем через мультиполя
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
     * Генерирует все возможные варианты форматирования телефона
     */
    private static function getPhoneVariations($phone)
    {
        self::log("Генерация вариантов телефона: " . $phone);
        $normalizedPhone = self::normalizePhone($phone);
        self::log("Нормализованный телефон: " . $normalizedPhone);
        
        if (empty($normalizedPhone) || strlen($normalizedPhone) !== 11) {
            self::log("Некорректная длина телефона, возвращаем исходный");
            return [$phone];
        }
        
        $variations = [];
        
        // +7 XXX XXX-XX-XX
        $variations[] = '+' . $normalizedPhone[0] . ' ' . 
                       substr($normalizedPhone, 1, 3) . ' ' . 
                       substr($normalizedPhone, 4, 3) . '-' . 
                       substr($normalizedPhone, 7, 2) . '-' . 
                       substr($normalizedPhone, 9, 2);
        
        // 8 XXX XXX-XX-XX
        $variations[] = '8 ' . 
                       substr($normalizedPhone, 1, 3) . ' ' . 
                       substr($normalizedPhone, 4, 3) . '-' . 
                       substr($normalizedPhone, 7, 2) . '-' . 
                       substr($normalizedPhone, 9, 2);
        
        // +7XXXXXXXXXX
        $variations[] = '+' . $normalizedPhone;
        
        // 7XXXXXXXXXX
        $variations[] = $normalizedPhone;
        
        // 8XXXXXXXXXX
        $variations[] = '8' . substr($normalizedPhone, 1);
        
        // +7(XXX)XXX-XX-XX
        $variations[] = '+' . $normalizedPhone[0] . '(' . 
                       substr($normalizedPhone, 1, 3) . ')' . 
                       substr($normalizedPhone, 4, 3) . '-' . 
                       substr($normalizedPhone, 7, 2) . '-' . 
                       substr($normalizedPhone, 9, 2);
        
        // Исходный формат
        $variations[] = $phone;
        
        // Убираем дубликаты
        $uniqueVariations = array_unique($variations);
        self::log("Сгенерированные варианты: " . print_r($uniqueVariations, true));
        return $uniqueVariations;
    }

    /**
     * Нормализует номер телефона для сравнения
     */
    private static function normalizePhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        self::log("Нормализация телефона: " . $phone);
        // Убираем все кроме цифр
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        self::log("Очищенный телефон: " . $cleanPhone);
        
        // Обрабатываем российские номера
        if (strlen($cleanPhone) === 11) {
            if ($cleanPhone[0] === '8') {
                $cleanPhone = '7' . substr($cleanPhone, 1);
            }
        } elseif (strlen($cleanPhone) === 10) {
            $cleanPhone = '7' . $cleanPhone;
        }
        
        self::log("Нормализованный телефон: " . $cleanPhone);
        return $cleanPhone;
    }
}