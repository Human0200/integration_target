<?php
namespace LeadSpace\Classes\Contacts;

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ExpressionField;

class FindContact
{

    
    public static function findOrCreateContact($properties)
    {
        $phone = $properties['PHONE'] ?? null;
        $email = $properties['EMAIL'] ?? null;
        $name = $properties['NAME'] ?? $properties['FIO'] ?? 'Клиент из интернет-магазина';
        $lastName = $properties['LAST_NAME'] ?? '';

        $companyId = $properties['COMPANY_ID'] ?? null;

        if (!$phone && !$email) {
            return null;
        }

        // Ищем контакт по телефону или email только среди контактов с заполненным COMPANY_ID
        $contactId = null;

        if ($phone) {
            $contactId = self::findContactByPhone($phone, $companyId);
        }

        if (!$contactId && $email) {
            $contactId = self::findContactByEmail($email, $companyId);
        }

        // Если контакт не найден - создаем новый
        if (!$contactId) {
            $contactFields = [
                'NAME' => $name,
                'LAST_NAME' => $lastName,
            ];

            // Добавляем COMPANY_ID если он есть
            if ($companyId) {
                $contactFields['COMPANY_ID'] = $companyId;
            }

            if ($phone) {
                $contactFields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
            }

            if ($email) {
                $contactFields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
            }

            $createResult = self::createContact($contactFields);
            
            if ($createResult->isSuccess()) {
                $contactId = $createResult->getId();
            }
        } else {
            // Если контакт найден, обновляем его данные
            self::updateContact($contactId, $name, $lastName, $phone, $email);
        }

        return $contactId;
    }
    
