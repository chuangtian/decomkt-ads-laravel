<?php

namespace App\Services\StudentDiscounts;

use App\Services\Credentials\CredentialService;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class StudentDiscountMailService
{
    public function __construct(private readonly CredentialService $credentials) {}

    public function sendApproved(string $storeId, string $recipient, string $code): void
    {
        $escaped = e($code);
        $this->send(
            $storeId,
            $recipient,
            "You're Verified — Your Student Discount Is Ready",
            <<<HTML
<div style="max-width:560px;margin:auto;font:16px/1.55 Arial,sans-serif;color:#171717">
  <h1 style="font-size:28px">Your student status is verified! 🎓</h1>
  <p>Use your exclusive student discount code at checkout:</p>
  <div style="border:2px solid #171717;padding:20px;text-align:center;font-size:26px;font-weight:700;letter-spacing:1px">{$escaped}</div>
  <p style="margin-top:24px"><a href="https://www.macfoxbike.com" style="display:block;background:#171717;color:#fff;text-decoration:none;text-align:center;padding:14px">SHOP NOW</a></p>
  <p style="color:#666;font-size:14px">Valid on eligible products only. Combination rules are determined by the store's discount settings.</p>
</div>
HTML,
        );
    }

    public function sendRejected(string $storeId, string $recipient): void
    {
        $this->send(
            $storeId,
            $recipient,
            'Update on your student discount application',
            '<div style="max-width:560px;margin:auto;font:16px/1.55 Arial,sans-serif;color:#171717"><h1 style="font-size:28px">We could not approve your application</h1><p>The image submitted could not be confirmed as a student ID. You may submit a new, clear photo of your student ID and try again.</p></div>',
        );
    }

    private function send(string $storeId, string $recipient, string $subject, string $html): void
    {
        $values = $this->credentials->many([
            'MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD',
            'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        ], $storeId);
        $host = $values['MAIL_HOST'] ?? null;
        $username = $values['MAIL_USERNAME'] ?? null;
        $password = $values['MAIL_PASSWORD'] ?? null;
        if (! $host || ! $username || ! $password) {
            throw new RuntimeException('Email delivery is not configured.');
        }

        $scheme = strtolower($values['MAIL_SCHEME'] ?? 'smtps') === 'smtp' ? 'smtp' : 'smtps';
        $port = (int) ($values['MAIL_PORT'] ?? ($scheme === 'smtps' ? 465 : 587));
        $dsn = sprintf('%s://%s:%s@%s:%d', $scheme, rawurlencode($username), rawurlencode($password), $host, $port);
        $email = (new Email)
            ->from(sprintf('%s <%s>', $values['MAIL_FROM_NAME'] ?? 'Macfox', $values['MAIL_FROM_ADDRESS'] ?? $username))
            ->to($recipient)
            ->subject($subject)
            ->html($html);
        (new Mailer(Transport::fromDsn($dsn)))->send($email);
    }
}
