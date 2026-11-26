<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('push:vapid {--env-lines : Print only the .env lines (for scripts)}')]
#[Description('Generate the VAPID key pair for Web Push notifications (put them in .env)')]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $url = (string) config('app.url');
        $subject = str_starts_with($url, 'https://') ? $url : 'mailto:admin@example.com';

        if (! $this->option('env-lines')) {
            $this->line('Add to .env (each environment needs its own pair; changing it later means re-enabling notifications on every phone):');
            $this->newLine();
        }

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT='.$subject);

        return self::SUCCESS;
    }
}
