#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

$autoload = '/var/www/html/legacy/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload not found.\n");
    exit(2);
}
require $autoload;

$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
$subject = trim((string) (getenv('VAPID_SUBJECT') ?: getenv('BASE_URL') ?: ''));

fwrite(STDOUT, "# Copy these values to deployment .env. Keep the private key secret.\n");
fwrite(STDOUT, "HAYNE_PUSH_ENABLED=TRUE\n");
if ($subject !== '') {
    fwrite(STDOUT, 'VAPID_SUBJECT=' . rtrim($subject, '/') . "\n");
}
fwrite(STDOUT, 'VAPID_PUBLIC_KEY=' . $keys['publicKey'] . "\n");
fwrite(STDOUT, 'VAPID_PRIVATE_KEY=' . $keys['privateKey'] . "\n");
