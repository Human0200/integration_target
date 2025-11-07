<?php

namespace LeadSpace\Classes\Contacts;

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ExpressionField;

class FindContact
{
    private static function writeLog($message)
    {
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/contact_debug.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    public static function findOrCreateContact($properties)
    {
        self::writeLog('=== findOrCreateContact called ===');
        self::writeLog('Properties: ' . print_r($properties, true));

        $phone = $properties['PHONE'] ?? null;
        $email = $properties['EMAIL'] ?? null;
        $name = $properties['NAME'] ?? $properties['FIO'] ?? 'Клиент из интернет-магазина';
        $lastName = $properties['LAST_NAME'] ?? '';

        $companyId = $properties['COMPANY_ID'] ?? null;

        self::writeLog("Parsed data - Phone: {$phone}, Email: {$email}, Name: {$name}, CompanyId: {$companyId}");

        if (!$phone && !$email) {
            self::writeLog('No phone and no email provided - returning null');
            return null;
        }

        // Ищем контакт по телефону или email только среди контактов с заполненным COMPANY_ID
        $contactId = null;

        if ($phone) {
            $contactId = self::findContactByPhone($phone, $companyId);
            self::writeLog("Search by phone result: " . ($contactId ? $contactId : 'not found'));
        }

        if (!$contactId && $email) {
            $contactId = self::findContactByEmail($email, $companyId);
            self::writeLog("Search by email result: " . ($contactId ? $contactId : 'not found'));
        }

        // Если контакт не найден - создаем новый
        if (!$contactId) {
            self::writeLog('Contact not found, creating new one');

            // ИСПРАВЛЕНИЕ: Создаем контакт БЕЗ мультиполей
            $contactFields = [
                'NAME' => $name,
                'LAST_NAME' => $lastName,
            ];

            // Добавляем COMPANY_ID если он есть
            if ($companyId) {
                $contactFields['COMPANY_ID'] = $companyId;
            }

            self::writeLog('Contact fields to create: ' . print_r($contactFields, true));

            $createResult = self::createContact($contactFields);

            self::writeLog('Create result type: ' . gettype($createResult));

            // Проверяем не null ли результат
            if ($createResult && is_object($createResult) && method_exists($createResult, 'isSuccess') && $createResult->isSuccess()) {
                $contactId = $createResult->getId();
                self::writeLog("Contact successfully created with ID: {$contactId}");

                // ИСПРАВЛЕНИЕ: Теперь добавляем телефон и email через CCrmFieldMulti
                if ($phone || $email) {
                    self::addContactMultiFields($contactId, $phone, $email);
                }
            } else {
                // Логируем ошибку если есть
                if ($createResult && is_object($createResult) && method_exists($createResult, 'getErrorMessages')) {
                    $errors = $createResult->getErrorMessages();
                    self::writeLog('Error creating contact: ' . implode(', ', $errors));
                } else {
                    self::writeLog('Error creating contact: createContact returned invalid result - ' . print_r($createResult, true));
                }
                return null;
            }
        } else {
            self::writeLog("Contact found with ID: {$contactId}, updating...");
            // Если контакт найден, обновляем его данные
            self::updateContact($contactId, $name, $lastName, $phone, $email);
        }

        self::writeLog("Returning contact ID: {$contactId}");
        self::writeLog('=== End findOrCreateContact ===');
        return $contactId;
    }

    /**
     * Добавляет телефон и email к контакту
     */
    private static function addContactMultiFields($contactId, $phone, $email)
    {
        try {
            $fieldMulti = new \CCrmFieldMulti();
            $fields = [];

            if ($phone) {
                $fields['PHONE'] = [
                    'n0' => [
                        'VALUE' => $phone,
                        'VALUE_TYPE' => 'WORK'
                    ]
                ];
                self::writeLog("Adding phone {$phone} to contact {$contactId}");
            }

            if ($email) {
                $fields['EMAIL'] = [
                    'n0' => [
                        'VALUE' => $email,
                        'VALUE_TYPE' => 'WORK'
                    ]
                ];
                self::writeLog("Adding email {$email} to contact {$contactId}");
            }

            if (!empty($fields)) {
                $result = $fieldMulti->SetFields('CONTACT', $contactId, $fields);
                self::writeLog("SetFields result: " . ($result ? 'success' : 'failed'));
            }
        } catch (\Exception $e) {
            self::writeLog('Exception in addContactMultiFields: ' . $e->getMessage());
        }
    }

    /**
     * Создает новый контакт через D7 ORM
     */
    /**
     * Создает новый контакт через D7 ORM
     */
    private static function createContact($fields)
    {
        try {
            // Добавляем обязательные поля
            if (!isset($fields['ASSIGNED_BY_ID'])) {
                $fields['ASSIGNED_BY_ID'] = 1; // Ответственный
            }

            if (!isset($fields['MODIFY_BY_ID'])) {
                $fields['MODIFY_BY_ID'] = 1; // Кем обновлён
            }

            if (!isset($fields['CREATED_BY_ID'])) {
                $fields['CREATED_BY_ID'] = 1; // Кем создан
            }

            self::writeLog('Attempting to create contact with ContactTable::add()');
            self::writeLog('Fields with required IDs: ' . print_r($fields, true));

            $result = ContactTable::add($fields);
            self::writeLog('ContactTable::add() completed');

            if ($result && is_object($result)) {
                self::writeLog('Result is valid object');
                if (method_exists($result, 'isSuccess')) {
                    self::writeLog('isSuccess method exists, result: ' . ($result->isSuccess() ? 'true' : 'false'));
                    if (!$result->isSuccess() && method_exists($result, 'getErrorMessages')) {
                        self::writeLog('Errors: ' . implode(', ', $result->getErrorMessages()));
                    }
                }
            }

            return $result;
        } catch (\Exception $e) {
            self::writeLog('Exception in createContact: ' . $e->getMessage());
            self::writeLog('Exception trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Получает данные контакта по ID
     */
    private static function getContact($contactId)
    {
        try {
            $result = ContactTable::getByPrimary($contactId, [
                'select' => ['ID', 'NAME', 'LAST_NAME', 'COMPANY_ID']
            ]);

            if ($result && $result->fetch()) {
                return $result;
            }
            return null;
        } catch (\Exception $e) {
            self::writeLog('Error getting contact: ' . $e->getMessage());
            return null;
        }
    }

    private static function updateContact($contactId, $name, $lastName, $phone, $email)
    {
        $updateFields = [
            'NAME' => $name,
            'LAST_NAME' => $lastName,
        ];

        // Создаем объект для работы с мультиполями
        $fieldMulti = new \CCrmFieldMulti();

        // Мультиполя (телефоны и email) нужно обновлять через CCrmFieldMulti
        if ($phone) {
            $existingPhones = \CCrmFieldMulti::GetEntityFields('CONTACT', $contactId, 'PHONE', false);
            $phoneExists = false;

            if (!empty($existingPhones)) {
                $normalizedPhone = self::normalizePhone($phone);
                foreach ($existingPhones as $existingPhone) {
                    if (self::normalizePhone($existingPhone['VALUE']) === $normalizedPhone) {
                        $phoneExists = true;
                        break;
                    }
                }
            }

            if (!$phoneExists) {
                $fieldMulti->SetFields('CONTACT', $contactId, [
                    'PHONE' => [
                        'n0' => [
                            'VALUE' => $phone,
                            'VALUE_TYPE' => 'WORK'
                        ]
                    ]
                ]);
                self::writeLog("Added phone {$phone} to contact {$contactId}");
            }
        }

        if ($email) {
            $existingEmails = \CCrmFieldMulti::GetEntityFields('CONTACT', $contactId, 'EMAIL', false);
            $emailExists = false;

            if (!empty($existingEmails)) {
                $normalizedEmail = strtolower(trim($email));
                foreach ($existingEmails as $existingEmail) {
                    if (strtolower(trim($existingEmail['VALUE'])) === $normalizedEmail) {
                        $emailExists = true;
                        break;
                    }
                }
            }

            if (!$emailExists) {
                $fieldMulti->SetFields('CONTACT', $contactId, [
                    'EMAIL' => [
                        'n0' => [
                            'VALUE' => $email,
                            'VALUE_TYPE' => 'WORK'
                        ]
                    ]
                ]);
                self::writeLog("Added email {$email} to contact {$contactId}");
            }
        }

        // Обновляем основные поля
        try {
            $result = ContactTable::update($contactId, $updateFields);
            if (!$result->isSuccess()) {
                $errors = $result->getErrorMessages();
                self::writeLog('Error updating contact: ' . implode(', ', $errors));
            }
        } catch (\Exception $e) {
            self::writeLog('Exception in updateContact: ' . $e->getMessage());
        }
    }

    /**
     * Обновляет поля контакта
     */
public static function updateContactFields($contactId, $fields)
{
    try {
        writeHandlerLog('Attempting to update contact ' . $contactId . ' with fields: ' . print_r($fields, true));
        
        // Проверяем существование контакта
        $contact = ContactTable::getById($contactId)->fetch();
        if (!$contact) {
            writeHandlerLog('Contact not found: ' . $contactId);
            return false;
        }
        
        writeHandlerLog('Contact found, updating...');
        
        // Разделяем обычные поля и мультиполя (EMAIL, PHONE)
        $regularFields = [];
        $multiFields = [];
        
        foreach ($fields as $key => $value) {
            if ($key === 'EMAIL' || $key === 'PHONE') {
                $multiFields[$key] = $value;
            } else {
                $regularFields[$key] = $value;
            }
        }
        
        // Обновляем обычные поля
        if (!empty($regularFields)) {
            $updateResult = ContactTable::update($contactId, $regularFields);
            if (!$updateResult->isSuccess()) {
                $errors = $updateResult->getErrorMessages();
                writeHandlerLog('Update failed for regular fields: ' . print_r($errors, true));
                return false;
            }
        }
        
        // Обновляем мультиполя (EMAIL, PHONE)
        if (!empty($multiFields)) {
            $contactEntity = new \CCrmContact(false);
            $multiUpdateFields = ['FM' => []];
            
            foreach ($multiFields as $fieldType => $fieldValue) {
                if (!empty($fieldValue)) {
                    $multiUpdateFields['FM'][$fieldType] = [
                        'n0' => [
                            'VALUE' => $fieldValue,
                            'VALUE_TYPE' => 'WORK'
                        ]
                    ];
                }
            }
            
            writeHandlerLog('Updating multi fields: ' . print_r($multiUpdateFields, true));
            $multiResult = $contactEntity->Update($contactId, $multiUpdateFields);
            if (!$multiResult) {
                writeHandlerLog('Failed to update multi fields: ' . $contactEntity->LAST_ERROR);
                return false;
            }
        }
        
        writeHandlerLog('Contact updated successfully: ' . $contactId);
        return $contactId;
        
    } catch (\Exception $e) {
        writeHandlerLog('Exception in updateContactFields for contact ' . $contactId . ': ' . $e->getMessage());
        writeHandlerLog('Stack trace: ' . $e->getTraceAsString());
        return false;
    }
}

    /**
     * Создает адрес (заглушка для будущего функционала)
     */
    public static function createAddress($requisites)
    {
        return ['success' => true, 'message' => 'Address creation not implemented yet'];
    }

    /**
     * Удаляет контакт по ID
     */
    public static function deleteContact($contactId)
    {
        if (empty($contactId)) {
            self::writeLog('DeleteContact: Contact ID is empty');
            return false;
        }

        try {
            $contactResult = ContactTable::getByPrimary($contactId, [
                'select' => ['ID']
            ]);

            if (!$contactResult->fetch()) {
                self::writeLog("DeleteContact: Contact with ID {$contactId} not found");
                return false;
            }

            $deleteResult = ContactTable::delete($contactId);

            if ($deleteResult->isSuccess()) {
                self::writeLog("DeleteContact: Contact {$contactId} successfully deleted");
                return true;
            } else {
                $errors = $deleteResult->getErrorMessages();
                self::writeLog("DeleteContact: Failed to delete contact {$contactId}. Errors: " . implode(', ', $errors));
                return false;
            }
        } catch (\Exception $e) {
            self::writeLog('DeleteContact: Exception - ' . $e->getMessage());
            return false;
        }
    }

    private static function findContactByPhone($phone, $companyId = null)
    {
        if (empty($phone)) {
            return null;
        }

        self::writeLog("Searching contact by phone: {$phone}, companyId: " . ($companyId ?? 'null'));

        // Используем старый API для поиска по мультиполям
        $filter = ['PHONE' => $phone];

        if ($companyId) {
            $filter['COMPANY_ID'] = $companyId;
        }

        $res = \CCrmContact::GetListEx(
            [],
            $filter,
            false,
            ['nTopCount' => 1],
            ['ID', 'COMPANY_ID']
        );

        if ($contact = $res->Fetch()) {
            self::writeLog("Found contact by phone: ID={$contact['ID']}, COMPANY_ID={$contact['COMPANY_ID']}");

            // Если указан companyId, проверяем совпадение
            if ($companyId && $contact['COMPANY_ID'] != $companyId) {
                self::writeLog("Company ID mismatch, skipping");
                return null;
            }
            // Если companyId не указан, проверяем что у контакта есть компания
            if (!$companyId && empty($contact['COMPANY_ID'])) {
                self::writeLog("Contact has no company, skipping");
                return null;
            }
            return $contact['ID'];
        }

        self::writeLog("No contact found by phone");
        return null;
    }

    /**
     * Нормализует номер телефона для сравнения
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

    private static function findContactByEmail($email, $companyId = null)
    {
        if (empty($email)) {
            return null;
        }

        self::writeLog("Searching contact by email: {$email}, companyId: " . ($companyId ?? 'null'));

        // Используем старый API для поиска по мультиполям
        $filter = ['EMAIL' => $email];

        if ($companyId) {
            $filter['COMPANY_ID'] = $companyId;
        }

        $res = \CCrmContact::GetListEx(
            [],
            $filter,
            false,
            ['nTopCount' => 1],
            ['ID', 'COMPANY_ID']
        );

        if ($contact = $res->Fetch()) {
            self::writeLog("Found contact by email: ID={$contact['ID']}, COMPANY_ID={$contact['COMPANY_ID']}");

            // Если указан companyId, проверяем совпадение
            if ($companyId && $contact['COMPANY_ID'] != $companyId) {
                self::writeLog("Company ID mismatch, skipping");
                return null;
            }
            // Если companyId не указан, проверяем что у контакта есть компания
            if (!$companyId && empty($contact['COMPANY_ID'])) {
                self::writeLog("Contact has no company, skipping");
                return null;
            }
            return $contact['ID'];
        }

        self::writeLog("No contact found by email");
        return null;
    }
}
