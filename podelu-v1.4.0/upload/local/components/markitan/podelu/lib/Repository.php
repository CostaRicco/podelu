<?php
/** По делу v1.4.0. Репозиторий HL; обратная совместимость с v1.2/v1.3.
 * Включение повторения явное. Существующие UF_REPEAT по умолчанию false.
 * База повторяющегося пакета не переписывается при месячных изменениях.
 * Операции над правилами блокируют строку пакета в транзакции InnoDB. */
namespace Markitan\Podelu;
if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true){die();}
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;
require_once __DIR__.'/PackagePolicy.php';
final class Repository {
 private $classes=[];
 private const TYPES=[
  'clients'=>['McPodeluClients','mc_podelu_clients',['UF_NAME','UF_SITE','UF_COMMENT','UF_ACTIVE','UF_CREATED_AT','UF_CREATED_BY','UF_XML_ID']],
  'directions'=>['McPodeluDirections','mc_podelu_directions',['UF_NAME','UF_ACTIVE','UF_XML_ID']],
  'packages'=>['McPodeluPackages','mc_podelu_packages',['UF_CLIENT','UF_MONTH','UF_NAME','UF_MINUTES','UF_DIRECTION_IDS','UF_XML_ID','UF_CREATED_AT','UF_CREATED_BY','UF_REPEAT','UF_END_MONTH']],
  'rules'=>['McPodeluPackageRules','mc_podelu_package_rules',['UF_PACKAGE','UF_MONTH','UF_SCOPE','UF_NAME','UF_MINUTES','UF_DIRECTION_IDS','UF_CREATED_AT','UF_CREATED_BY']],
 ];
 public function __construct(){if(!Loader::includeModule('highloadblock'))throw new \RuntimeException('Нет модуля Highload-блоков.');}
 public function dataClass(string $type): string {
  if(isset($this->classes[$type]))return $this->classes[$type];
  if(!isset(self::TYPES[$type]))throw new \RuntimeException('Неизвестная сущность.');
  [$name,$table,$fields]=self::TYPES[$type];$hl=HighloadBlockTable::getList(['filter'=>['=NAME'=>$name],'limit'=>1])->fetch();
  if(!$hl || $hl['TABLE_NAME']!==$table)throw new \RuntimeException('Не установлена структура '.$name.'.');
  $e=HighloadBlockTable::compileEntity($hl);foreach($fields as $f)if(!$e->hasField($f))throw new \RuntimeException('Отсутствует поле '.$f.'.');return $this->classes[$type]=$e->getDataClass();
 }
 public function ready(): void {foreach(array_keys(self::TYPES) as $t)$this->dataClass($t);}
 public function clients(string $q,int $page): array {
  $c=$this->dataClass('clients');$filter=$q===''?[]:['%UF_NAME'=>$q];$count=(int)$c::getCount($filter);$pages=max(1,(int)ceil($count/20));$page=min($pages,max(1,$page));
  return ['rows'=>$c::getList(['filter'=>$filter,'order'=>['ID'=>'DESC'],'limit'=>20,'offset'=>($page-1)*20])->fetchAll(),'count'=>$count,'page'=>$page,'pages'=>$pages];
 }
 public function stats(): array {$c=$this->dataClass('clients');return ['total'=>(int)$c::getCount(),'active'=>(int)$c::getCount(['=UF_ACTIVE'=>1])];}
 public function client(int $id): ?array {if($id<1)return null;$c=$this->dataClass('clients');return $c::getByPrimary($id)->fetch()?:null;}
 private function write(string $type,array $data,int $id=0): int {$c=$this->dataClass($type);$r=$id?$c::update($id,$data):$c::add($data);if(!$r->isSuccess())throw new \RuntimeException(implode('; ',$r->getErrorMessages()));return $id?: (int)$r->getId();}
 public function addClient(string $name,string $site,string $comment,bool $active,int $user): int {
  return $this->write('clients',['UF_NAME'=>$name,'UF_SITE'=>$site,'UF_COMMENT'=>$comment,'UF_ACTIVE'=>$active?1:0,'UF_CREATED_AT'=>new DateTime(),'UF_CREATED_BY'=>$user,'UF_XML_ID'=>bin2hex(random_bytes(16))]);
 }
 public function directions(): array {$c=$this->dataClass('directions');return $c::getList(['order'=>['UF_NAME'=>'ASC','ID'=>'ASC']])->fetchAll();}
 public function addDirection(string $name): int {foreach($this->directions() as $d)if(mb_strtolower(trim($d['UF_NAME']))===mb_strtolower($name))throw new \InvalidArgumentException('Направление с таким названием уже есть.');return $this->write('directions',['UF_NAME'=>$name,'UF_ACTIVE'=>1,'UF_XML_ID'=>bin2hex(random_bytes(16))]);}
 public static function directionIds(string $json): array {
  $v=json_decode($json,true);if(!is_array($v))throw new \RuntimeException('Повреждены направления пакета.');$ids=[];foreach($v as $id){if(!is_int($id)||$id<1)throw new \RuntimeException('Некорректные ID направлений.');$ids[]=$id;}return array_values(array_unique($ids));
 }
 private function validateDirs(array $ids): void {$known=[];foreach($this->directions() as $d)$known[(int)$d['ID']]=true;foreach($ids as $id)if(!isset($known[$id]))throw new \InvalidArgumentException('Направление не найдено.');}
 public function packages(int $client,string $month): array {
  $c=$this->dataClass('packages');$r=$this->dataClass('rules');$rows=$c::getList(['filter'=>['=UF_CLIENT'=>$client],'order'=>['ID'=>'ASC']])->fetchAll();
  $ids=array_map(static function($p){return (int)$p['ID'];},$rows);$allRules=$ids?$r::getList(['filter'=>['@UF_PACKAGE'=>$ids,'<=UF_MONTH'=>$month],'order'=>['UF_MONTH'=>'ASC','ID'=>'ASC']])->fetchAll():[];
  $by=[];foreach($allRules as $rule)$by[(int)$rule['UF_PACKAGE']][]=$rule;$out=[];
  foreach($rows as $p){$view=PackagePolicy::effective($p,$month,$by[(int)$p['ID']]??[]);if($view){$view['DIRECTION_IDS']=self::directionIds((string)$view['UF_DIRECTION_IDS']);$out[]=$view;}}
  return $out;
 }
 public function subscriptions(int $client): array {
  $c=$this->dataClass('packages');return $c::getList(['filter'=>['=UF_CLIENT'=>$client,'=UF_REPEAT'=>1],'order'=>['ID'=>'DESC']])->fetchAll();
 }
 private function locked(int $id,callable $fn){
  $this->dataClass('packages');$this->dataClass('rules');$conn=Application::getConnection();$conn->startTransaction();
  try{
   // Имя таблицы константное, ID приведён к int, пользовательских SQL-фрагментов нет.
   $lock=$conn->query('SELECT ID FROM mc_podelu_packages WHERE ID = '.(int)$id.' FOR UPDATE')->fetch();
   if(!$lock)throw new \InvalidArgumentException('Пакет не найден.');$result=$fn();$conn->commitTransaction();return $result;
  }catch(\Throwable $e){$conn->rollbackTransaction();throw $e;}
 }
 private function base(int $id,int $client): array {$c=$this->dataClass('packages');$p=$c::getByPrimary($id)->fetch();if(!$p || (int)$p['UF_CLIENT']!==$client)throw new \InvalidArgumentException('Пакет не принадлежит выбранному клиенту.');return $p;}
 public function savePackage(int $id,int $client,string $month,string $name,int $minutes,array $dirs,bool $repeat,string $end,string $scope,int $user): int {
  if(!$this->client($client))throw new \InvalidArgumentException('Клиент не найден.');$this->validateDirs($dirs);
  if($end!==''&&$end<$month&&$id===0)throw new \InvalidArgumentException('Окончание не может быть раньше начала.');
  $values=['UF_NAME'=>$name,'UF_MINUTES'=>$minutes,'UF_DIRECTION_IDS'=>json_encode(array_values($dirs))];
  if(!$id)return $this->write('packages',$values+['UF_CLIENT'=>$client,'UF_MONTH'=>$month,'UF_REPEAT'=>$repeat?1:0,'UF_END_MONTH'=>$repeat?$end:'','UF_XML_ID'=>bin2hex(random_bytes(16)),'UF_CREATED_AT'=>new DateTime(),'UF_CREATED_BY'=>$user]);
  return $this->locked($id,function()use($id,$client,$month,$values,$repeat,$end,$scope,$user){
   $base=$this->base($id,$client);
   if(empty($base['UF_REPEAT'])){
    if($base['UF_MONTH']!==$month)throw new \InvalidArgumentException('Разовый пакет относится к другому месяцу.');
    if($repeat && $end!==''&&$end<$base['UF_MONTH'])throw new \InvalidArgumentException('Окончание раньше начала.');
    return $this->write('packages',$values+['UF_REPEAT'=>$repeat?1:0,'UF_END_MONTH'=>$repeat?$end:''],$id);
   }
   if(!$repeat)throw new \InvalidArgumentException('Повторяющийся пакет остановите через «Срок действия», не превращая его в разовый.');
   if(!PackagePolicy::effective($base,$month))throw new \InvalidArgumentException('Пакет не действует в этом месяце.');
   $c=$this->dataClass('rules');$old=$c::getList(['filter'=>['=UF_PACKAGE'=>$id,'=UF_MONTH'=>$month,'=UF_SCOPE'=>$scope],'limit'=>1])->fetch();
   $data=$values+['UF_PACKAGE'=>$id,'UF_MONTH'=>$month,'UF_SCOPE'=>$scope,'UF_CREATED_AT'=>new DateTime(),'UF_CREATED_BY'=>$user];
   $this->write('rules',$data,$old?(int)$old['ID']:0);
   if($scope==='from'){$exceptions=$c::getList(['filter'=>['=UF_PACKAGE'=>$id,'=UF_MONTH'=>$month,'=UF_SCOPE'=>'one']])->fetchAll();foreach($exceptions as $ex){$deleted=$c::delete((int)$ex['ID']);if(!$deleted->isSuccess())throw new \RuntimeException('Не удалось заменить исключение текущего месяца.');}}
   return $id;
  });
 }
 public function setEnd(int $id,int $client,string $end): int {
  return $this->locked($id,function()use($id,$client,$end){$base=$this->base($id,$client);if(empty($base['UF_REPEAT']))throw new \InvalidArgumentException('Это разовый пакет.');if($end!==''&&$end<$base['UF_MONTH'])throw new \InvalidArgumentException('Последний месяц не может быть раньше начала.');return $this->write('packages',['UF_END_MONTH'=>$end],$id);});
 }
 public function clearException(int $id,int $client,string $month): int {
  return $this->locked($id,function()use($id,$client,$month){$base=$this->base($id,$client);if(empty($base['UF_REPEAT']))throw new \InvalidArgumentException('Это разовый пакет.');$c=$this->dataClass('rules');$rows=$c::getList(['filter'=>['=UF_PACKAGE'=>$id,'=UF_MONTH'=>$month,'=UF_SCOPE'=>'one']])->fetchAll();foreach($rows as $row){$r=$c::delete((int)$row['ID']);if(!$r->isSuccess())throw new \RuntimeException('Не удалось убрать исключение.');}return $id;});
 }
}
