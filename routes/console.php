<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test {to : Address to send the test email to}', function (string $to) {
    $this->info('Mailer: '.config('mail.default').', host: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').', from: '.config('mail.from.address'));

    Mail::raw('Test wiadomości z Shama — jeśli to czytasz, wysyłka maili działa.', function ($message) use ($to) {
        $message->to($to)->subject('Shama — test wysyłki maili');
    });

    $this->info("Sent to {$to}.");
})->purpose('Send a test email to check the mail configuration');
