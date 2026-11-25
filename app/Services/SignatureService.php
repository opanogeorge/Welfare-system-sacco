<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\Member;
use App\Models\Signature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Electronic signatures with an audit trail: the drawn signature, who signed, when, from which
 * IP/device, and a SHA-256 hash of the exact terms they signed. Anyone can check a signed
 * document at /verify/{code}. This is an electronic signature under the Kenya Information and
 * Communications Act (as amended by the Business Laws (Amendment) Act, 2020); an *advanced*
 * electronic signature additionally needs a licensed e-certification service provider.
 */
class SignatureService
{
    public function sign(Model $doc, string $purpose, string $dataUrl, string $signerName, string $role, ?Member $member = null, bool $otpVerified = false): Signature
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) throw new RuntimeException('Please draw your signature.');
        $png = base64_decode($m[1]);
        if (strlen($png) < 100) throw new RuntimeException('The signature image is invalid. Please sign again.');
        if (strlen($png) > 600_000) throw new RuntimeException('Signature image too large.');

        $code = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        $path = "signatures/" . now()->format('Y/m') . "/$code.png";
        Storage::disk('local')->put($path, $png);

        $sig = Signature::create([
            'signable_type' => $doc::class, 'signable_id' => $doc->getKey(), 'purpose' => $purpose,
            'member_id' => $member?->id, 'user_id' => auth()->id(), 'signer_name' => $signerName, 'signer_role' => $role,
            'image_path' => $path, 'document_hash' => $this->hash($doc, $purpose), 'verification_code' => $code,
            'ip' => request()?->ip(), 'user_agent' => substr((string) request()?->userAgent(), 0, 250),
            'otp_verified' => $otpVerified, 'signed_at' => now(),
        ]);
        AuditLog::record('e_signature', $doc, ['purpose' => $purpose, 'code' => $code, 'signer' => $signerName]);
        return $sig;
    }

    /** Canonical content of what was signed - if any of it changes later, verification shows "altered". */
    public function document(Model $doc, string $purpose): array
    {
        return match (true) {
            $doc instanceof Loan => [
                'purpose' => $purpose, 'loan_no' => $doc->loan_no, 'member_no' => $doc->member->member_no, 'national_id' => $doc->member->national_id,
                'product' => $doc->product->name, 'amount' => (string) ($doc->amount_approved ?? $doc->amount_applied),
                'rate' => (string) $doc->interest_rate, 'method' => $doc->interest_method, 'period' => $doc->period,
                'processing_fee_rate' => (string) $doc->product->processing_fee_rate, 'insurance_rate' => (string) $doc->product->insurance_rate,
                'penalty_rate' => (string) $doc->product->penalty_rate, 'terms_version' => setting('loan_terms_version', '1'),
            ],
            $doc instanceof LoanGuarantor => [
                'purpose' => $purpose, 'loan_no' => $doc->loan->loan_no, 'borrower' => $doc->loan->member->member_no,
                'guarantor' => $doc->member->member_no, 'amount' => (string) $doc->amount, 'loan_amount' => (string) $doc->loan->amount_applied,
            ],
            $doc instanceof Member => [
                'purpose' => $purpose, 'member_no' => $doc->member_no, 'national_id' => $doc->national_id,
                'name' => $doc->full_name, 'phone' => $doc->phone, 'bylaws_version' => setting('bylaws_version', '1'),
            ],
            default => ['purpose' => $purpose, 'type' => $doc::class, 'id' => $doc->getKey()],
        };
    }

    public function hash(Model $doc, string $purpose): string
    {
        $d = $this->document($doc, $purpose);
        ksort($d);
        return hash('sha256', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{signature: Signature, intact: bool} */
    public function verify(string $code): ?array
    {
        $sig = Signature::where('verification_code', strtoupper(trim($code)))->first();
        if (! $sig || ! $sig->signable) return $sig ? ['signature' => $sig, 'intact' => false] : null;
        return ['signature' => $sig, 'intact' => hash_equals($sig->document_hash, $this->hash($sig->signable, $sig->purpose))];
    }

    public function imageDataUri(Signature $s): ?string
    {
        return Storage::disk('local')->exists($s->image_path) ? 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get($s->image_path)) : null;
    }
}
