<?php

namespace App\Services;

use App\Models\MessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Advanta SMS (quicksms.advantasms.com). Set SMS_ENABLED=true and the ADVANTA_* keys in .env.
 * With SMS disabled, messages are written to the log so you can test locally.
 * Every message is recorded in message_logs.
 */
class SmsService
{
    public function send(?string $phone, string $message, ?int $memberId = null, ?string $batch = null): bool
    {
        if (! $phone) return false;
        $phone = $this->normalize($phone);
        $status = 'logged';

        if (! config('sacco.sms.enabled')) {
            Log::info("[SMS disabled] to $phone: $message");
        } else {
            try {
                $res = Http::timeout(15)->post(config('sacco.sms.url'), [
                    'apikey'    => config('sacco.sms.api_key'),
                    'partnerID' => config('sacco.sms.partner_id'),
                    'shortcode' => config('sacco.sms.shortcode'),
                    'mobile'    => $phone,
                    'message'   => $message,
                ]);
                $status = $res->successful() ? 'sent' : 'failed';
            } catch (\Throwable $e) {
                Log::warning('SMS failed: ' . $e->getMessage());
                $status = 'failed';
            }
        }

        try {
            MessageLog::create([
                'channel' => 'sms', 'recipient' => $phone, 'member_id' => $memberId, 'message' => $message,
                'status' => $status, 'batch' => $batch, 'sent_by' => auth()->id(),
            ]);
        } catch (\Throwable) {
            // never let logging break a financial posting
        }
        return $status !== 'failed';
    }

    public function normalize(string $phone): string
    {
        $p = preg_replace('/\D/', '', $phone);
        if (str_starts_with($p, '0')) $p = '254' . substr($p, 1);
        if (strlen($p) === 9) $p = '254' . $p;
        return $p;
    }
}
