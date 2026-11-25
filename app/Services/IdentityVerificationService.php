<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\IdentityVerification;
use App\Models\Member;
use App\Support\Integrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Identity verification.
 *  1. IPRS data match via Smile ID Enhanced KYC (ID number + names + DOB checked against the
 *     national registry). Keys from Admin > Integrations > Identity.
 *  2. Face match: the applicant's live selfie is compared with the ID photo by a staff member
 *     (side-by-side review). Automated face matching needs Smile ID Biometric KYC via their SDK.
 * Confirm request fields against the Smile ID documentation for your account before going live.
 */
class IdentityVerificationService
{
    public function configured(): bool
    {
        return (bool) (Integrations::get('smileid_partner_id') && Integrations::get('smileid_api_key'));
    }

    public function verifyIprs(Member $member): IdentityVerification
    {
        if (! $this->configured()) throw new RuntimeException('Smile ID keys are not set (Admin > Integrations > Identity).');

        $partner = Integrations::get('smileid_partner_id');
        $ts = now()->toIso8601String();
        $signature = base64_encode(hash_hmac('sha256', $ts . $partner . 'sid_request', Integrations::get('smileid_api_key'), true));
        $base = Integrations::get('smileid_env') === 'production' ? 'https://api.smileidentity.com' : 'https://testapi.smileidentity.com';

        try {
            $res = Http::timeout(40)->post("$base/v1/id_verification", [
                'source_sdk' => 'rest_api', 'source_sdk_version' => '1.0.0',
                'partner_id' => $partner, 'timestamp' => $ts, 'signature' => $signature,
                'country' => 'KE', 'id_type' => 'NATIONAL_ID', 'id_number' => $member->national_id,
                'first_name' => $member->first_name, 'middle_name' => $member->middle_name, 'last_name' => $member->last_name,
                'dob' => $member->date_of_birth?->format('Y-m-d'), 'phone_number' => $member->phone,
                'partner_params' => ['job_id' => 'SACCO-' . $member->id . '-' . Str::random(6), 'user_id' => $member->member_no, 'job_type' => 5],
            ]);
            $body = $res->json() ?? ['raw' => $res->body()];
        } catch (\Throwable $e) {
            $body = ['error' => $e->getMessage()];
        }

        $actions = $body['Actions'] ?? [];
        $idOk = ($actions['Verify_ID_Number'] ?? '') === 'Verified';
        $names = $actions['Names'] ?? null;
        $result = match (true) {
            $idOk && $names === 'Exact Match' => 'verified',
            $idOk && in_array($names, ['Partial Match', 'Transposed'], true) => 'partial',
            isset($body['error']) || ! isset($body['ResultCode']) => 'pending',
            default => 'failed',
        };

        $v = IdentityVerification::create([
            'member_id' => $member->id, 'provider' => 'smileid', 'type' => 'iprs_match', 'result' => $result,
            'details' => ['result_code' => $body['ResultCode'] ?? null, 'result_text' => $body['ResultText'] ?? ($body['error'] ?? null),
                          'actions' => $actions, 'registry_name' => $body['FullName'] ?? null, 'registry_dob' => $body['DOB'] ?? null],
            'verified_by' => auth()->id(),
        ]);
        $this->updateStatus($member);
        AuditLog::record('identity_iprs', $member, ['result' => $result]);
        return $v;
    }

    /** Staff side-by-side review of live selfie vs ID photo. */
    public function recordFaceMatch(Member $member, bool $match, ?string $notes): IdentityVerification
    {
        $v = IdentityVerification::create([
            'member_id' => $member->id, 'provider' => 'manual', 'type' => 'face_match',
            'result' => $match ? 'verified' : 'failed', 'details' => ['notes' => $notes], 'verified_by' => auth()->id(),
        ]);
        $this->updateStatus($member);
        AuditLog::record('identity_face', $member, ['match' => $match]);
        return $v;
    }

    /** Verified = latest ID check AND latest face check both passed. */
    public function updateStatus(Member $member): void
    {
        $latest = fn ($type) => IdentityVerification::where('member_id', $member->id)->where('type', $type)->latest('id')->value('result');
        $id = $latest('iprs_match') ?? $latest('document');
        $face = $latest('face_match');
        $status = ($id === 'failed' || $face === 'failed') ? 'failed' : (($id === 'verified' && $face === 'verified') ? 'verified' : 'unverified');
        $member->forceFill(['identity_status' => $status])->saveQuietly();
    }
}
