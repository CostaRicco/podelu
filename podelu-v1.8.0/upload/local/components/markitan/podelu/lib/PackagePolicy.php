<?php
/** По делу v1.8.0. */
namespace Markitan\Podelu;require_once __DIR__.'/Policy.php';
final class PackagePolicy{public static function effective(array $b,string $m,array $r=[]): ?array{return Policy::effective($b,$m,$r);}}
