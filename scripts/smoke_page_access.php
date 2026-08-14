<?php

use App\Http\Middleware\EnsurePageAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $email = DB::table('Employee')->where('isDefaultAdmin', true)->value('email');
    $user = App\Models\User::query()->where('email', $email)->firstOrFail();
    $request = Request::create('/', 'GET');
    $request->setUserResolver(static fn () => $user);
    $session = $app->make('session')->driver();
    $session->start();
    $request->setLaravelSession($session);

    $app->forgetScopedInstances();
    $app->instance('request', $request);

    $response = $app->make(EnsurePageAccess::class)->handle(
        $request,
        static fn () => response('page-access-ok'),
    );

    if ($response->getContent() !== 'page-access-ok') {
        throw new RuntimeException('Page access middleware smoke test failed.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

echo "PAGE_ACCESS_SMOKE=ok\n";
