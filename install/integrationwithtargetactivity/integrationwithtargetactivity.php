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
        // Заглушка: просто пишем в трекинг БП входные значения и ничего не делаем
        $contactId = trim((string)$this->ContactId);
        $companyId = trim((string)$this->CompanyId);

        $this->WriteToTrackingService(sprintf(
            "Stub executed. ContactId=%s, CompanyId=%s",
            $contactId !== "" ? $contactId : "(empty)",
            $companyId !== "" ? $companyId : "(empty)"
        ));

        return CBPActivityExecutionStatus::Closed;
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
