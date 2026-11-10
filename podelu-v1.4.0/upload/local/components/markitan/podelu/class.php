<?php
/** По делу v1.4.0. Административный компонент; каждый action: POST, CSRF, вход и IsAdmin.
 * Абонентские лимиты, исключения one, новые условия from, прекращение и возобновление.
 * Задачи/расходы отсутствуют: отображаем только выделенный лимит, без фиктивных остатков. */
if(!defined('B_PROLOG_INCLUDED')||B_PROLOG_INCLUDED!==true){die();}
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Errorable;
use Bitrix\Main\Context;
require_once __DIR__.'/lib/Repository.php';
class MarkitanPodeluComponent extends CBitrixComponent implements Controllerable,Errorable {
 private $errors;
 public function __construct($component=null){parent::__construct($component);$this->errors=new ErrorCollection();}
 public function configureActions(){
  $out=[];foreach(['addClient','addDirection','savePackage','setEnd','clearException'] as $a)$out[$a]=['prefilters'=>[new ActionFilter\Authentication(),new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),new ActionFilter\Csrf()]];return $out;
 }
 private function admin(): bool {global $USER;return is_object($USER)&&$USER->IsAuthorized()&&$USER->IsAdmin();}
 private function text($v,int $max,bool $required=false): string {
  if(!is_string($v))throw new InvalidArgumentException('Некорректный формат текста.');$v=trim($v);
  if(($required&&$v==='')||mb_strlen($v)>$max)throw new InvalidArgumentException('Проверьте обязательные поля и длину текста.');
  if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$v))throw new InvalidArgumentException('Недопустимые символы.');return $v;
 }
 private function integer($v,bool $zero=false): int {
  if(!is_scalar($v)||!preg_match('/^\d{1,10}$/',(string)$v))throw new InvalidArgumentException('Некорректный идентификатор.');$n=(int)$v;if($n<($zero?0:1)||$n>2147483647)throw new InvalidArgumentException('Некорректный идентификатор.');return $n;
 }
 private function month($v,bool $empty=false): string {
  if($empty&&$v==='')return '';
  if(!is_string($v)||!preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/',$v))throw new InvalidArgumentException('Выберите месяц от 2000 до 2100 года.');return $v;
 }
 private function run(callable $fn){
  if(!$this->admin()){$this->errors->setError(new Error('Нет доступа.','ACCESS_DENIED'));return null;}
  try{return $fn(new \Markitan\Podelu\Repository());}
  catch(InvalidArgumentException $e){$this->errors->setError(new Error($e->getMessage(),'VALIDATION'));}
  catch(Throwable $e){AddMessage2Log($e->getMessage(),'markitan.podelu');$this->errors->setError(new Error('Не удалось выполнить действие. Проверьте миграцию v1.4.0 и журнал ошибок.','STORAGE'));}return null;
 }
 public function addClientAction($name='',$site='',$comment='',$active='Y'){
  return $this->run(function($repo)use($name,$site,$comment,$active){global $USER;
   $name=$this->text($name,150,true);$site=$this->text($site,255);$comment=$this->text($comment,3000);if(!in_array($active,['Y','N'],true))throw new InvalidArgumentException('Некорректная активность.');
   if($site!==''){if(strpos($site,'://')===false)$site='https://'.$site;$p=parse_url($site);if(!$p||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass'])||preg_match('/\s/u',$site)||mb_strlen($site)>255)throw new InvalidArgumentException('Укажите корректный адрес сайта.');}
   return ['id'=>$repo->addClient($name,$site,$comment,$active==='Y',(int)$USER->GetID())];
  });
 }
 public function addDirectionAction($name=''){return $this->run(function($repo)use($name){return ['id'=>$repo->addDirection($this->text($name,100,true))];});}
 public function savePackageAction($id='0',$client='',$month='',$name='',$hours='',$directions=[],$repeat='N',$end='',$scope='one'){
  return $this->run(function($repo)use($id,$client,$month,$name,$hours,$directions,$repeat,$end,$scope){global $USER;
   $id=$this->integer($id,true);$client=$this->integer($client);$month=$this->month($month);$name=$this->text($name,150,true);$end=$this->month($end,true);
   if(!in_array($repeat,['Y','N'],true)||!in_array($scope,['one','from'],true))throw new InvalidArgumentException('Некорректный режим пакета.');
   if(!is_string($hours)&&!is_numeric($hours))throw new InvalidArgumentException('Укажите часы.');$hours=str_replace(',','.',trim((string)$hours));
   if(!preg_match('/^(\d{1,5})(?:\.(\d{1,2}))?$/',$hours,$m))throw new InvalidArgumentException('Лимит: от 0 до 99999,75 ч, шаг 0,25 ч.');
   $fraction=isset($m[2])?(int)str_pad($m[2],2,'0'):0;if(!in_array($fraction,[0,25,50,75],true))throw new InvalidArgumentException('Шаг лимита 0,25 ч (15 минут).');$minutes=(int)$m[1]*60+intdiv($fraction*60,100);
   if(!is_array($directions)||count($directions)<1||count($directions)>50)throw new InvalidArgumentException('Выберите от 1 до 50 направлений.');$dirs=[];foreach($directions as $v)$dirs[]=$this->integer($v);$dirs=array_values(array_unique($dirs));
   return ['id'=>$repo->savePackage($id,$client,$month,$name,$minutes,$dirs,$repeat==='Y',$end,$scope,(int)$USER->GetID())];
  });
 }
 public function setEndAction($id='',$client='',$end=''){
  return $this->run(function($repo)use($id,$client,$end){return ['id'=>$repo->setEnd($this->integer($id),$this->integer($client),$this->month($end,true))];});
 }
 public function clearExceptionAction($id='',$client='',$month=''){
  return $this->run(function($repo)use($id,$client,$month){return ['id'=>$repo->clearException($this->integer($id),$this->integer($client),$this->month($month))];});
 }
 public function executeComponent(){
  $res=Context::getCurrent()->getResponse();$res->addHeader('Cache-Control','no-store, private');$res->addHeader('X-Content-Type-Options','nosniff');$res->addHeader('Referrer-Policy','same-origin');
  $this->arResult=['ALLOWED'=>$this->admin(),'ERROR'=>''];if(!$this->arResult['ALLOWED']){$res->setStatus('403 Forbidden');$this->includeComponentTemplate();return;}
  $req=Context::getCurrent()->getRequest();$q=$req->getQuery('q');$q=is_string($q)?trim($q):'';if(mb_strlen($q)>150)$q='';$page=$req->getQuery('page');$page=is_scalar($page)?max(1,(int)$page):1;$id=$req->getQuery('client');$id=is_scalar($id)?max(0,(int)$id):0;
  try{$month=$this->month($req->getQuery('month'));}catch(Throwable $e){$month=date('Y-m');}
  try{
   $repo=new \Markitan\Podelu\Repository();$repo->ready();$list=$repo->clients($q,$page);$selected=$id>0?$repo->client($id):($list['rows'][0]??null);
   $dirs=$repo->directions();$ps=$selected?$repo->packages((int)$selected['ID'],$month):[];$subs=$selected?$repo->subscriptions((int)$selected['ID']):[];
   $this->arResult+=['LIST'=>$list,'STATS'=>$repo->stats(),'SELECTED'=>$selected,'Q'=>$q,'MONTH'=>$month,'DIRECTIONS'=>$dirs,'PACKAGES'=>$ps,'SUBSCRIPTIONS'=>$subs];
  }catch(Throwable $e){AddMessage2Log($e->getMessage(),'markitan.podelu');$this->arResult['ERROR']='Нужно установить структуру v1.4.0. Клиенты и пакеты предыдущих версий сохраняются.';}
  $this->includeComponentTemplate();
 }
 public function getErrors(){return $this->errors->toArray();}
 public function getErrorByCode($code){return $this->errors->getErrorByCode($code);}
}
