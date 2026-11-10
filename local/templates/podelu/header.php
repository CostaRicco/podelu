<?php
/** По делу v1.9.1. Скруглённые компактные секторы. Требуется установленный интерфейс v1.9.0. */
if(!defined('B_PROLOG_INCLUDED')||B_PROLOG_INCLUDED!==true){die();}
\Bitrix\Main\UI\Extension::load('main.core');$a=\Bitrix\Main\Page\Asset::getInstance();
$a->addCss('https://cdn.jsdelivr.net/npm/medeo-font@1.0.0-rc.4.1/dist/medeo.css');
$a->addCss(SITE_TEMPLATE_PATH.'/app.css?v=1.9.0');
$a->addCss('/local/components/markitan/podelu/templates/app/radial.css?v=1.9.1');
$a->addJs('/local/components/markitan/podelu/templates/app/ui.js?v=1.9.0');
$a->addJs('/local/components/markitan/podelu/templates/app/radial.js?v=1.9.1');
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php $APPLICATION->ShowTitle(); ?></title><?php $APPLICATION->ShowHead(); ?></head><body><?php $APPLICATION->ShowPanel(); ?><header class="header"><a class="brand" href="/clients/"><i></i><span>ПО ДЕЛУ <small>by Markitan Concept</small></span></a><span class="header-meta">РАБОЧЕЕ ПРОСТРАНСТВО / v1.9.1</span></header>
