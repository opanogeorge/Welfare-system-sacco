<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\RiskModel;
use App\Models\Setting;
use App\Services\CreditRiskService;
use Illuminate\Http\Request;
use RuntimeException;

/** AI credit scoring & loan risk prediction console. */
class RiskController extends Controller
{
    public function __construct(private CreditRiskService $risk) {}

    public function index(Request $request)
    {
        $model = $this->risk->activeModel();
        $history = RiskModel::latest()->take(8)->get();
        $lgd = (float) Setting::get('risk_lgd', 0.45);

        $running = collect();
        if ($model) {
            $running = Loan::with('member', 'product')->visibleTo($request->user(), $request->branch)->where('status', 'disbursed')->get()
                ->map(function ($l) use ($lgd) {
                    $p = $this->risk->predictRunning($l);
                    return ['loan' => $l, 'pd' => $p['pd'], 'band' => $p['band'], 'drivers' => $p['drivers'],
                            'ead' => $l->outstanding, 'el' => round($p['pd'] * $l->outstanding * $lgd, 2)];
                })->sortByDesc('pd')->values();
        }
        $bands = collect(CreditRiskService::BANDS)->mapWithKeys(fn ($b) => [$b[0] => [
            'count' => $running->where('band', $b[0])->count(), 'exposure' => $running->where('band', $b[0])->sum('ead'),
        ]]);
        $weights = $model ? collect($model->features['names'])->map(fn ($n, $i) => ['name' => CreditRiskService::FEATURES[$n] ?? $n, 'w' => $model->weights[$i]])->sortByDesc(fn ($x) => abs($x['w']))->values() : collect();

        return view('risk.index', compact('model', 'history', 'running', 'bands', 'weights', 'lgd'));
    }

    public function train(Request $request)
    {
        try {
            $m = $this->risk->train($request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }
        return back()->with('success', "Model {$m->version} trained on {$m->samples} loans ({$m->defaults} defaults). Validation AUC " . ($m->auc ?? 'n/a') . '.');
    }

    public function activate(RiskModel $model)
    {
        RiskModel::query()->update(['is_active' => false]);
        $model->update(['is_active' => true]);
        return back()->with('success', "Model {$model->version} is now active.");
    }

    public function rescore(Loan $loan)
    {
        try {
            $loan->forceFill(['risk_features' => $this->risk->snapshot($loan)])->saveQuietly();
            $r = $this->risk->scoreLoan($loan);
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }
        return back()->with($r ? 'success' : 'warning', $r ? 'Risk re-scored: PD ' . number_format($r['pd'] * 100, 1) . '%.' : 'No active model yet.');
    }
}
