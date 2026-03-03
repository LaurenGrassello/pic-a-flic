<?php
declare(strict_types=1);
namespace PicaFlic\Application\Middleware;
use PicaFlic\Infrastructure\Security\JwtService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
/** Validates Bearer token and attaches uid to request attributes. */
final class JwtMiddleware implements MiddlewareInterface {
  public function __construct(private JwtService $jwt){}
  public function process(Request $request, Handler $handler): Response {
    $auth=$request->getHeaderLine('Authorization');
    if(!preg_match('/Bearer\s+(.*)/i',$auth,$m)) return $this->unauth('Missing Bearer token');
    $uid=$this->jwt->validate($m[1]??''); if(!$uid) return $this->unauth('Invalid or expired token');
    return $handler->handle($request->withAttribute('uid',$uid));
  }
  private function unauth(string $msg): Response { $r=new SlimResponse(401); $r->getBody()->write(json_encode(['error'=>$msg])); return $r->withHeader('Content-Type','application/json'); }
}
