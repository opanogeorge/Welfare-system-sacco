<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * SMS / email / M-Pesa / WhatsApp credentials managed from Admin > Integrations.
 * Values saved there override .env; secrets are stored encrypted (APP_KEY).
 */
class Integrations
{
    /** key => [label, group, secret?] */
    public const FIELDS = [
        // SMS - Advanta
        'sms_enabled'          => ['Send SMS (off = write to log only)', 'sms', false],
        'advanta_url'          => ['API URL', 'sms', false],
        'advanta_api_key'      => ['API key', 'sms', true],
        'advanta_partner_id'   => ['Partner ID', 'sms', false],
        'advanta_shortcode'    => ['Sender ID / shortcode', 'sms', false],
        // Email - SMTP
        'mail_mailer'          => ['Mailer', 'mail', false],
        'mail_host'            => ['SMTP host', 'mail', false],
        'mail_port'            => ['SMTP port', 'mail', false],
        'mail_encryption'      => ['Encryption', 'mail', false],
        'mail_username'        => ['Username', 'mail', false],
        'mail_password'        => ['Password / app password', 'mail', true],
        'mail_from_address'    => ['From address', 'mail', false],
        'mail_from_name'       => ['From name', 'mail', false],
        // M-Pesa - Daraja
        'mpesa_env'            => ['Environment', 'mpesa', false],
        'mpesa_paybill'        => ['Paybill / till (C2B shortcode)', 'mpesa', false],
        'mpesa_stk_shortcode'  => ['STK Push shortcode', 'mpesa', false],
        'mpesa_consumer_key'   => ['Consumer key', 'mpesa', true],
        'mpesa_consumer_secret'=> ['Consumer secret', 'mpesa', true],
        'mpesa_passkey'        => ['Lipa na M-Pesa passkey', 'mpesa', true],
        'mpesa_callback_token' => ['Callback security token', 'mpesa', true],
        'public_url'           => ['Public HTTPS base URL', 'mpesa', false],
        // Credit reference bureau - Metropol
        'metropol_public_key'      => ['Public API key', 'crb', true],
        'metropol_private_key'     => ['Private key', 'crb', true],
        'metropol_base_url'        => ['API base URL', 'crb', false],
        'metropol_path_score'      => ['Path: credit score', 'crb', false],
        'metropol_path_delinquency'=> ['Path: delinquency status', 'crb', false],
        'metropol_path_identity'   => ['Path: identity verification', 'crb', false],
        'metropol_path_report'     => ['Path: full report (JSON)', 'crb', false],
        // Identity verification - Smile ID
        'smileid_env'              => ['Environment', 'identity', false],
        'smileid_partner_id'       => ['Partner ID', 'identity', false],
        'smileid_api_key'          => ['API key', 'identity', true],
        // WhatsApp - Meta Cloud API
        'whatsapp_token'       => ['Access token', 'whatsapp', true],
        'whatsapp_phone_id'    => ['Phone number ID', 'whatsapp', false],
    ];

    public static function isSecret(string $key): bool
    {
        return (bool) (self::FIELDS[$key][2] ?? false);
    }

    /** Decrypted stored value, or null if not set in the admin panel. */
    public static function get(string $key): ?string
    {
        $v = Setting::get("int_$key");
        if ($v === null || $v === '') return null;
        if (! self::isSecret($key)) return $v;
        try { return Crypt::decryptString($v); } catch (\Throwable) { return null; }
    }

    public static function put(string $key, ?string $value): void
    {
        $value = $value === null ? null : trim($value);
        Setting::put("int_$key", ($value !== null && $value !== '' && self::isSecret($key)) ? Crypt::encryptString($value) : $value);
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /** Push admin-panel values into runtime config (called on every request from AppServiceProvider). */
    public static function apply(): void
    {
        $map = [
            'advanta_url' => 'sacco.sms.url', 'advanta_api_key' => 'sacco.sms.api_key',
            'advanta_partner_id' => 'sacco.sms.partner_id', 'advanta_shortcode' => 'sacco.sms.shortcode',
            'mpesa_env' => 'sacco.mpesa.env', 'mpesa_paybill' => 'sacco.mpesa.paybill', 'mpesa_stk_shortcode' => 'sacco.mpesa.stk_shortcode',
            'mpesa_consumer_key' => 'sacco.mpesa.consumer_key', 'mpesa_consumer_secret' => 'sacco.mpesa.consumer_secret',
            'mpesa_passkey' => 'sacco.mpesa.passkey', 'mpesa_callback_token' => 'sacco.mpesa.callback_token',
            'public_url' => 'sacco.public_url',
            'whatsapp_token' => 'sacco.whatsapp.token', 'whatsapp_phone_id' => 'sacco.whatsapp.phone_id',
            'mail_host' => 'mail.mailers.smtp.host', 'mail_port' => 'mail.mailers.smtp.port',
            'mail_username' => 'mail.mailers.smtp.username', 'mail_password' => 'mail.mailers.smtp.password',
            'mail_from_address' => 'mail.from.address', 'mail_from_name' => 'mail.from.name',
        ];
        $set = [];
        foreach ($map as $key => $configKey) {
            if (($v = self::get($key)) !== null) $set[$configKey] = $v;
        }
        if (($v = self::get('sms_enabled')) !== null) $set['sacco.sms.enabled'] = $v === '1';
        if (($v = self::get('mail_mailer')) !== null) $set['mail.default'] = $v;
        if (($v = self::get('mail_encryption')) !== null) {
            $enc = $v === 'none' ? null : $v;
            $set['mail.mailers.smtp.encryption'] = $enc;                    // Laravel 10
            $set['mail.mailers.smtp.scheme'] = $enc === 'ssl' ? 'smtps' : 'smtp'; // Laravel 11+
        }
        if ($set) config($set);
    }

    /** Base URL Safaricom can reach (ngrok / live domain), falling back to APP_URL. */
    public static function publicUrl(): string
    {
        return rtrim(config('sacco.public_url') ?: config('app.url'), '/');
    }

    public static function callbackUrls(): array
    {
        $q = '?token=' . config('sacco.mpesa.callback_token');
        $b = self::publicUrl() . '/api/payments';
        return [
            'validation'   => "$b/c2b/validation$q",
            'confirmation' => "$b/c2b/confirmation$q",
            'stk'          => "$b/stk/callback$q",
        ];
    }
}
