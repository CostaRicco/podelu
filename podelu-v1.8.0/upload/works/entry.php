<?php
/** По делу v1.8.0. Маршрут timeform; видимый app. */
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php';
$APPLICATION->SetTitle('По делу | by Markitan Concept');
$APPLICATION->IncludeComponent('markitan:podelu','app',['VIEW'=>'timeform'],false,['HIDE_ICONS'=>'Y']);
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php';