    /**
     * Создает новый контакт через D7 ORM
     */
    private static function createContact($fields)
    {
        try {
            return ContactTable::add($fields);
        } catch (\Exception $e) {
            error_log('Error creating contact: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Получает данные контакта по ID
     */
    private static function getContact($contactId)
    {
        try {
            return ContactTable::getByPrimary($contactId, [
                'select' => ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'COMPANY_ID']
            ]);
        } catch (\Exception $e) {
            error_log('Error getting contact: ' . $e->getMessage());
            return null;
        }
    }
    
    private static function updateContact($contactId, $name, $lastName, $phone, $email)
    {
        $updateFields = [
            'NAME' => $name,
            'LAST_NAME' => $lastName,
        ];
        
        // Получаем текущие данные контакта
        $currentContactResult = self::getContact($contactId);
        
        if ($currentContactResult && $currentContactResult->isSuccess()) {
            $currentContact = $currentContactResult->fetchAll()[0];
            
            // Проверяем и добавляем телефон, если его еще нет
            if ($phone && !self::contactHasPhone($currentContact, $phone)) {
                $phones = $currentContact['PHONE'] ?? [];
                $phones[] = ['VALUE' => $phone, 'VALUE_TYPE' => 'WORK'];
                $updateFields['PHONE'] = $phones;
            }

            // Проверяем и добавляем email, если его еще нет
            if ($email && !self::contactHasEmail($currentContact, $email)) {
                $emails = $currentContact['EMAIL'] ?? [];
                $emails[] = ['VALUE' => $email, 'VALUE_TYPE' => 'WORK'];
                $updateFields['EMAIL'] = $emails;
            }
        }
        
        // Обновляем контакт
        try {
            ContactTable::update($contactId, $updateFields);
        } catch (\Exception $e) {
            error_log('Error updating contact: ' . $e->getMessage());
        }
    }
    
    private static function contactHasPhone($contact, $phone)
    {
        if (empty($contact['PHONE'])) {
            return false;
        }
        
        $normalizedSearchPhone = self::normalizePhone($phone);
        
        foreach ($contact['PHONE'] as $phoneData) {
            $normalizedContactPhone = self::normalizePhone($phoneData['VALUE']);
            if ($normalizedContactPhone === $normalizedSearchPhone) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function contactHasEmail($contact, $email)
    {
        if (empty($contact['EMAIL'])) {
            return false;
        }
        
        $normalizedSearchEmail = strtolower(trim($email));
        
        foreach ($contact['EMAIL'] as $emailData) {
            $normalizedContactEmail = strtolower(trim($emailData['VALUE']));
            if ($normalizedContactEmail === $normalizedSearchEmail) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Обновляет поля контакта
     */
    public static function updateContactFields($contactId, $fields)
    {
        try {
            $updateResult = ContactTable::update($contactId, $fields);
            return $updateResult->isSuccess() ? $contactId : false;
        } catch (\Exception $e) {
            error_log('Error updating contact fields: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Создает адрес (заглушка для будущего функционала)
     */
    public static function createAddress($requisites)
    {
        // TODO: Реализовать создание адреса
        return ['success' => true, 'message' => 'Address creation not implemented yet'];
    }
    
    /**
     * Удаляет контакт по ID
     * @param int $contactId ID контакта для удаления
     * @return bool true если удаление успешно, false в противном случае
     */
    public static function deleteContact($contactId)
    {
        if (empty($contactId)) {
            error_log('DeleteContact: Contact ID is empty');
            return false;
        }
        
        try {
            // Проверяем существование контакта
            $contactResult = ContactTable::getByPrimary($contactId, [
                'select' => ['ID']
            ]);
            
            if (!$contactResult->fetch()) {
                error_log("DeleteContact: Contact with ID {$contactId} not found");
                return false;
            }
            
            // Удаляем контакт
            $deleteResult = ContactTable::delete($contactId);
            
            if ($deleteResult->isSuccess()) {
                error_log("DeleteContact: Contact {$contactId} successfully deleted");
                return true;
            } else {
                $errors = $deleteResult->getErrorMessages();
                error_log("DeleteContact: Failed to delete contact {$contactId}. Errors: " . implode(', ', $errors));
                return false;
            }
            
        } catch (\Exception $e) {
            error_log('DeleteContact: Exception - ' . $e->getMessage());
            return false;
        }
    }

    private static function findContactByPhone($phone, $companyId = null)
    {
        if (empty($phone)) {
            return null;
        }

        // Генерируем все возможные варианты формата телефона
        $phoneVariations = self::getPhoneVariations($phone);
        
        // Ищем по каждому варианту
        foreach ($phoneVariations as $phoneVariation) {
            try {
                $query = ContactTable::query()
                    ->setSelect(['ID', 'COMPANY_ID'])
                    ->where('PHONE', $phoneVariation);
                
                // Добавляем фильтр по COMPANY_ID если он задан
                if ($companyId) {
                    $query->where('COMPANY_ID', $companyId);
                } else {
                    // Ищем только контакты с заполненным COMPANY_ID
                    $query->where('COMPANY_ID', '>', 0);
                }
                
                $result = $query->exec();
                
                if ($contact = $result->fetch()) {
                    return $contact['ID'];
                }
            } catch (\Exception $e) {
                error_log('Error finding contact by phone: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Генерирует все возможные варианты форматирования телефона
     */
    private static function getPhoneVariations($phone)
    {
        $normalizedPhone = self::normalizePhone($phone);
        
        if (empty($normalizedPhone) || strlen($normalizedPhone) !== 11) {
            return [$phone]; // Возвращаем исходный, если не удалось нормализовать
        }
        
        $variations = [];
        
        // +7 XXX XXX-XX-XX
        $variations[] = '+' . $normalizedPhone[0] . ' ' . 
                       substr($normalizedPhone, 1, 3) . ' ' . 
                       substr($normalizedPhone, 4, 3) . '-' . 
                       substr($normalizedPhone, 7, 2) . '-' . 
                       substr($normalizedPhone, 9, 2);
        
        // 7 XXX XXX-XX-XX
        $variations[] = $normalizedPhone[0] . ' ' . 
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
        
        // 7(XXX)XXX-XX-XX
        $variations[] = $normalizedPhone[0] . '(' . 
                       substr($normalizedPhone, 1, 3) . ')' . 
                       substr($normalizedPhone, 4, 3) . '-' . 
                       substr($normalizedPhone, 7, 2) . '-' . 
                       substr($normalizedPhone, 9, 2);
        
        // Исходный формат тоже добавляем
        $variations[] = $phone;
        
        // Убираем дубликаты
        return array_unique($variations);
    }

    /**
     * Нормализует номер телефона для сравнения
     */
    private static function normalizePhone($phone)
    {
        if (empty($phone)) {
            return '';
        }
        
        // Убираем все кроме цифр
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        
        // Обрабатываем российские номера
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
        
        try {
            $query = ContactTable::query()
                ->setSelect(['ID', 'COMPANY_ID'])
                ->where('EMAIL', $email);
            
            // Добавляем фильтр по COMPANY_ID если он задан
            if ($companyId) {
                $query->where('COMPANY_ID', $companyId);
            } else {
                // Ищем только контакты с заполненным COMPANY_ID
                $query->where('COMPANY_ID', '>', 0);
            }
            
            $result = $query->exec();
            
            if ($contact = $result->fetch()) {
                return $contact['ID'];
            }
        } catch (\Exception $e) {
            error_log('Error finding contact by email: ' . $e->getMessage());
        }
        
        return null;
    }
}