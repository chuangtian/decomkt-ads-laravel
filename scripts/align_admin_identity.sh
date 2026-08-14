#!/usr/bin/env bash
set -Eeuo pipefail

APP=/var/www/admin.decomkt.com/current
cd "$APP"

php artisan tinker --execute='
$legacy = [];
foreach (file("/root/deploy_admin/legacy-source.env", FILE_IGNORE_NEW_LINES) ?: [] as $legacyLine) {
    if (preg_match("/^([A-Z][A-Z0-9_]*)=(.*)$/", $legacyLine, $matches)) {
        $legacy[$matches[1]] = $matches[2];
    }
}
$login = trim((string) ($legacy["ADMIN_USERNAME"] ?? ""), " \\t\\n\\r\\0\\x0B\\\"");
$password = trim((string) ($legacy["ADMIN_PASSWORD"] ?? ""), " \\t\\n\\r\\0\\x0B\\\"");
$employee = DB::table("Employee")->where("username", $login)->orWhere("email", $login)->first();
$employee ??= DB::table("Employee")->where("username", "admin")->first();
$employee ??= DB::table("Employee")->where("status", "ACTIVE")->orderBy("id")->first();
if (! $employee) { throw new RuntimeException("Legacy administrator was not found."); }
$identityEmail = trim((string) ($employee->email ?: $employee->username));
if (! filter_var($identityEmail, FILTER_VALIDATE_EMAIL)) {
    $identityEmail = strtolower(preg_replace("/[^a-z0-9._-]+/i", "-", $employee->username))."@decomkt.local";
}
DB::table("Employee")->where("id", $employee->id)->update(["email" => strtolower($identityEmail), "isDefaultAdmin" => true, "updatedAt" => now()]);
$userUpdate = ["name" => $employee->name, "email" => strtolower($identityEmail), "updated_at" => now()];
if ($password !== "") { $userUpdate["password"] = \Illuminate\Support\Facades\Hash::make($password); }
DB::table("users")->where("id", DB::table("users")->min("id"))->update($userUpdate);
$path = base_path(".env");
$contents = file_get_contents($path);
$setEnv = function (string $key, string $value) use (&$contents): void {
    $line = $key."=".json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $contents = preg_match("/^".preg_quote($key, "/")."=/m", $contents)
        ? preg_replace("/^".preg_quote($key, "/")."=.*$/m", $line, $contents)
        : rtrim($contents).PHP_EOL.$line.PHP_EOL;
};
$setEnv("ADMIN_EMAIL", strtolower($identityEmail));
if ($password !== "") { $setEnv("ADMIN_PASSWORD", $password); }
$mapping = [
    "EMAIL_HOST" => "MAIL_HOST", "EMAIL_PORT" => "MAIL_PORT",
    "EMAIL_USER" => "MAIL_USERNAME", "EMAIL_PASS" => "MAIL_PASSWORD",
    "EMAIL_FROM" => "MAIL_FROM_ADDRESS", "SHOPIFY_APP_CLIENT_ID" => "SHOPIFY_APP_CLIENT_ID",
    "SHOPIFY_APP_SECRET" => "SHOPIFY_APP_SECRET", "SHOPIFY_APP_URL" => "SHOPIFY_APP_URL",
];
foreach ($mapping as $oldKey => $newKey) {
    $value = trim((string) ($legacy[$oldKey] ?? ""), " \\t\\n\\r\\0\\x0B\\\"");
    if ($value !== "") { $setEnv($newKey, $value); }
}
$secure = filter_var($legacy["EMAIL_SECURE"] ?? false, FILTER_VALIDATE_BOOL);
$setEnv("MAIL_MAILER", "smtp");
$setEnv("MAIL_SCHEME", $secure ? "smtps" : "smtp");
file_put_contents($path, $contents);
foreach (["MAIL_HOST" => "EMAIL_HOST", "MAIL_PORT" => "EMAIL_PORT", "MAIL_USERNAME" => "EMAIL_USER", "MAIL_PASSWORD" => "EMAIL_PASS"] as $key => $legacyKey) {
    $value = trim((string) ($legacy[$legacyKey] ?? ""), " \\t\\n\\r\\0\\x0B\\\"");
    if ($value !== "") {
        DB::table("SystemConfig")->updateOrInsert(["key" => $key], ["value" => \Illuminate\Support\Facades\Crypt::encryptString($value), "encrypted" => true, "updatedAt" => now()]);
    }
}
echo "administrator_aligned=1".PHP_EOL;
'

php artisan optimize:clear
php artisan optimize
chown -R www-data:www-data "$APP/storage" "$APP/bootstrap/cache"
chown root:www-data "$APP/.env"
chmod 640 "$APP/.env"
chmod -R ug+rwX "$APP/storage" "$APP/bootstrap/cache"
supervisorctl restart 'decomkt-admin-queue:*'

php artisan tinker --execute='
$user = DB::table("users")->first();
$employee = DB::table("Employee")->where("email", $user->email)->first();
echo "user_employee_match=".(int) (bool) $employee.PHP_EOL;
echo "default_admin=".(int) (bool) ($employee->isDefaultAdmin ?? false).PHP_EOL;
'
