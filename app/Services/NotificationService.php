<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Multi-channel member messaging: SMS (Advanta), email (Laravel mail) and WhatsApp
 * (Meta Cloud API - set WHATSAPP_TOKEN and WHATSAPP_PHONE_ID to enable).
 * Placeholders: {name} {first_name} {member_no} {deposits} {shares} {savings} {loan_balance}
 */
class NotificationService
{
    public function __construct(private SmsService $sms) {}

    public function toMember(Member $member, string $template, array $channels = ['sms'], ?string $subject = null, ?string $batch = null): void
    {
        $text = $this->render($template, $member);

        if (in_array('sms', $channels)) {
            $this->sms->send($member->phone, $text, $member->id, $batch);
        }
        if (in_array('email', $channels) && $member->email) {
            $this->email($member->email, $subject ?? config('sacco.name'), $text, $member->id, $batch);
        }
        if (in_array('whatsapp', $channels) && $member->phone) {
            $this->whatsapp($member->phone, $text, $member->id, $batch);
        }
    }

    /** @param iterable<Member> $members */
    public function bulk(iterable $members, string $template, array $channels, ?string $subject = null): array
    {
        $batch = 'B' . now()->format('ymdHis') . Str::upper(Str::random(3));
        $n = 0;
        foreach ($members as $m) {
            $this->toMember($m, $template, $channels, $subject, $batch);
            $n++;
        }
        return ['batch' => $batch, 'count' => $n];
    }

    public function email(string $to, string $subject, string $body, ?int $memberId = null, ?string $batch = null): void
    {
        $status = 'sent';
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            Log::warning("Email to $to failed: " . $e->getMessage());
            $status = 'failed';
        }
        MessageLog::create(['channel' => 'email', 'recipient' => $to, 'member_id' => $memberId, 'subject' => $subject,
            'message' => $body, 'status' => $status, 'batch' => $batch, 'sent_by' => auth()->id()]);
    }

    public function whatsapp(string $phone, string $body, ?int $memberId = null, ?string $batch = null): void
    {
        $phone = $this->sms->normalize($phone);
        $status = 'logged';
        if (config('sacco.whatsapp.token')) {
            try {
                $res = Http::withToken(config('sacco.whatsapp.token'))->timeout(15)
                    ->post('https://graph.facebook.com/v19.0/' . config('sacco.whatsapp.phone_id') . '/messages', [
                        'messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'text', 'text' => ['body' => $body],
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
            } catch (\Throwable $e) {
                $status = 'failed';
            }
        }
        MessageLog::create(['channel' => 'whatsapp', 'recipient' => $phone, 'member_id' => $memberId,
            'message' => $body, 'status' => $status, 'batch' => $batch, 'sent_by' => auth()->id()]);
    }

    public function render(string $template, Member $m): string
    {
        $map = [
            '{name}' => $m->full_name, '{first_name}' => $m->first_name, '{member_no}' => $m->member_no,
        ];
        foreach (['deposits' => 'DEP', 'shares' => 'SHR', 'savings' => 'SAV'] as $k => $code) {
            if (str_contains($template, '{' . $k . '}')) $map['{' . $k . '}'] = number_format($m->balance($code), 2);
        }
        if (str_contains($template, '{loan_balance}')) $map['{loan_balance}'] = number_format($m->outstandingLoans(), 2);
        return strtr($template, $map);
    }
}
