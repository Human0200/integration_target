<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('leadspace.integrationtarget', [
    'LeadSpace\Classes\Settings24\GlobalSettings' => 'lib/classes/settings.php',
    'LeadSpace\Classes\Contacts\FindContact' => 'lib/classes/FindContact.php',

]);
?>