<?php
/** По делу v1.4.0: оболочка, библиотеки и оформление. */
if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true){die();}
\Bitrix\Main\UI\Extension::load('main.core');CJSCore::Init(['ajax']);
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH.'/app.css?v=1.4.0');
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php $APPLICATION->ShowTitle(); ?></title><?php $APPLICATION->ShowHead(); ?></head><body><?php $APPLICATION->ShowPanel(); ?><header class="app-header"><a href="/" class="brand"><i class="live-dot" title="Интерфейс активен"></i><span>ПО ДЕЛУ <small>by Markitan Concept</small></span></a><div class="header-meta"><span>КЛИЕНТСКОЕ ПРОСТРАНСТВО</span><b>v1.4.0</b></div></header>
