<?php
/** По делу v1.8.0. Общая защита HTTP. */
if(!defined('NO_KEEP_STATISTIC'))define('NO_KEEP_STATISTIC',true);require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';require_once $_SERVER['DOCUMENT_ROOT'].'/local/components/markitan/podelu/lib/Repository.php';require_once $_SERVER['DOCUMENT_ROOT'].'/local/components/markitan/podelu/lib/Validation.php';header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
function pd_reply(array $a,int $s=200): void {http_response_code($s);header('Content-Type: application/json; charset=UTF-8');echo json_encode($a,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP);exit;}
if(!$USER->IsAuthorized()||!$USER->IsAdmin())pd_reply(['success'=>false,'message'=>'Нужен вход администратора.'],403);
function pd_post(): void {if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');pd_reply(['success'=>false,'message'=>'Требуется POST.'],405);}if(!check_bitrix_sessid())pd_reply(['success'=>false,'message'=>'Сессия истекла. Обновите страницу.'],403);}
