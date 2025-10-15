<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arActivityDescription = array(
    "NAME" => GetMessage("B24_STUB_NAME"),
    "DESCRIPTION" => GetMessage("B24_STUB_DESC"),
    "TYPE" => "activity",
    "CLASS" => "IntegrationWithTargetActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => array("ID" => "other"),
    "PROPERTIES" => array(
        "ContactId" => array(
            "Name" => GetMessage("B24_STUB_CONTACT_ID"),
            "Type" => "int",
            "Required" => false,
            "Default" => ""
        ),
        "CompanyId" => array(
            "Name" => GetMessage("B24_STUB_COMPANY_ID"),
            "Type" => "int",
            "Required" => false,
            "Default" => ""
        ),
    ),
    "RETURN" => array(
        "UserId" => array(
            "NAME" => "ID пользователя",
            "TYPE" => "string",
        ),
    )
);
?>