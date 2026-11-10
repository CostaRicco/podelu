<?php
/** По делу v1.4.0. Изолированные тесты PackagePolicy. Запуск: php tests/run.php
 * Не загружать в публичную папку. Не требует Битрикса и базы, не меняет данные. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../upload/local/components/markitan/podelu/lib/PackagePolicy.php';
use Markitan\Podelu\PackagePolicy;
$passed=0;
function same($expected,$actual,string $name): void {global $passed;if($expected!==$actual){fwrite(STDERR,'FAIL '.$name.': '.var_export($actual,true).PHP_EOL);exit(1);}++$passed;}
$base=['ID'=>8,'UF_MONTH'=>'2026-11','UF_END_MONTH'=>'','UF_REPEAT'=>1,'UF_NAME'=>'Поддержка','UF_MINUTES'=>600,'UF_DIRECTION_IDS'=>'[1,2]'];
$rule=static function(int $id,string $m,string $scope,int $minutes){return ['ID'=>$id,'UF_PACKAGE'=>8,'UF_MONTH'=>$m,'UF_SCOPE'=>$scope,'UF_NAME'=>'Поддержка '.$minutes,'UF_MINUTES'=>$minutes,'UF_DIRECTION_IDS'=>'[1,2]'];};
same(null,PackagePolicy::effective($base,'2026-10'),'before start');
same(600,(int)PackagePolicy::effective($base,'2026-11')['UF_MINUTES'],'start');
same(600,(int)PackagePolicy::effective($base,'2026-12')['UF_MINUTES'],'new monthly limit');
same(600,(int)PackagePolicy::effective($base,'2030-01')['UF_MINUTES'],'continues');
$rules=[$rule(1,'2026-12','one',900)];
same(900,(int)PackagePolicy::effective($base,'2026-12',$rules)['UF_MINUTES'],'one month exception');
same(600,(int)PackagePolicy::effective($base,'2027-01',$rules)['UF_MINUTES'],'exception not inherited');
$rules[]=$rule(2,'2027-01','from',1200);
same(600,(int)PackagePolicy::effective($base,'2026-11',$rules)['UF_MINUTES'],'past unchanged');
same(1200,(int)PackagePolicy::effective($base,'2027-01',$rules)['UF_MINUTES'],'new terms start');
same(1200,(int)PackagePolicy::effective($base,'2027-02',$rules)['UF_MINUTES'],'new terms continue');
$rules[]=$rule(3,'2027-03','from',1800);$rules[]=$rule(4,'2027-04','one',2100);
same(1800,(int)PackagePolicy::effective($base,'2027-03',array_reverse($rules))['UF_MINUTES'],'chronological terms');
same(2100,(int)PackagePolicy::effective($base,'2027-04',$rules)['UF_MINUTES'],'future exception preserved');
same(1800,(int)PackagePolicy::effective($base,'2027-05',$rules)['UF_MINUTES'],'return after future exception');
$both=[$rule(10,'2026-12','one',900),$rule(11,'2026-12','from',1200)];
same(900,(int)PackagePolicy::effective($base,'2026-12',$both)['UF_MINUTES'],'one has priority in policy');
same(true,PackagePolicy::effective($base,'2026-12',$both)['HAS_MONTH_OVERRIDE'],'exception flag');
$ended=$base;$ended['UF_END_MONTH']='2026-12';
same(600,(int)PackagePolicy::effective($ended,'2026-12')['UF_MINUTES'],'inclusive last month');
same(null,PackagePolicy::effective($ended,'2027-01'),'after end');
$old=$base;unset($old['UF_REPEAT']);
same(600,(int)PackagePolicy::effective($old,'2026-11')['UF_MINUTES'],'old package preserved');
same(null,PackagePolicy::effective($old,'2026-12'),'old package stays one off');
$zero=$base;$zero['UF_MINUTES']=0;
same(0,(int)PackagePolicy::effective($zero,'2026-12')['UF_MINUTES'],'zero valid');
$foreign=$rule(50,'2026-11','from',999);$foreign['UF_PACKAGE']=99;
same(600,(int)PackagePolicy::effective($base,'2026-12',[$foreign])['UF_MINUTES'],'foreign rules ignored');
$view=PackagePolicy::effective($base,'2027-04',$rules);
same(600,$base['UF_MINUTES'],'base not mutated');
same('one',$view['RULE_SOURCE'],'source recorded');
same('2027-04',$view['VIEW_MONTH'],'view month recorded');
echo 'PASS: '.$passed.' checks'.PHP_EOL;
