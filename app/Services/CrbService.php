<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CrbCheck;
use App\Models\Loan;
use App\Models\Member;
use App\Support\Integrations;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Credit Reference Bureau checks.
 *  - Metropol CRB REST API (credentials from Admin > Integrations > CRB).
 *    Requests are signed: SHA-256(private_key + json + public_key + timestamp) in X-METROPOL-REST-API-HASH.
 *    Endpoint paths differ between Metropol API versions, so they are configurable; confirm them
 *    against the API document Metropol sends you with your keys.
 *  - Manual: record a result (score, listing, PDF report) from any bureau's web portal.
 * A CRB check needs the member's consent (Credit Reference Bureau Regulations, 2020).
 */
class CrbService
{
    public const REASONS = ['new_credit_app' => 1, 'credit_review' => 2, 'verify_credit_info' => 3, 'customer_request' => 4];

    public function configured(): bool
    {
        return (bool) (Integrations::get('metropol_public_key') && Integrations::get('metropol_private_key'));
    }

    public function check(Member $member, string $type, ?Loan $loan = null, bool $consent = false): CrbCheck
    {
        if (! $consent && ! $member->crb_consent) throw new RuntimeException('Record the member\'s consent to a CRB check first.');
        if (! $this->configured()) throw new RuntimeException('Metropol API keys are not set (Admin > Integrations > CRB). Use "Record manual result" instead.');

        $path = match ($type) {
            'identity'    => Integrations::get('metropol_path_identity') ?: '/identity/verify',
            'delinquency' => Integrations::get('metropol_path_delinquency') ?: '/deliquency/status',
            'report'      => Integrations::get('metropol_path_report') ?: '/report/json',
            default       => Integrations::get('metropol_path_score') ?: '/score/consumer',
        };
        $payload = [
            'report_type'     => match ($type) { 'identity' => 1, 'delinquency' => 2, 'score' => 3, default => 4 },
            'identity_number' => $member->national_id,
            'identity_type'   => '001',            // 001 = National ID
            'loan_amount'     => (int) ($loan?->amount_applied ?? 0),
            'report_reason'   => self::REASONS['new_credit_app'],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $ts = now()->format('YmdHisu');
        $hash = hash('sha256', Integrations::get('metropol_private_key') . $json . Integrations::get('metropol_public_key') . $ts);
        $base = rtrim(Integrations::get('metropol_base_url') ?: 'https://api.metropol.co.ke:5555/v2_1', '/');

        try {
            $res = Http::withHeaders([
                'X-METROPOL-REST-API-KEY'       => Integrations::get('metropol_public_key'),
                'X-METROPOL-REST-API-HASH'      => $hash,
                'X-METROPOL-REST-API-TIMESTAMP' => $ts,
                'Content-Type'                  => 'application/json',
            ])->timeout(30)->withBody($json, 'application/json')->post($base . $path);
            $body = $res->json() ?? ['raw' => $res->body()];
            $ok = $res->successful() && ($body['has_error'] ?? false) !== true;
        } catch (\Throwable $e) {
            $body = ['error' => $e->getMessage()]; $ok = false;
        }

        $score = $body['credit_score'] ?? $body['score'] ?? null;
        $listed = isset($body['is_listed']) ? (bool) $body['is_listed']
            : (isset($body['delinquency_code']) ? ! in_array((string) $body['delinquency_code'], ['001', '002'], true) : null);

        $check = CrbCheck::create([
            'member_id' => $member->id, 'loan_id' => $loan?->id, 'provider' => 'metropol', 'check_type' => $type,
            'score' => is_numeric($score) ? (int) $score : null, 'is_listed' => $listed,
            'summary' => $ok ? ($body['delinquency_description'] ?? $body['api_message'] ?? 'Check completed') : ('Failed: ' . ($body['api_message'] ?? $body['error'] ?? 'HTTP error')),
            'response' => $body, 'consent_obtained' => true, 'status' => $ok ? 'success' : 'failed', 'checked_by' => auth()->id(),
        ]);
        if ($consent && ! $member->crb_consent) $member->forceFill(['crb_consent' => true])->saveQuietly();
        AuditLog::record('crb_check', $member, ['type' => $type, 'status' => $check->status]);
        return $check;
    }

    public function recordManual(Member $member, array $data, ?Loan $loan = null, $file = null): CrbCheck
    {
        $path = $file ? $file->store("crb/{$member->id}", 'local') : null;
        $check = CrbCheck::create([
            'member_id' => $member->id, 'loan_id' => $loan?->id, 'provider' => $data['provider'], 'check_type' => 'report',
            'score' => $data['score'] ?? null, 'grade' => $data['grade'] ?? null, 'is_listed' => (bool) ($data['is_listed'] ?? false),
            'summary' => $data['summary'] ?? null, 'report_path' => $path, 'consent_obtained' => true, 'checked_by' => auth()->id(),
        ]);
        $member->forceFill(['crb_consent' => true])->saveQuietly();
        AuditLog::record('crb_check_manual', $member, ['provider' => $data['provider']]);
        return $check;
    }

    public function latest(Member $member, int $days = 30): ?CrbCheck
    {
        return CrbCheck::where('member_id', $member->id)->where('status', 'success')->where('created_at', '>=', now()->subDays($days))->latest()->first();
    }

    /**
     * Credit information sharing file (all SACCO loans) for submission to the bureaus.
     * Column layout follows the common CIS Kenya consumer fields; map to your bureau's exact template if it differs.
     */
    public function sharingRows(): \Generator
    {
        foreach (Loan::with('member', 'product')->whereIn('status', ['disbursed', 'closed', 'written_off'])->cursor() as $l) {
            $m = $l->member;
            $days = $l->status === 'disbursed' ? $l->daysInArrears() : 0;
            yield [
                'Surname' => $m->last_name, 'Forename 1' => $m->first_name, 'Forename 2' => $m->middle_name,
                'Date of Birth' => $m->date_of_birth?->format('Ymd'), 'Gender' => strtoupper(substr((string) $m->gender, 0, 1)),
                'Primary Identification Document Type' => '001', 'Primary Identification Doc Number' => $m->national_id, 'PIN Number' => $m->kra_pin,
                'Mobile Telephone Number' => $m->phone, 'Email Address' => $m->email, 'Employer Name' => $m->employer,
                'Account Number' => $l->loan_no, 'Account Product Type' => $l->product->code, 'Date Account Opened' => $l->disbursed_on?->format('Ymd'),
                'Installment Due Date' => $l->schedules()->whereIn('status', ['pending', 'partial', 'overdue'])->min('due_date'),
                'Original Amount' => number_format((float) $l->amount_approved, 2, '.', ''), 'Currency' => 'KES',
                'Current Balance' => number_format($l->status === 'disbursed' ? $l->outstanding : 0, 2, '.', ''),
                'Overdue Balance' => number_format($l->status === 'disbursed' ? $l->arrears() : 0, 2, '.', ''),
                'Number of Days in Arrears' => $days, 'Prudential Risk Classification' => $l->status === 'disbursed' ? $l->classification() : 'Closed',
                'Account Status' => match ($l->status) { 'closed' => 'Closed', 'written_off' => 'Written off', default => $days > 90 ? 'Non-performing' : 'Active' },
                'Account Status Date' => ($l->closed_on ?? today())->format('Ymd'),
                'Repayment Period' => $l->period, 'Installment Amount' => number_format((float) $l->schedules()->value('total_due'), 2, '.', ''),
            ];
        }
    }
}
