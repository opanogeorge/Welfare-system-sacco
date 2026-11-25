<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\IdentityVerificationService;
use Illuminate\Http\Request;
use RuntimeException;

class IdentityController extends Controller
{
    public function __construct(private IdentityVerificationService $idv) {}

    /** Side-by-side review page: selfie vs ID photo, plus IPRS results. */
    public function show(Member $member)
    {
        $member->load('documents');
        $verifications = $member->hasMany(\App\Models\IdentityVerification::class)->with('verifier')->latest()->get();
        $selfie = $member->documents->firstWhere('type', 'selfie') ?? $member->documents->firstWhere('type', 'photo');
        $idFront = $member->documents->firstWhere('type', 'national_id') ?? $member->documents->firstWhere('type', 'passport');
        $idBack = $member->documents->firstWhere('type', 'national_id_back');
        return view('members.identity', ['member' => $member, 'verifications' => $verifications, 'selfie' => $selfie,
            'idFront' => $idFront, 'idBack' => $idBack, 'configured' => $this->idv->configured()]);
    }

    public function iprs(Member $member)
    {
        try {
            $v = $this->idv->verifyIprs($member);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with($v->result === 'verified' ? 'success' : 'warning', 'IPRS check: ' . $v->result . ' - ' . ($v->details['result_text'] ?? ''));
    }

    public function face(Request $request, Member $member)
    {
        $d = $request->validate(['match' => 'required|boolean', 'notes' => 'nullable|string|max:500']);
        $this->idv->recordFaceMatch($member, (bool) $d['match'], $d['notes'] ?? null);
        return back()->with('success', 'Face match recorded. Identity status: ' . $member->fresh()->identity_status . '.');
    }

    /** Staff capture of a live photo at the counter (webcam). */
    public function capture(Request $request, Member $member)
    {
        $request->validate(['image' => 'required|string']);
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $request->image, $m)) return back()->with('error', 'Invalid image.');
        $path = "members/{$member->id}/selfie-" . now()->format('YmdHis') . '.jpg';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, base64_decode($m[1]));
        $member->documents()->create(['type' => 'selfie', 'title' => 'Live photo (counter)', 'path' => $path, 'uploaded_by' => auth()->id()]);
        return back()->with('success', 'Live photo captured.');
    }
}
