<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class CBPIntegrationWithTargetActivity extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "Title" => "",
            "ContactId" => "",
            "CompanyId" => "",
        );

    }

public function Execute()
{
    $contactId = trim((string)$this->ContactId);
    $companyId = trim((string)$this->CompanyId);

    $this->WriteToTrackingService(sprintf(
        "Stub executed. ContactId=%s, CompanyId=%s",
        $contactId !== "" ? $contactId : "(empty)",
        $companyId !== "" ? $companyId : "(empty)"
    ));

    try {
        // 1. Авторизация и получение токена
        $authToken = $this->Authenticate();
        if (!$authToken) {
            $this->WriteToTrackingService("Ошибка авторизации");
            return CBPActivityExecutionStatus::Closed;
        }

        // 2. Получение данных контакта и компании из Битрикс24
        $contactData = $this->GetContactData($contactId);
        $companyData = $this->GetCompanyData($companyId);

        if (!$contactData || !$companyData) {
            $this->WriteToTrackingService("Не удалось получить данные из Битрикс24");
            return CBPActivityExecutionStatus::Closed;
        }

        // 3. Формирование данных для отправки
        $requestData = $this->PrepareRequestData($contactData, $companyData);

        // 4. Отправка данных в API
        $result = $this->SendUserData($authToken, $requestData);

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
        "email" => "user@example.com",
        "password" => "password123"    
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $authUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($authData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        $this->WriteToTrackingService("Ошибка авторизации. HTTP код: " . $httpCode);
        return null;
    }

    $authResult = json_decode($response, true);
    
    if (isset($authResult['token'])) {
        $this->WriteToTrackingService("Авторизация успешна");
        return $authResult['token'];
    }

    $this->WriteToTrackingService("Токен не получен в ответе");
    return null;
}

/**
 * Получение данных контакта из Битрикс24
 */
private function GetContactData($contactId)
{
    if (empty($contactId)) {
        return null;
    }

    // TODO: Реализовать получение данных контакта из Битрикс24
    // Используя CCrmContact

    return [
        'id' => $contactId,
        'fio' => 'Пётр Иванов',
        'phone' => '+7 (999) 111-22-33',
        'email' => 'p.ivanov@example.com'
    ];
}

/**
 * Получение данных компании из Битрикс24
 */
private function GetCompanyData($companyId)
{
    if (empty($companyId)) {
        return null;
    }

    // TODO: Реализовать получение данных компании из Битрикс24
    // Используя CCrmCompany

    return [
        'name' => 'ООО Рога и Копыта',
        'inn' => '1234567890',
        'kpp' => '123456789',
        'email' => 'info@example.com',
        'phone' => '+7 (495) 123-45-67',
        'address' => 'г. Москва, ул. Примерная, д. 1'
    ];
}

/**
 * Подготовка данных для отправки в API
 */
private function PrepareRequestData($contactData, $companyData)
{
    return [
        "name" => $companyData['name'] ?? '',
        "inn" => $companyData['inn'] ?? '',
        "kpp" => $companyData['kpp'] ?? '',
        "email" => $companyData['email'] ?? '',
        "phone" => $companyData['phone'] ?? '',
        "address" => $companyData['address'] ?? '',
        "contacts" => [
            [
                "id" => (int)$contactData['id'],
                "fio" => $contactData['fio'] ?? '',
                "phone" => $contactData['phone'] ?? '',
                "email" => $contactData['email'] ?? ''
            ]
        ]
    ];
}

/**
 * Отправка данных пользователя в API
 */
private function SendUserData($authToken, $data)
{
    // TODO: Определить ID пользователя - возможно из настроек или данных компании/контакта
    $userId = $data['contacts'][0]['id'] ?? 0;
    
    if (!$userId) {
        $this->WriteToTrackingService("ID пользователя не определен");
        return false;
    }

    $apiUrl = "https://test-api.targetco.ru/api/btx/users/" . $userId;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT", // или POST в зависимости от API
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $authToken
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $this->WriteToTrackingService("HTTP код ответа: " . $httpCode);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $this->WriteToTrackingService("Успешный ответ API: " . $response);
        return true;
    } else {
        $this->WriteToTrackingService("Ошибка API: " . $response);
        return false;
    }
}

    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $errors = array();

        foreach (array("ContactId", "CompanyId") as $key) {
            if (!isset($arTestProperties[$key]) || $arTestProperties[$key] === "") {
                $errors[] = array("code" => "Empty", "parameter" => $key, "message" => $key . " is required");
            }
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

        // Если значения ещё не установлены, извлекаем из текущих свойств активности
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
