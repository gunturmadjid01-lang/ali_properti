<?php

use Illuminate\Contracts\Console\Kernel;
use Pusher\Pusher;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = config('broadcasting.connections.pusher');
$pusher = new Pusher($config['key'], $config['secret'], $config['app_id'], $config['options']);
$sent = $pusher->trigger('private-chat.global', 'connection.test', [
    'ok' => true,
    'at' => date(DATE_ATOM),
]);

fwrite(STDOUT, $sent ? "PUSHER_TRIGGER_OK\n" : "PUSHER_TRIGGER_FAILED\n");
exit($sent ? 0 : 1);
