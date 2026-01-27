<?php

use Bitrix\Main\Localization\Loc;

$module_id = "travelsoft.tsoperator.amointegration";

if (!$USER->isAdmin())
    return;

Loc::loadMessages(__FILE__);

global $APPLICATION;

$main_options = array(
    'CLIENT_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_CLIENT_ID'), 'type' => 'text'),
    'CLIENT_SECRET' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_CLIENT_SECRET'), 'type' => 'text'),
    'LONG_ACCESS_TOKEN' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_LONG_ACCESS_TOKEN'), 'type' => 'text'),
    'REDIRECT_URL' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_REDIRECT_URL'), 'type' => 'text'),
    'BASE_DOMAIN' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_BASE_DOMAIN'), 'type' => 'text'),
    'TOUR_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_TOUR_FIELD_ID'), 'type' => 'text'),
    'DESC_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_DESC_FIELD_ID'), 'type' => 'text'),
    'TOUR_LINK_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_TOUR_LINK_FIELD_ID'), 'type' => 'text'),
    'DATE_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_DATE_FIELD_ID'), 'type' => 'text'),
    'ADULTS_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_ADULTS_FIELD_ID'), 'type' => 'text'),
    'CHILDREN_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_CHILDREN_FIELD_ID'), 'type' => 'text'),
    'PHONE_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_PHONE_FIELD_ID'), 'type' => 'text'),
    'CID_FIELD_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_CID_FIELD_ID'), 'type' => 'text'),
    'STATUS_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_STATUS_ID'), 'type' => 'text'),
    'PIPELINE_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_PIPELINE_ID'), 'type' => 'text'),
    'CALLBACK_FORM_IBLOCK_ID' => array('desc' => Loc::getMessage('TRAVELSOFT_AMO_CALLBACK_FORM_IBLOCK_ID'), 'type' => 'text'),
);
$tabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => "Settings",
        "ICON" => "",
        "TITLE" => "Settings"
    ),
);

$o_tab = new CAdminTabControl("TravelsoftTabControl", $tabs);
if ($REQUEST_METHOD == "POST" && strlen($save . $reset) > 0 && check_bitrix_sessid()) {

    if (strlen($reset) > 0) {
        foreach ($main_options as $name => $desc) {
            \Bitrix\Main\Config\Option::delete($module_id, array('name' => $name));
        }
    } else {
        foreach ($main_options as $name => $desc) {

            if (isset($_REQUEST[$name])) {
                \Bitrix\Main\Config\Option::set($module_id, $name, $_REQUEST[$name]);
            }
        }
    }

    LocalRedirect($APPLICATION->GetCurPage() . "?mid=" . urlencode($module_id) . "&lang=" . urlencode(LANGUAGE_ID) . "&" . $o_tab->ActiveTabParam());
}
$o_tab->Begin();
?>

<form method="post" action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($module_id) ?>&amp;lang=<? echo LANGUAGE_ID ?>">
    <?
    $o_tab->BeginNextTab();
    foreach ($main_options as $name => $desc):
        $cur_opt_val = htmlspecialcharsbx(Bitrix\Main\Config\Option::get($module_id, $name));
        $name = htmlspecialcharsbx($name);
        ?>
        <tr>
            <td width="40%">
                <label for="<? echo $name ?>"><? echo $desc['desc'] ?>:</label>
            </td>
            <td width="60%">

                <input type="text" id="<? echo $name ?>" value="<?= $cur_opt_val ?>" name="<? echo $name ?>">
            </td>
        </tr>

    <? endforeach ?>
    <?
    $active = Bitrix\Main\Config\Option::get($module_id, 'CLIENT_ID') && Bitrix\Main\Config\Option::get($module_id, 'CLIENT_SECRET') &&
            Bitrix\Main\Config\Option::get($module_id, 'REDIRECT_URL');
    $_SESSION['bxamostate'] = bin2hex(random_bytes(16));
    ?>
    <tr>
        <td width="40%"></td>
        <td width="60%">

            <input <? if (!$active): ?>disabled<? endif ?> type="button" class="adm-btn-save" onclick="jsUtils.OpenWindow('https://www.amocrm.ru/oauth?client_id=<?= Bitrix\Main\Config\Option::get($module_id, 'CLIENT_ID') ?>&state=<?= $_SESSION['bxamostate'] ?>&mode=post_message', 700, 700);" value="<?= Loc::getMessage('TRAVELSOFT_AMO_GET_TOCKEN_BTN') ?>">
            <?
            if (!$active) {
                echo BeginNote();
                echo Loc::getMessage('TRAVELSOFT_AMO_NOTIFY');
                echo EndNote();
            }
            ?>
        </td>
    </tr>
    <? $o_tab->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage("TRAVELSOFT_AMO_SAVE_BTN_NAME") ?>" title="<?= Loc::getMessage("TRAVELSOFT_AMO_SAVE_BTN_NAME") ?>" class="adm-btn-save">
    <input type="submit" name="reset" title="<?= Loc::getMessage("TRAVELSOFT_AMO_RESET_BTN_NAME") ?>" OnClick="return confirm('<? echo AddSlashes(Loc::getMessage("TRAVELSOFT_AMO_RESTORE_WARNING")) ?>')" value="<?= Loc::getMessage("TRAVELSOFT_AMO_RESET_BTN_NAME") ?>">
    <?= bitrix_sessid_post(); ?>
    <? $o_tab->End(); ?>
</form>