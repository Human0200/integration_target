<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

\Bitrix\Main\Loader::includeModule('main');

class CBPIntegrationWithTargetActivity extends CBPActivity
{
    // Учетные данные для API
    const API_EMAIL = "btx@targetco.ru";
    const API_PASSWORD = "hWP_cj1ZER";
    
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "Title" => "",
            "ContactId" => "",
            "CompanyId" => "",
            "UserId" => "",
        );
    }

    public function Execute()
    {
        $this->UserId = "";
        // Обработка массива контактов
        $contactIds = $this->ContactId;
        if (!is_array($contactIds)) {
            $contactIds = !empty($contactIds) ? [$contactIds] : [];
        }
        
        $companyId = trim((string)$this->CompanyId);

        try {
            // 1. Авторизация и получение токена
            $authToken = $this->Authenticate();
            
            if (!$authToken) {
                $this->WriteToTrackingService("Ошибка авторизации");
                return CBPActivityExecutionStatus::Closed;
            }

            // 2. Получение данных компании из Битрикс24
            $companyData = $this->GetCompanyData($companyId);
            
            if (!$companyData) {
                $this->WriteToTrackingService("Не удалось получить данные компании");
                return CBPActivityExecutionStatus::Closed;
            }

            // 3. Получение данных всех контактов
            $contacts = [];
            foreach ($contactIds as $contactId) {
                $contactData = $this->GetContactData($contactId);
                if ($contactData) {
                    $contacts[] = $contactData;
                }
            }
            $this->WriteToTrackingService("Контакты для отправки: " . print_r($contacts, true));
            if (empty($contacts)) {
                $this->WriteToTrackingService("Не удалось получить данные контактов");
                return CBPActivityExecutionStatus::Closed;
            }

            // 4. Формирование данных для отправки
            $requestData = $this->PrepareRequestData($contacts, $companyData);

            // 5. Отправка данных в API
            $result = $this->SendUserData($authToken, $requestData, $companyId);

            if ($result) {
                $this->WriteToTrackingService("Данные успешно отправлены в API");
            } else {
                $this->WriteToTrackingService("Ошибка при отправке данных в API");
            }

        } catch (Exception $e) {
            $this->WriteToTrackingService("Ошибка: " . $e->getMessage());
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Авторизация в API
     */
    private function Authenticate()
    {
        $authUrl = "https://test-api.targetco.ru/api/login";
        
        $authData = [
            "email" => self::API_EMAIL,
            "password" => self::API_PASSWORD
        ];

        try {
            $http = new \Bitrix\Main\Web\HttpClient([
                'socketTimeout' => 30,
                'streamTimeout' => 30,
                'waitResponse' => true,
            ]);
            
            $http->setHeader('Content-Type', 'application/json');
            $http->setHeader('Accept', 'application/json');
            
            $response = $http->post($authUrl, json_encode($authData));
            $httpCode = $http->getStatus();

            if ($httpCode !== 200) {
                $this->WriteToTrackingService("Ошибка авторизации. HTTP код: " . $httpCode . ". Ответ: " . $response);
                return null;
            }

            $authResult = json_decode($response, true);
            
            if (isset($authResult['token'])) {
                return $authResult['token'];
            }

            $this->WriteToTrackingService("Токен не найден в ответе авторизации");
            return null;
            
        } catch (Exception $e) {
            $this->WriteToTrackingService("Исключение при авторизации: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получение данных контакта из Битрикс24
     */
    private function GetContactData($contactId)
    {
        if (empty($contactId)) {
            return null;
        }

        try {
            \Bitrix\Main\Loader::includeModule('crm');
            
            $contact = \CCrmContact::GetByID($contactId, false);
            
            if (!$contact) {
                return null;
            }
            
            $fio = trim(($contact['NAME'] ?? '') . ' ' . ($contact['LAST_NAME'] ?? ''));
            if (empty($fio)) {
                $fio = $contact['HONORIFIC'] ?? '';
            }
            
            return [
                'id' => $contactId,
                'fio' => $fio,
                'phone' => $this->GetContactPhone($contactId),
                'email' => $this->GetContactEmail($contactId)
            ];
            
        } catch (Exception $e) {
            $this->WriteToTrackingService("Ошибка получения контакта ID={$contactId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получение телефона контакта
     */
    private function GetContactPhone($contactId)
    {
        $phones = \CCrmFieldMulti::GetEntityFields('CONTACT', $contactId, 'PHONE', false);
        if (!empty($phones)) {
            return reset($phones)['VALUE'] ?? '';
        }
        return '';
    }

    /**
     * Получение email контакта
     */
    private function GetContactEmail($contactId)
    {
        $emails = \CCrmFieldMulti::GetEntityFields('CONTACT', $contactId, 'EMAIL', false);
        if (!empty($emails)) {
            return reset($emails)['VALUE'] ?? '';
        }
        return '';
    }

    /**
     * Получение данных компании из Битрикс24
     */
    private function GetCompanyData($companyId)
    {
        if (empty($companyId)) {
            return null;
        }

        try {
            \Bitrix\Main\Loader::includeModule('crm');
            
            $company = \CCrmCompany::GetByID($companyId, false);
            
            if (!$company) {
                return null;
            }
            
            return [
                'name' => $company['TITLE'] ?? '',
                'inn' => $company['UF_CRM_INN'] ?? '',
                'kpp' => $company['UF_CRM_KPP'] ?? '',
                'email' => $this->GetCompanyEmail($companyId),
                'phone' => $this->GetCompanyPhone($companyId),
                'address' => $company['ADDRESS'] ?? ''
            ];
            
        } catch (Exception $e) {
            $this->WriteToTrackingService("Ошибка получения компании ID={$companyId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получение телефона компании
     */
    private function GetCompanyPhone($companyId)
    {
        $phones = \CCrmFieldMulti::GetEntityFields('COMPANY', $companyId, 'PHONE', false);
        if (!empty($phones)) {
            return reset($phones)['VALUE'] ?? '';
        }
        return '';
    }

    /**
     * Получение email компании
     */
    private function GetCompanyEmail($companyId)
    {
        $emails = \CCrmFieldMulti::GetEntityFields('COMPANY', $companyId, 'EMAIL', false);
        if (!empty($emails)) {
            return reset($emails)['VALUE'] ?? '';
        }
        return '';
    }

    /**
     * Подготовка данных для отправки в API
     */
    private function PrepareRequestData($contacts, $companyData)
    {
        $contactsFormatted = [];
        foreach ($contacts as $contact) {
            $contactsFormatted[] = [
                "id" => (int)$contact['id'],
                "fio" => $contact['fio'] ?? '',
                "phone" => $contact['phone'] ?? '',
                "email" => $contact['email'] ?? ''
            ];
        }

        return [
            "name" => $companyData['name'] ?? '',
            "inn" => $companyData['inn'] ?? '',
            "kpp" => $companyData['kpp'] ?? '',
            "email" => $companyData['email'] ?? '',
            "phone" => $companyData['phone'] ?? '',
            "address" => $companyData['address'] ?? '',
            "contacts" => $contactsFormatted
        ];
    }

   /**
 * Отправка данных пользователя в API
 */
private function SendUserData($authToken, $data, $companyId)
{
    $apiUrl = "https://test-api.targetco.ru/api/btx/users/" . $companyId;

    try {
        $http = new \Bitrix\Main\Web\HttpClient([
            'socketTimeout' => 30,
            'streamTimeout' => 30,
            'waitResponse' => true,
        ]);
        
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        $http->setHeader('Content-Type', 'application/json');
        $http->setHeader('Accept', 'application/json');
        $http->setHeader('Authorization', 'Bearer ' . $authToken);
        
        $response = $http->post($apiUrl, $jsonData);
        $httpCode = $http->getStatus();

        $this->WriteToTrackingService("Ответ API (код {$httpCode}): " . $response);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            // Декодируем JSON ответ
            $responseData = json_decode($response, true);
            
            if (isset($responseData['data']['id'])) {
                $this->UserId = $responseData['data']['id'];
                $this->WriteToTrackingService("ID пользователя из API: " . $this->UserId);
            }
            
            return true;
        } else {
            return false;
        }
        
    } catch (Exception $e) {
        $this->WriteToTrackingService("Исключение при отправке: " . $e->getMessage());
        return false;
    }
}

    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $errors = array();

        if (!isset($arTestProperties["CompanyId"]) || $arTestProperties["CompanyId"] === "") {
            $errors[] = array("code" => "Empty", "parameter" => "CompanyId", "message" => "CompanyId is required");
        }

        if (!isset($arTestProperties["ContactId"]) || empty($arTestProperties["ContactId"])) {
            $errors[] = array("code" => "Empty", "parameter" => "ContactId", "message" => "ContactId is required");
        }

        return array_merge($errors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues, $formName = "")
    {
        $runtime = CBPRuntime::GetRuntime();

        $arMap = [
            'ContactId' => 'contactid',
            "CompanyId" => "companyid",
        ];

        if (!is_array($arCurrentValues)) {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            if (is_array($arCurrentActivity["Properties"])) {
                foreach ($arMap as $propertyKey => $fieldName) {
                    $arCurrentValues[$fieldName] = $arCurrentActivity["Properties"][$propertyKey] ?? "";
                }
            }
        }

        return $runtime->ExecuteResourceFile(__FILE__, "properties_dialog.php", ["arCurrentValues" => $arCurrentValues]);
    }

    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        $arErrors = [];

        $arMap = [
            'ContactId' => 'contactid',
            "CompanyId" => "companyid",
        ];

        $arProperties = [];
        foreach ($arMap as $key => $value) {
            $arProperties[$key] = $arCurrentValues[$value] ?? "";
        }

        $arErrors = self::ValidateProperties($arProperties);
        if (!empty($arErrors)) {
            return false;
        }

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;

        return true;
    }
}