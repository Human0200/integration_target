<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

\Bitrix\Main\Loader::includeModule('main');

class CBPIntegrationWithTargetActivity extends CBPActivity
{
    // Учетные данные для API
    const API_EMAIL = "btx@targetco.ru";
    const API_PASSWORD = "hWP_cj1ZER";

    // Маппинг значений поля UF_CRM_1756716976955 в group
    const GROUP_MAPPING = [
        40 => 'NNK',
        45 => 'Отдел продаж',
        41 => 'EAEL',
        42 => 'Целина',
        43 => 'В работу',
        44 => 'Сотрудники',
    ];

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
            if (!empty($contactIds)) {
                foreach ($contactIds as $contactId) {
                    $contactData = $this->GetContactData($contactId);
                    if ($contactData) {
                        $contacts[] = $contactData;
                    }
                }
                $this->WriteToTrackingService("Контакты для отправки: " . print_r($contacts, true));
            } else {
                $this->WriteToTrackingService("Контакты не указаны, отправляем только данные компании");
            }

            // 4. Получаем ID компании из Target по email или ИНН (если существует)
            $targetCompanyId = $this->GetTargetCompanyIdByInn($authToken, $companyData['inn']) ?? $this->GetTargetCompanyIdByEmail($authToken, $companyData['email']);
            if ($targetCompanyId) {
                $this->WriteToTrackingService("Найдена существующая компания в Target с ID: " . $targetCompanyId);
                // Устанавливаем найденный ID для обновления
                $companyData['ext_id'] = $targetCompanyId;
            }
            $targetApiKey = $this->GetUserApiKey($companyData['responsible_id']);

            if (empty($targetApiKey)) {
                $this->WriteToTrackingService("ОШИБКА: Не удалось получить API ключ для пользователя ID=" . $companyData['responsible_id']);
                return CBPActivityExecutionStatus::Closed;
            }

            $this->WriteToTrackingService("API ключ получен для пользователя ID=" . $companyData['responsible_id']);

            // Формирование данных для отправки
            $requestData = $this->PrepareRequestData($contacts, $companyData);

            // Отправка данных в API
            $apiUrl = "https://api.targetco.ru/api/btx/users/" . ($companyData['ext_id'] ?? '0') . '?token=' . $targetApiKey;
            $this->WriteToTrackingService("Ссылка API: " . $apiUrl);
            $result = $this->SendToAPI($authToken, $requestData, $apiUrl);

