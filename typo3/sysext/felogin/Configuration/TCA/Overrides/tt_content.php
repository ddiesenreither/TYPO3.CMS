<?php
defined('TYPO3_MODE') or die();

(static function (): void {
    $feloginExtbase = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class)
        ->isFeatureEnabled('felogin.extbase');

    if ($feloginExtbase) {
        // Extbase plugin has a different CType
        $contentTypeName = 'felogin_login';
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            'Felogin',
            'Login',
            'Login Form'
        );
    } else {
        $contentTypeName = 'login';
    }
    // Add the FlexForm
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
        '*',
        'FILE:EXT:felogin/Configuration/FlexForms/Login.xml',
        $contentTypeName
    );
    $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$contentTypeName] = 'mimetypes-x-content-login';

    // check if there is already a forms tab and add the item after that, otherwise
    // add the tab item as well
    $additionalCTypeItem = [
        'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:CType.I.10',
        $contentTypeName,
        'content-elements-login'
    ];

    $existingCTypeItems = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'];
    $groupFound = false;
    $groupPosition = false;
    foreach ($existingCTypeItems as $position => $item) {
        if ($item[0] === 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:CType.div.forms') {
            $groupFound = true;
            $groupPosition = $position;
            break;
        }
    }

    if ($groupFound && $groupPosition) {
        // add the new CType item below CType
        array_splice(
            $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'],
            $groupPosition,
            0,
            [0 => $additionalCTypeItem]
        );
    } else {
        // nothing found, add two items (group + new CType) at the bottom of the list
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
            'tt_content',
            'CType',
            ['LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:CType.div.forms', '--div--']
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
            'tt_content',
            'CType',
            $additionalCTypeItem
        );
    }

    $GLOBALS['TCA']['tt_content']['types'][$contentTypeName]['showitem'] = '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
            pi_flexform,
        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
            categories,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
            rowDescription,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
    ';
})();
