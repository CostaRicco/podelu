<?php
/** По делу v1.4.0. Совместимый адаптер клиентов v1.2.0. */
namespace Markitan\Podelu;
if(!defined('B_PROLOG_INCLUDED')||B_PROLOG_INCLUDED!==true){die();}
require_once __DIR__.'/Repository.php';
final class ClientRepository {
 public const BLOCK_NAME='McPodeluClients';public const TABLE_NAME='mc_podelu_clients';private $repo;
 public function __construct(){$this->repo=new Repository();}
 public function list(string $q,int $p,int $size=20): array{return $this->repo->clients($q,$p);}
 public function stats(): array{return $this->repo->stats();}
 public function find(int $id): ?array{return $this->repo->client($id);}
 public function add(string $n,string $s,string $c,bool $a,int $u): int{return $this->repo->addClient($n,$s,$c,$a,$u);}
}
