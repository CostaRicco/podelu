<?php
/** По делу v1.4.0. Повторяющиеся пакеты, исключения и изменения условий по месяцам.
 * Отличия от v1.3.0: повторение лимита без переноса часов; история условий и остановка.
 * Существующие клиенты и пакеты сохраняются. Задачи и списание времени пока не включены. */
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php';
$APPLICATION->SetTitle('По делу | by Markitan Concept');
$APPLICATION->IncludeComponent('markitan:podelu','',[],false,['HIDE_ICONS'=>'Y']);
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php';
