<?php
/** По делу v1.8.0. CLI-проверки чистой логики, не требуют Битрикса. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}require __DIR__.'/../upload/local/components/markitan/podelu/lib/Policy.php';require __DIR__.'/../upload/local/components/markitan/podelu/lib/Validation.php';
use Markitan\Podelu\Policy;use Markitan\Podelu\Validation as V;
$n=0;function same($e,$a,$name){global $n;if($e!==$a){fwrite(STDERR,'FAIL '.$name.': '.var_export($a,true).PHP_EOL);exit(1);}++$n;}
same(40,V::parts('0','40'),'40 exact');same(15,V::parts('0','15'),'15 exact');same(60,V::parts('1','0'),'hour');same(1,V::parts('0','1'),'minute');same('40 мин',V::duration(40),'display40');same('1 ч 40 мин',V::duration(100),'display100');same([15,40,60],V::durations('15,40,60'),'presets');
$b=['ID'=>8,'UF_MONTH'=>'2026-11','UF_REPEAT'=>1,'UF_END_MONTH'=>'','UF_NAME'=>'Поддержка','UF_MINUTES'=>600,'UF_DIRECTION_IDS'=>'[1,2]'];same(null,Policy::effective($b,'2026-10'),'before');same(600,(int)Policy::effective($b,'2026-12')['UF_MINUTES'],'monthly');
$ps=[['ID'=>1,'UF_MINUTES'=>600,'IS_SKIPPED'=>false]];$es=[['UF_PACKAGE'=>1,'UF_MINUTES'=>40]];same(560,Policy::balance($ps,$es)['free'],'after40');same(600,Policy::balance($ps,[])['free'],'delete returns40');$es[]=['UF_PACKAGE'=>1,'UF_MINUTES'=>15];same(545,Policy::balance($ps,$es)['free'],'after55');$es[]=['UF_PACKAGE'=>0,'UF_MINUTES'=>30];same(545,Policy::balance($ps,$es)['free'],'extra does not debit');
same('/clients/',V::local('//example.org'),'redirect');same('2028-02-29',V::date('2028-02-29'),'leap');try{V::parts('24','1');same(true,false,'over24 accepted');}catch(InvalidArgumentException $e){same(true,true,'over24 rejected');}echo 'PASS: '.$n.' checks'.PHP_EOL;
