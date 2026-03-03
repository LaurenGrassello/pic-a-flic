<?php
declare(strict_types=1);
namespace PicaFlic\Application\Controller;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
/** Quiz endpoints (stubs with comments). */
final class QuizController {
  /** GET /quiz — return static quiz (replace with DB) */
  public function get(Request $req, Response $res): Response {
    $quiz=[['id'=>1,'question'=>'Pick genres','answers'=>['Drama','Comedy','Horror','Sci-Fi','Romance']],['id'=>2,'question'=>'Preferred runtime','answers'=>['<= 90','<= 120','<= 150']]];
    $res->getBody()->write(json_encode($quiz)); return $res->withHeader('Content-Type','application/json');
  }
  /** POST /quiz/submit — accept answers (stub) */
  public function submit(Request $req, Response $res): Response {
    $d=json_decode((string)$req->getBody(),true)?:[]; $res->getBody()->write(json_encode(['ok'=>True,'stored'=>$d['answer_ids']??[]])); return $res->withHeader('Content-Type','application/json');
  }
}
