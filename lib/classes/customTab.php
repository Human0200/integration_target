<?php
namespace LeadSpace\Tabs;

use Bitrix\Main\Loader;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

Loader::includeModule('crm');

class CustomTab
{
    protected \CCrmPerms $userPermissions;

    public function __construct()
    {
        $this->userPermissions = \CCrmPerms::GetCurrentUserPermissions();
    }

    public static function onEntityDetailsTabsInitialized(\Bitrix\Main\Event $event)
    {
       // file_put_contents(__DIR__ . '/log.txt', print_r($event->getParameters(), true));
        $entityID = $event->getParameter('entityID');
        $entityTypeID = $event->getParameter('entityTypeID');
        $tabs = $event->getParameter('tabs');

        // Проверяем, что у нас есть ID сущности
        if (empty($entityID)) {
            return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, [
                'tabs' => $tabs,
            ]);
        }

        $manager = new self();
        
        // Обрабатываем сделки (DEAL)
        if ($entityTypeID == \CCrmOwnerType::Deal) {
            $tabs = $manager->getDealTabs($tabs, $entityID);
        }
        // Обрабатываем контакты (CONTACT)
        elseif ($entityTypeID == \CCrmOwnerType::Contact) {
            $tabs = $manager->getContactTabs($tabs, $entityID);
        }
        // Обрабатываем компании (COMPANY)
        elseif ($entityTypeID == \CCrmOwnerType::Company) {
            $tabs = $manager->getCompanyTabs($tabs, $entityID);
        }

        return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, [
            'tabs' => $tabs,
        ]);
    }

    private function getDealTabs(array $tabs, int $dealId): array
    {
        $canUpdateDeal = \CCrmDeal::CheckUpdatePermission($dealId, $this->userPermissions);

        if ($canUpdateDeal) {
            $tabs[] = [
                'id' => 'custom_tab_deal',
                'name' => 'Target Cooperation',
                'enabled' => !empty($dealId),
                'loader' => [
                    'serviceUrl' => '/local/ajax/custom_tab_content.php?&site=' . SITE_ID . '&' . bitrix_sessid_get(),
                    'componentData' => [
                        'template' => '',
                        'params' => [
                            'ENTITY_TYPE_ID' => \CCrmOwnerType::Deal,
                            'ENTITY_ID' => $dealId
                        ]
                    ]
                ]
            ];
        }

        return $tabs;
    }

    private function getContactTabs(array $tabs, int $contactId): array
    {
        $canUpdateContact = \CCrmContact::CheckUpdatePermission($contactId, $this->userPermissions);

        if ($canUpdateContact) {
            $tabs[] = [
                'id' => 'custom_tab_contact',
                'name' => 'Target Cooperation',
                'enabled' => !empty($contactId),
                'loader' => [
                    'serviceUrl' => '/local/ajax/custom_tab_content.php?&site=' . SITE_ID . '&' . bitrix_sessid_get(),
                    'componentData' => [
                        'template' => '',
                        'params' => [
                            'ENTITY_TYPE_ID' => \CCrmOwnerType::Contact,
                            'ENTITY_ID' => $contactId
                        ]
                    ]
                ]
            ];
        }

        return $tabs;
    }

    private function getCompanyTabs(array $tabs, int $companyId): array
    {
        $canUpdateCompany = \CCrmCompany::CheckUpdatePermission($companyId, $this->userPermissions);

        if ($canUpdateCompany) {
            $tabs[] = [
                'id' => 'custom_tab_company',
                'name' => 'Target Cooperation',
                'enabled' => !empty($companyId),
                'loader' => [
                    'serviceUrl' => '/local/ajax/custom_tab_content.php?&site=' . SITE_ID . '&' . bitrix_sessid_get(),
                    'componentData' => [
                        'template' => '',
                        'params' => [
                            'ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                            'ENTITY_ID' => $companyId
                        ]
                    ]
                ]
            ];
        }

        return $tabs;
    }
}