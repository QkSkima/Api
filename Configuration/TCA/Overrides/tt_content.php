<?php

defined('TYPO3') || die();

$constructPlugins = function ($index) {
    $listType = 'qkskimaapi_pi' . (string) $index;

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
        [
            'LLL:EXT:qkskima_api/Resources/Private/Language/locallang_db.xlf:plugin.renderer.'.(string)$index,
            $listType,               // list_type value
            'EXT:qkskima_api/Resources/Public/Icons/plugin.svg'
        ],
        'list_type',
        'qkskima_api'
    );

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$listType]
        = 'pi_flexform';

    $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$listType]
        = 'layout,select_key,pages,recursive';

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        $listType,
        'FILE:EXT:qkskima_api/Configuration/FlexForms/Plugin.xml'
    );

};

call_user_func($constructPlugins, 1);
call_user_func($constructPlugins, 2);