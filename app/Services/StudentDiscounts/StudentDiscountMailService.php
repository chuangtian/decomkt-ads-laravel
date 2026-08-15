<?php

namespace App\Services\StudentDiscounts;

use App\Services\Credentials\CredentialService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class StudentDiscountMailService
{
    private const BRAND_KEYS = [
        'STUDENT_DISCOUNT_BRAND_NAME',
        'STUDENT_DISCOUNT_LOGO_URL',
        'STUDENT_DISCOUNT_SHOP_URL',
        'STUDENT_DISCOUNT_SUPPORT_URL',
        'STUDENT_DISCOUNT_INSTAGRAM_URL',
        'STUDENT_DISCOUNT_FACEBOOK_URL',
        'STUDENT_DISCOUNT_TIKTOK_URL',
        'STUDENT_DISCOUNT_YOUTUBE_URL',
    ];

    public function __construct(private readonly CredentialService $credentials) {}

    public function sendApproved(string $storeId, string $recipient, string $code): void
    {
        $this->send(
            $storeId,
            $recipient,
            "You're Verified — Your Student Discount Is Ready",
            $this->approvedHtml($storeId, $code),
        );
    }

    public function approvedHtml(string $storeId, string $code): string
    {
        $brand = $this->brand($storeId);
        $escapedCode = e($code);
        $escapedBrandName = e($brand['name']);

        $logo = '';
        if ($brand['logoUrl']) {
            $image = '<img src="'.e($brand['logoUrl']).'" alt="'.e($brand['name']).'" width="180" style="display:block;max-width:180px;width:100%;height:auto;margin:0 auto;border:0">';
            $logo = $brand['shopUrl']
                ? '<a href="'.e($brand['shopUrl']).'" target="_blank" style="display:inline-block;text-decoration:none">'.$image.'</a>'
                : $image;
            $logo = '<tr><td align="center" style="padding:24px 40px 22px;border-bottom:1px solid #dedede">'.$logo.'</td></tr>';
        }

        $shopButton = $brand['shopUrl']
            ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0 0"><tr><td align="center" bgcolor="#111111"><a href="'.e($brand['shopUrl']).'" target="_blank" style="display:block;padding:15px 22px;color:#ffffff;text-decoration:none;font-size:16px;line-height:20px;font-weight:700">SHOP NOW</a></td></tr></table>'
            : '';

        $support = '';
        if ($brand['supportUrl']) {
            $support = '<tr><td style="padding:0 34px 30px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #dedede"><tr><td style="padding-top:24px"><p style="margin:0 0 10px;font-size:15px;line-height:20px;font-weight:700;color:#111111">HELP</p><p style="margin:0;font-size:15px;line-height:23px;color:#555555">Having trouble with your code? <a href="'.e($brand['supportUrl']).'" style="color:#111111;font-weight:700;text-decoration:underline">Contact our support team</a> and we’ll be happy to help.</p></td></tr></table></td></tr>';
        }

        $iconBaseUrl = rtrim((string) config('app.url'), '/').'/email-icons';
        $socialLinks = collect([
            ['url' => $brand['instagramUrl'], 'label' => 'Instagram', 'image' => $iconBaseUrl.'/instagram.png'],
            ['url' => $brand['facebookUrl'], 'label' => 'Facebook', 'image' => $iconBaseUrl.'/facebook.png'],
            ['url' => $brand['tiktokUrl'], 'label' => 'TikTok', 'image' => $iconBaseUrl.'/tiktok.png'],
            ['url' => $brand['youtubeUrl'], 'label' => 'YouTube', 'image' => $iconBaseUrl.'/youtube.png'],
        ])->filter(fn (array $social): bool => filled($social['url']));
        $social = '';
        if ($socialLinks->isNotEmpty()) {
            $items = $socialLinks->map(fn (array $social): string => '<td style="padding:0 8px"><a href="'.e($social['url']).'" target="_blank" aria-label="'.e($social['label']).'" title="'.e($social['label']).'" style="display:block;width:24px;height:24px;text-decoration:none"><img src="'.e($social['image']).'" alt="'.e($social['label']).'" width="24" height="24" style="display:block;width:24px;height:24px;border:0"></a></td>')->implode('');
            $social = '<tr><td align="center" style="padding:20px 34px;border-top:1px dashed #bdbdbd"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>'.$items.'</tr></table></td></tr>';
        }

        $campaign = DB::table('StudentDiscountCampaign')->where('storeId', $storeId)->first();
        $cannotCombine = ! $campaign || (! $campaign->combinesWithProduct && ! $campaign->combinesWithOrder && ! $campaign->combinesWithShipping);
        $terms = $cannotCombine
            ? 'Valid on eligible products only. Cannot be combined with other codes.'
            : 'Valid on eligible products only. Combination rules are determined by the store’s discount settings.';

        return <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f3f3">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f3f3f3">
  <tr>
    <td align="center" style="padding:20px 10px">
      <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:560px;background:#ffffff;color:#111111;font-family:Arial,Helvetica,sans-serif">
        {$logo}
        <tr>
          <td style="padding:28px 34px 30px">
            <h1 style="margin:0 0 20px;font-size:28px;line-height:35px;font-weight:800;color:#111111">Your student status is verified!&nbsp;🎓</h1>
            <p style="margin:0 0 18px;font-size:16px;line-height:25px;color:#333333">Use your exclusive student discount code at checkout and save on your next {$escapedBrandName} ride.</p>
            <div style="border:2px solid #111111;padding:18px 14px;text-align:center;font-family:'Courier New',monospace;font-size:24px;line-height:30px;font-weight:700;letter-spacing:2px;color:#111111">{$escapedCode}</div>
            {$shopButton}
            <p style="margin:18px 0 0;font-size:14px;line-height:21px;font-style:italic;color:#555555">{$terms}</p>
          </td>
        </tr>
        {$support}
        {$social}
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
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

    /**
     * @return array{name: string, logoUrl: ?string, shopUrl: ?string, supportUrl: ?string, instagramUrl: ?string, facebookUrl: ?string, tiktokUrl: ?string, youtubeUrl: ?string}
     */
    private function brand(string $storeId): array
    {
        $store = DB::table('Store')->where('id', $storeId)->first();
        $values = $this->credentials->manyStore(self::BRAND_KEYS, $storeId);
        $shopUrl = $this->httpUrl($values['STUDENT_DISCOUNT_SHOP_URL'] ?? null);
        $supportUrl = $this->supportUrl($values['STUDENT_DISCOUNT_SUPPORT_URL'] ?? null);

        return [
            'name' => trim($values['STUDENT_DISCOUNT_BRAND_NAME'] ?? '') ?: trim((string) ($store->name ?? 'Store')),
            'logoUrl' => $this->httpUrl($values['STUDENT_DISCOUNT_LOGO_URL'] ?? null),
            'shopUrl' => $shopUrl,
            'supportUrl' => $supportUrl,
            'instagramUrl' => $this->httpUrl($values['STUDENT_DISCOUNT_INSTAGRAM_URL'] ?? null),
            'facebookUrl' => $this->httpUrl($values['STUDENT_DISCOUNT_FACEBOOK_URL'] ?? null),
            'tiktokUrl' => $this->httpUrl($values['STUDENT_DISCOUNT_TIKTOK_URL'] ?? null),
            'youtubeUrl' => $this->httpUrl($values['STUDENT_DISCOUNT_YOUTUBE_URL'] ?? null),
        ];
    }

    private function httpUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }

    private function supportUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if (str_starts_with(strtolower($value), 'mailto:') && filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL)) {
            return $value;
        }

        return $this->httpUrl($value);
    }
}
