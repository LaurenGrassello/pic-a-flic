<?php
declare(strict_types=1);
namespace PicaFlic\Domain\Entity;
use Doctrine\ORM\Mapping as ORM;
/** Streaming services catalog (e.g., netflix, prime). */
#[ORM\Entity, ORM\Table(name:"streaming_services")]
class StreamingService {
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"integer")] private int $id;
  #[ORM\Column(type:"string", length:50, unique:true)] private string $code;
  #[ORM\Column(type:"string", length:100)] private string $name;
  public function __construct(string $code,string $name){$this->code=$code;$this->name=$name;}
  public function getId():int{return $this->id;} public function getCode():string{return $this->code;} public function getName():string{return $this->name;}
}
