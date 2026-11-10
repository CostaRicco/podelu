<?php
/** По делу v1.8.0. Чтение подходящих пакетов. */
require __DIR__.'/bootstrap.php';pd_post();use Markitan\Podelu\Repository;use Markitan\Podelu\Validation as V;
try{$r=new Repository();$r->ready();$p=$_POST;pd_reply(['success'=>true,'packages'=>$r->options(V::id($p['client']??''),V::id($p['direction']??'0',true),V::date($p['date']??''),V::id($p['task']??'0',true))]);}catch(InvalidArgumentException $e){pd_reply(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){AddMessage2Log($e->getMessage(),'markitan.podelu.options');pd_reply(['success'=>false,'message'=>'Не удалось получить пакеты.'],500);}
