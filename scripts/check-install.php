<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$temporaryRoot = sys_get_temp_dir().'/ridedock-install-'.bin2hex(random_bytes(12));
$files = new Filesystem();
$files->makeDirectory($temporaryRoot.'/views', 0700, true);

try {
    $files->copy($projectRoot.'/.env.example', $temporaryRoot.'/.env');
    $files->put($temporaryRoot.'/database.sqlite', '');
    // Every child boots from the temporary environment, never the local .env.
    $files->put($temporaryRoot.'/console.php', <<<'PHP'
<?php
try {
    require $argv[1].'/vendor/autoload.php';
    $app = require $argv[1].'/bootstrap/app.php';
    $app->useEnvironmentPath($argv[2]);
    if ($argv[3] === 'check-http') {
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->bootstrap();
        $app->make(\Illuminate\Foundation\Vite::class)->useHotFile($argv[2].'/hot');
        $request = \Illuminate\Http\Request::create('http://localhost/login', 'GET');
        $response = $kernel->handle($request);
        if ($response->getStatusCode() !== 200 || ! str_contains($response->getContent(), '/build/assets/')) {
            throw new \RuntimeException('Fresh cached installation did not render the login page with production assets (HTTP '.$response->getStatusCode().').');
        }
        $kernel->terminate($request, $response);
        echo "Fresh cached installation: HTTP 200 with production assets.\n";
        if (\Illuminate\Support\Facades\Route::has('register') || \Illuminate\Support\Facades\Route::has('register.store')) {
            throw new \RuntimeException('Cached installation still contains registration routes.');
        }
        foreach ([['GET', '/register', 404], ['POST', '/register', 404], ['GET', '/admin/users', 302], ['GET', '/invitations/'.str_repeat('a', 64), 404]] as [$method, $path, $expected]) {
            $request = \Illuminate\Http\Request::create('http://localhost'.$path, $method);
            $response = $kernel->handle($request);
            if ($response->getStatusCode() !== $expected) {
                throw new \RuntimeException('Fresh cached installation failed '.$method.' '.$path.' (HTTP '.$response->getStatusCode().').');
            }
            $kernel->terminate($request, $response);
        }
        echo "Fresh cached installation: registration absent; administration and invitations protected.\n";
        exit(0);
    }
    exit($app->handleCommand(new \Symfony\Component\Console\Input\ArgvInput(array_merge(['artisan'], array_slice($argv, 3)))));
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP);

    $environment = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'http://localhost',
        'APP_CONFIG_CACHE' => $temporaryRoot.'/config.php',
        'APP_ROUTES_CACHE' => $temporaryRoot.'/routes.php',
        'APP_EVENTS_CACHE' => $temporaryRoot.'/events.php',
        'APP_PACKAGES_CACHE' => $temporaryRoot.'/packages.php',
        'APP_SERVICES_CACHE' => $temporaryRoot.'/services.php',
        'VIEW_COMPILED_PATH' => $temporaryRoot.'/views',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $temporaryRoot.'/database.sqlite',
        'DB_URL' => '',
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        'QUEUE_CONNECTION' => 'database',
        'AI_QUEUE_CONNECTION' => 'ai',
        'LOG_CHANNEL' => 'stderr',
        'OPENAI_API_KEY' => '',
        'OPENAI_MODEL' => '',
        'HAMMERHEAD_ENABLED' => 'false',
        'HAMMERHEAD_CLIENT_ID' => '',
        'HAMMERHEAD_CLIENT_SECRET' => '',
        'HAMMERHEAD_WEBHOOK_SECRET' => '',
        'WAHOO_ENABLED' => 'false',
        'WAHOO_CLIENT_ID' => '',
        'WAHOO_CLIENT_SECRET' => '',
        'WAHOO_WEBHOOK_TOKEN' => '',
    ];
    $commands = [
        ['migrate', '--force', '--no-interaction'],
        ['config:cache', '--no-interaction'],
        ['route:cache', '--no-interaction'],
        ['view:cache', '--no-interaction'],
        ['check-http'],
    ];
    foreach ($commands as $command) {
        $process = new Process([PHP_BINARY, $temporaryRoot.'/console.php', $projectRoot, $temporaryRoot, ...$command], $projectRoot, $environment);
        $process->setTimeout(120);
        $process->mustRun(static function (string $type, string $output): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $output);
        });
    }
} finally {
    $files->deleteDirectory($temporaryRoot);
}
