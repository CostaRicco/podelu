<?php
/** По делу v1.4.0. Чистая логика месячного лимита; нет зависимостей от ORM и Битрикса.
 * Порядок: базовый лимит -> последнее правило from -> исключение one текущего месяца.
 * Лимиты не зависят от расходов прошлого месяца: переноса времени нет. */
namespace Markitan\Podelu;
final class PackagePolicy {
    public static function effective(array $base,string $month,array $rules=[]): ?array {
        $repeat=!empty($base['UF_REPEAT']);$start=(string)$base['UF_MONTH'];$end=(string)($base['UF_END_MONTH']??'');
        if(!$repeat && $month!==$start)return null;
        if($repeat && ($month<$start || ($end!=='' && $month>$end)))return null;
        $result=$base;$result['VIEW_MONTH']=$month;$result['RULE_SOURCE']='base';$result['RULE_MONTH']=$start;$result['HAS_MONTH_OVERRIDE']=false;
        if(!$repeat)return $result;
        usort($rules,static function($a,$b){$cmp=strcmp($a['UF_MONTH'],$b['UF_MONTH']);return $cmp!==0?$cmp:((int)$a['ID']<=>(int)$b['ID']);});
        $one=null;
        foreach($rules as $rule){
            if((int)$rule['UF_PACKAGE']!==(int)$base['ID'] || $rule['UF_MONTH']<$start)continue;
            if($rule['UF_SCOPE']==='from' && $rule['UF_MONTH']<=$month){
                foreach(['UF_NAME','UF_MINUTES','UF_DIRECTION_IDS'] as $f)$result[$f]=$rule[$f];
                $result['RULE_SOURCE']='from';$result['RULE_MONTH']=$rule['UF_MONTH'];
            }
            if($rule['UF_SCOPE']==='one' && $rule['UF_MONTH']===$month)$one=$rule;
        }
        if($one){foreach(['UF_NAME','UF_MINUTES','UF_DIRECTION_IDS'] as $f)$result[$f]=$one[$f];$result['RULE_SOURCE']='one';$result['RULE_MONTH']=$month;$result['HAS_MONTH_OVERRIDE']=true;}
        return $result;
    }
}
