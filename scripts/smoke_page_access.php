<?php

use App\Http\Middleware\EnsurePageAccess;

require __DIR__.'/../app/Http/Middleware/EnsurePageAccess.php';

$handle = new ReflectionMethod(EnsurePageAccess::class, 'handle');

if ($handle->getNumberOfRequiredParameters() !== 2) {
    fwrite(STDERR, 'EnsurePageAccess::handle must accept only Request and Closure as required parameters.'.PHP_EOL);
    exit(1);
}

echo "PAGE_ACCESS_SIGNATURE=ok\n";