            if ($result) {
                $this->WriteToTrackingService("Данные успешно отправлены в API. ID из внешней системы: " . $this->UserId);
            } else {
                $this->WriteToTrackingService("Ошибка при отправке данных в API");
            }
        } catch (Exception $e) {
            $this->WriteToTrackingService("Ошибка: " . $e->getMessage());
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Получение API ключа пользователя
     */
    private function GetUserApiKey($userId)
    {
        if (empty($userId)) {
            $this->WriteToTrackingService("ОШИБКА: ID пользователя не указан");
            return '';
        }

        try {
            $userResult = \CUser::GetByID($userId);

            if (!$userResult) {
                $this->WriteToTrackingService("ОШИБКА: Не удалось получить данные пользователя ID=" . $userId);
                return '';
            }

            $userFields = $userResult->Fetch();

            if (!$userFields) {
                $this->WriteToTrackingService("ОШИБКА: Пользователь ID=" . $userId . " не найден");
                return '';
            }

            // Логируем все UF_ поля для отладки
            // $ufFields = array_filter(array_keys($userFields), function ($key) {
            //     return strpos($key, 'UF_') === 0;
            // });
            // $this->WriteToTrackingService("Доступные UF_ поля пользователя: " . implode(', ', $ufFields));

            if (!isset($userFields['UF_APIKEY'])) {
                $this->WriteToTrackingService("ОШИБКА: Поле UF_APIKEY не существует у пользователя ID=" . $userId);
                return '';
            }

            $apiKey = trim($userFields['UF_APIKEY']);

            if (empty($apiKey)) {
                $this->WriteToTrackingService("ОШИБКА: Поле UF_APIKEY пустое у пользователя ID=" . $userId);
                return '';
            }

            $this->WriteToTrackingService("API ключ успешно получен (длина: " . strlen($apiKey) . " символов)");
            return $apiKey;
        } catch (Exception $e) {
            $this->WriteToTrackingService("ИСКЛЮЧЕНИЕ при получении API ключа: " . $e->getMessage());
            return '';
        }
    }


    /**
     * Получение ID компании из Target по ИНН
     */
    private function GetTargetCompanyIdByInn($authToken, $inn)
    {
        if (empty($inn)) {
            $this->WriteToTrackingService("ИНН компании пустой, поиск по ИНН невозможен");
            return null;
        }

        try {
            $apiUrl = "https://api.targetco.ru/api/btx/users";

            $http = new \Bitrix\Main\Web\HttpClient([
                'socketTimeout' => 30,
                'streamTimeout' => 30,
                'waitResponse' => true,
            ]);

            $http->setHeader('Content-Type', 'application/json');
            $http->setHeader('Accept', 'application/json');
            $http->setHeader('Authorization', 'Bearer ' . $authToken);

            $response = $http->get($apiUrl);
            $httpCode = $http->getStatus();

            $this->WriteToTrackingService("Поиск компании по ИНН {$inn}. HTTP код: " . $httpCode);

            if ($httpCode !== 200) {
                $this->WriteToTrackingService("Ошибка при получении списка компаний. HTTP код: " . $httpCode);
                return null;
            }

            $data = json_decode($response, true);
            $data = $data['data'];

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->WriteToTrackingService("Ошибка при декодировании JSON. Ошибка: " . json_last_error_msg());
                return null;
            }
            foreach ($data as $company) {
                if (isset($company['inn']) && $company['inn'] === $inn) {
                    
                    $this->writeToTrackingService("Декодирования компания:". json_encode($company, JSON_UNESCAPED_UNICODE));
                    
                }
            }
            foreach ($data as $company) {
                if (isset($company['inn']) && $company['inn'] === $inn) {

                    return $company['id'];
                }
            }
        } catch (Exception $e) {
            $this->WriteToTrackingService("Исключение при поиске компании по ИНН: " . $e->getMessage());
        }

        $this->WriteToTrackingService("Компания с ИНН {$inn} не найдена в Target");
        return null;
    }

    /**
     * Получение ID компании из Target по email
     */
    private function GetTargetCompanyIdByEmail($authToken, $email)
    {
        if (empty($email)) {
            $this->WriteToTrackingService("Email компании пустой, поиск по email невозможен");
            return null;
        }

        try {
            $apiUrl = "https://api.targetco.ru/api/btx/users";

            $http = new \Bitrix\Main\Web\HttpClient([
                'socketTimeout' => 30,
                'streamTimeout' => 30,
                'waitResponse' => true,
            ]);

            $http->setHeader('Content-Type', 'application/json');
            $http->setHeader('Accept', 'application/json');
            $http->setHeader('Authorization', 'Bearer ' . $authToken);

            $response = $http->get($apiUrl);
            $httpCode = $http->getStatus();

            $this->WriteToTrackingService("Поиск компании по email {$email}. HTTP код: " . $httpCode);

            if ($httpCode === 200) {
                $usersData = json_decode($response, true);

                if (isset($usersData['success']) && $usersData['success'] === true && !empty($usersData['data'])) {
                    // Ищем компанию по email
                    foreach ($usersData['data'] as $user) {
                        if (isset($user['email']) && strtolower(trim($user['email'])) === strtolower(trim($email))) {
                            $this->WriteToTrackingService("Найдена компания в Target: ID={$user['id']}, Email={$user['email']}");
                            return $user['id'];
                        }
                    }

                    $this->WriteToTrackingService("Компания с email {$email} не найдена в Target");
                } else {
                    $this->WriteToTrackingService("Не удалось получить список пользователей из Target или список пуст");
                }
            } else {
                $this->WriteToTrackingService("Ошибка при запросе списка пользователей из Target. HTTP код: " . $httpCode);
            }
        } catch (Exception $e) {
            $this->WriteToTrackingService("Исключение при поиске компании по email: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Авторизация в API
     */
    private function Authenticate()
    {
        $authUrl = "https://api.targetco.ru/api/login";

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

    private function GetCompanyData($companyId)
    {
        if (empty($companyId)) {
            return null;
        }

        try {
            \Bitrix\Main\Loader::includeModule('crm');

            // Получаем основные данные компании
            $dbResult = \CCrmCompany::GetListEx(
                [],
                ['ID' => $companyId, 'CHECK_PERMISSIONS' => 'N'],
                false,
                false,
                ['*', 'UF_*']
            );

            $company = $dbResult->Fetch();

            if (!$company) {
                $this->WriteToTrackingService("Компания с ID={$companyId} не найдена");
                return null;
            }

            // Получаем реквизиты компании
            $requisites = $this->GetCompanyRequisites($companyId);

            // Получаем значение поля UF_CRM_1756716976955 и маппим в group
            $groupFieldValue = $company['UF_CRM_1756716976955'] ?? null;
            $group = $this->MapGroupValue($groupFieldValue);

            return [
                'ext_id' => $company['UF_CRM_1760515262922'] ?? null,
                'responsible_id' => $company['ASSIGNED_BY_ID'] ?? null,
                'id' => $company['ID'] ?? null,
                'name' => $company['TITLE'] ?? '',
                'inn' => $requisites['inn'] ?? '',
                'kpp' => $requisites['kpp'] ?? '',
                'email' => $this->GetCompanyEmail($companyId),
                'phone' => $this->GetCompanyPhone($companyId),
                'address' => $requisites['address'] ?? ($company['ADDRESS'] ?? ''),
                'group' => $group,
                'comment' => preg_replace('/\[[^\]]*\]/', '', $company['COMMENTS'] ?? '')
            ];
        } catch (Exception $e) {
            $this->WriteToTrackingService("Ошибка получения компании ID={$companyId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получает реквизиты компании (ИНН, КПП, адрес и т.д.)
     */
    private function GetCompanyRequisites($companyId)
    {
        try {
            if (!\Bitrix\Main\Loader::includeModule('crm')) {
                return [];
            }

            // Получаем реквизиты компании
            $requisiteEntity = new \Bitrix\Crm\EntityRequisite();

            $dbRequisites = $requisiteEntity->getList([
                'filter' => [
                    'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                    'ENTITY_ID' => $companyId
                ],
                'order' => ['SORT' => 'ASC'],
                'limit' => 1
            ]);

            if ($requisite = $dbRequisites->fetch()) {
                $requisiteId = $requisite['ID'];
                $address = '';

                // Получаем адреса как в примере
                $addressEntity = new \Bitrix\Crm\EntityAddress();
                $dbAddress = $addressEntity->getList([
                    'filter' => [
                        'ENTITY_ID' => $requisiteId,
                        'ANCHOR_ID' => $companyId
                    ]
                ]);

                while ($addr = $dbAddress->fetch()) {
                    // TYPE_ID = 6 - юридический адрес (Registered)
                    // TYPE_ID = 1 - фактический адрес (Primary)
                    // TYPE_ID = 3 - почтовый адрес (Home)

                    if ($addr['TYPE_ID'] == 6) { // Юридический адрес
                        $addressParts = [];

                        if (!empty($addr['POSTAL_CODE'])) {
                            $addressParts[] = $addr['POSTAL_CODE'];
                        }

                        // Собираем адрес из компонентов
                        $locationParts = array_filter([
                            $addr['REGION'],
                            $addr['PROVINCE'],
                            $addr['CITY'],
                            $addr['ADDRESS_1'],
                            $addr['ADDRESS_2']
                        ]);

                        if (!empty($locationParts)) {
                            $addressParts[] = implode(', ', $locationParts);
                        }

                        $address = implode(', ', $addressParts);
                        break; // Берем юридический адрес
                    }
                }

                // Если юридический адрес не найден, берем первый попавшийся
                if (empty($address)) {
                    $dbAddress->reset();
                    while ($addr = $dbAddress->fetch()) {
                        $addressParts = [];

                        if (!empty($addr['POSTAL_CODE'])) {
                            $addressParts[] = $addr['POSTAL_CODE'];
                        }

                        $locationParts = array_filter([
                            $addr['REGION'],
                            $addr['PROVINCE'],
                            $addr['CITY'],
                            $addr['ADDRESS_1'],
                            $addr['ADDRESS_2']
                        ]);

                        if (!empty($locationParts)) {
                            $addressParts[] = implode(', ', $locationParts);
                        }

                        $address = implode(', ', $addressParts);
                        break;
                    }
                }

                $this->WriteToTrackingService("Реквизиты компании ID={$companyId} найдены:");

                return [
                    'inn' => $requisite['RQ_INN'] ?? '',
                    'kpp' => $requisite['RQ_KPP'] ?? '',
                    'ogrn' => $requisite['RQ_OGRN'] ?? '',
                    'address' => $address,
                    'bank_account' => $requisite['RQ_ACC_NUM'] ?? '',
                    'bank_name' => $requisite['RQ_BANK_NAME'] ?? '',
                    'bik' => $requisite['RQ_BIK'] ?? '',
                    'cor_account' => $requisite['RQ_COR_ACC_NUM'] ?? '',
                    'company_name' => $requisite['RQ_COMPANY_NAME'] ?? '',
                    'company_full_name' => $requisite['RQ_COMPANY_FULL'] ?? '',
                    'okpo' => $requisite['RQ_OKPO'] ?? '',
                    'okved' => $requisite['RQ_OKVED'] ?? '',
                ];
            }

            $this->WriteToTrackingService("Реквизиты для компании ID={$companyId} не найдены");
            return [];
        } catch (Exception $e) {
            $this->WriteToTrackingService("Ошибка получения реквизитов компании ID={$companyId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Маппинг значения поля списка в строковое значение group
     */
    private function MapGroupValue($fieldValue)
    {
        if (empty($fieldValue)) {
            $this->WriteToTrackingService("Предупреждение: поле UF_CRM_1756716976955 пустое");
            return '';
        }

        // Если передан массив, берем первый элемент
        if (is_array($fieldValue)) {
            $fieldValue = reset($fieldValue);
        }

        $fieldValue = (int)$fieldValue;

        if (isset(self::GROUP_MAPPING[$fieldValue])) {
            $mappedValue = self::GROUP_MAPPING[$fieldValue];
            $this->WriteToTrackingService("Маппинг group: {$fieldValue} -> {$mappedValue}");
            return $mappedValue;
        }

        $this->WriteToTrackingService("Предупреждение: значение {$fieldValue} не найдено в маппинге GROUP_MAPPING");
        return '';
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
            $contactData = array_filter([
                "ext_id" => (int)$contact['id'],
                "fio" => $contact['fio'] ?? null,
                "phone" => $contact['phone'] ?? null,
                "email" => $contact['email'] ?? null
            ], function ($value) {
                return $value !== null && $value !== '';
            });

            $contactsFormatted[] = $contactData;
        }

        $requestData = array_filter([
            "ext_id" => (int)$companyData['id'],
            "name" => $companyData['name'] ?? null,
            "inn" => $companyData['inn'] ?? null,
            "kpp" => $companyData['kpp'] ?? null,
            "email" => $companyData['email'] ?? null,
            "phone" => $companyData['phone'] ?? null,
            "address" => $companyData['address'] ?? null,
            "group" => $companyData['group'] ?? null,
            "comment" => $companyData['comment'] ?? null,
            "contacts" => $contactsFormatted,
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        $this->WriteToTrackingService("Данные для отправки: " . json_encode($requestData, JSON_UNESCAPED_UNICODE));

        return $requestData;
    }

    /**
     * Отправка данных в API
     */
    private function SendToAPI($authToken, $data, $apiUrl)
    {
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
                $responseData = json_decode($response, true);

                if (isset($responseData['data']['id'])) {
                    $this->UserId = $responseData['data']['id'];
                    $this->WriteToTrackingService("ID из API: " . $this->UserId);
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
