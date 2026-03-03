<?php
declare(strict_types=1);

namespace PicaFlic\Infrastructure\Service;

final class MailService {
  public function __construct(private string $env = 'dev') {}
  public function send(string $to, string $subject, string $html): void {
    if ($this->env !== 'prod') {
      error_log("[MAIL:DEV] $to | $subject\n$html\n");
      return;
    }
    // TODO: plug real SMTP here later
  }
}