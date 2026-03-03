<?php

declare(strict_types=1);
namespace PicaFlic\Infrastructure\Security;
use Firebase\JWT\JWT as LibJWT;
use Firebase\JWT\Key;

final class JwtService {

  public function __construct(
    private string $secret, 
    private int $ttlSeconds){
  }
  
  public function issue(int $userId): string {
    $now=time(); $payload=['uid'=>$userId,'iat'=>$now,'exp'=>$now+$this->ttlSeconds];
    return LibJWT::encode($payload,$this->secret,'HS256');
  }
  
  public function validate(string $token): ?int {
    try { $d=LibJWT::decode($token,new Key($this->secret,'HS256')); return isset($d->uid)?(int)$d->uid:null; }
    catch(\Throwable) { return null; }
  }
}