<?php

// Local preview of the bundle: php -S 127.0.0.1:8000 -t tests/app/public tests/app/public/index.php

use RomainGenin\AstraCheckoutBundle\Tests\Functional\TestKernel;
use Symfony\Component\HttpFoundation\Request;

if (\PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$kernel = new TestKernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
