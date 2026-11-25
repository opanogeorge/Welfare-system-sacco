<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\Member;
use App\Models\RiskModel;
use App\Models\Setting;
use App\Models\Transaction;
use RuntimeException;

/**
 * Machine-learning credit scoring and loan risk prediction.
 *
 * A logistic-regression model is trained on the SACCO's own loan history (features captured at
 * application time; label = the loan went 90+ days in arrears or was written off). It returns a
 * probability of default (PD) for new applications and for running loans, plus the factors that
 * drove the prediction, so credit officers can see WHY - not a black box.
 *
 * Until there is enough history (min samples / defaults), the rule-based score is used instead.
 */
class CreditRiskService
{
    public const FEATURES = [
        'tenure_months'      => 'Months of membership',
        'saving_months'      => 'Months with deposits (last 12)',
        'log_deposits'       => 'Deposits (log)',
        'loan_to_deposits'   => 'Loan ÷ deposits',
        'debt_to_deposits'   => 'Existing debt ÷ deposits',
        'installment_ratio'  => 'Installment ÷ monthly contribution',
        'past_max_arrears'   => 'Worst past arrears (days)',
        'loans_cleared'      => 'Loans cleared before',
        'period'             => 'Repayment period (months)',
        'age'                => 'Age (years)',
        'guarantor_cover'    => 'Guarantor cover ÷ loan',
        'is_top_up'          => 'Top-up loan',
    ];

    public const BANDS = [['A', 0.03], ['B', 0.07], ['C', 0.15], ['D', 0.30], ['E', 1.01]];

    /** Feature snapshot for a member applying for $amount over $period months. */
    public function features(Member $m, float $amount, int $period, float $installment = 0, float $guaranteed = 0, bool $topUp = false): array
    {
        $deposits = max(1, $m->loanableDeposits());
        $months = Transaction::where('member_id', $m->id)->where('type', 'deposit')
            ->whereDate('txn_date', '>=', today()->subMonths(12))->pluck('txn_date')
            ->map(fn ($d) => $d->format('Y-m'))->unique()->count();
        $loans = $m->loans()->whereIn('status', ['disbursed', 'closed', 'written_off'])->get();

        return [
            'tenure_months'     => (float) $m->monthsOfMembership(),
            'saving_months'     => (float) $months,
            'log_deposits'      => log10($deposits),
            'loan_to_deposits'  => $amount / $deposits,
            'debt_to_deposits'  => $m->outstandingLoans() / $deposits,
            'installment_ratio' => $installment > 0 ? $installment / max(1, (float) $m->monthly_contribution ?: 1000) : 0,
            'past_max_arrears'  => (float) min(365, $loans->max('max_days_arrears') ?? 0),
            'loans_cleared'     => (float) $loans->where('status', 'closed')->count(),
            'period'            => (float) $period,
            'age'               => (float) ($m->date_of_birth ? $m->date_of_birth->age : 35),
            'guarantor_cover'   => $amount > 0 ? min(2, $guaranteed / $amount) : 0,
            'is_top_up'         => $topUp ? 1.0 : 0.0,
        ];
    }

    public function snapshot(Loan $loan): array
    {
        $calc = LoanCalculator::schedule((float) ($loan->amount_approved ?? $loan->amount_applied), (float) $loan->interest_rate, $loan->period, $loan->interest_method);
        return $this->features($loan->member, (float) ($loan->amount_approved ?? $loan->amount_applied), $loan->period,
            $calc['installment'], $loan->guaranteedAmount(), (bool) $loan->top_up_of);
    }

    public function activeModel(): ?RiskModel
    {
        return RiskModel::where('is_active', true)->latest('id')->first();
    }

    /**
     * @return array{pd: float, band: string, score: int, recommendation: string, drivers: array, model: ?RiskModel}
     */
    public function predict(array $features): array
    {
        $model = $this->activeModel();
        if (! $model) {
            throw new RuntimeException('No trained risk model yet.');
        }
        $names = $model->features['names'];
        $z = 0.0; $contrib = [];
        foreach ($names as $i => $name) {
            $x = (($features[$name] ?? 0) - $model->features['means'][$i]) / ($model->features['stds'][$i] ?: 1);
            $c = $model->weights[$i] * $x;
            $contrib[$name] = $c;
            $z += $c;
        }
        $pd = 1 / (1 + exp(-($z + (float) $model->intercept)));
        arsort($contrib);
        $drivers = [];
        foreach (array_slice($contrib, 0, 3, true) as $n => $c) if ($c > 0.05) $drivers[] = ['factor' => self::FEATURES[$n] ?? $n, 'effect' => 'raises risk', 'value' => round($features[$n] ?? 0, 2)];
        foreach (array_slice(array_reverse($contrib, true), 0, 3, true) as $n => $c) if ($c < -0.05) $drivers[] = ['factor' => self::FEATURES[$n] ?? $n, 'effect' => 'lowers risk', 'value' => round($features[$n] ?? 0, 2)];

        $band = collect(self::BANDS)->first(fn ($b) => $pd < $b[1])[0];
        $rec = match (true) {
            $pd < (float) Setting::get('risk_auto_recommend_approve', 0.07) => 'Approve',
            $pd < (float) Setting::get('risk_decline_above', 0.30) => 'Review carefully',
            default => 'Decline or require more security',
        };
        return ['pd' => round($pd, 4), 'band' => $band, 'score' => (int) round((1 - $pd) * 100), 'recommendation' => $rec, 'drivers' => $drivers, 'model' => $model];
    }

    /** Score a loan and store the result on it. Returns null when no model is active. */
    public function scoreLoan(Loan $loan): ?array
    {
        if (! $this->activeModel()) return null;
        $features = $loan->risk_features ?: $this->snapshot($loan);
        $r = $this->predict($features);
        $loan->forceFill(['risk_features' => $features, 'pd' => $r['pd'], 'risk_band' => $r['band'], 'risk_model_id' => $r['model']->id])->saveQuietly();
        return $r;
    }

    /** Behavioural PD for a running loan: application features updated with current arrears. */
    public function predictRunning(Loan $loan): ?array
    {
        if (! $this->activeModel()) return null;
        $f = $loan->risk_features ?: $this->snapshot($loan);
        $f['past_max_arrears'] = max($f['past_max_arrears'] ?? 0, min(365, $loan->daysInArrears()));
        return $this->predict($f);
    }

    /** Keep the training label up to date: worst arrears each loan has ever reached. */
    public function refreshArrearsHistory(): int
    {
        $n = 0;
        Loan::where('status', 'disbursed')->chunkById(200, function ($loans) use (&$n) {
            foreach ($loans as $l) {
                $d = $l->daysInArrears();
                if ($d > $l->max_days_arrears) { $l->forceFill(['max_days_arrears' => min(65535, $d)])->saveQuietly(); $n++; }
            }
        });
        return $n;
    }

    /**
     * Train a new model on loans with a known outcome.
     * Good = cleared without ever reaching 90 days; Bad = reached 90+ days or written off.
     */
    public function train(?int $userId = null): RiskModel
    {
        $this->refreshArrearsHistory();
        $loans = Loan::with('member')->whereIn('status', ['closed', 'written_off', 'disbursed'])->get()
            ->filter(fn ($l) => $l->status !== 'disbursed' || $l->max_days_arrears >= 90 || $l->maturity_date?->isPast());

        $X = []; $y = [];
        foreach ($loans as $l) {
            $f = $l->risk_features ?: $this->snapshot($l);
            $X[] = array_map(fn ($n) => (float) ($f[$n] ?? 0), array_keys(self::FEATURES));
            $y[] = ($l->status === 'written_off' || $l->max_days_arrears >= 90) ? 1 : 0;
        }
        $minSamples = (int) Setting::get('risk_min_samples', 50);
        $minDefaults = (int) Setting::get('risk_min_defaults', 5);
        $defaults = array_sum($y);
        if (count($y) < $minSamples || $defaults < $minDefaults || $defaults === count($y)) {
            throw new RuntimeException(sprintf('Not enough history to train: %d loans with known outcome (need %d) and %d defaults (need %d). The rule-based score stays in use until then.',
                count($y), $minSamples, $defaults, $minDefaults));
        }

        // Standardise
        $k = count($X[0]); $n = count($X);
        $means = array_fill(0, $k, 0.0); $stds = array_fill(0, $k, 0.0);
        foreach ($X as $row) foreach ($row as $j => $v) $means[$j] += $v / $n;
        foreach ($X as $row) foreach ($row as $j => $v) $stds[$j] += ($v - $means[$j]) ** 2 / $n;
        $stds = array_map(fn ($s) => sqrt($s) ?: 1.0, $stds);
        $Z = array_map(fn ($row) => array_map(fn ($v, $j) => ($v - $means[$j]) / $stds[$j], $row, array_keys($row)), $X);

        // Stratified-ish split: every 5th row held out for validation
        $train = []; $test = [];
        foreach ($Z as $i => $row) {
            if ($i % 5 === 4) $test[] = [$row, $y[$i]]; else $train[] = [$row, $y[$i]];
        }

        [$w, $b] = $this->fit($train, $k);
        $metrics = $this->evaluate($test ?: $train, $w, $b);
        [$w, $b] = $this->fit(array_map(fn ($row, $i) => [$row, $y[$i]], $Z, array_keys($Z)), $k); // final fit on all data

        RiskModel::query()->update(['is_active' => false]);
        $model = RiskModel::create([
            'version' => 'v' . now()->format('Ymd.His'),
            'samples' => $n, 'defaults' => $defaults,
            'features' => ['names' => array_keys(self::FEATURES), 'means' => $means, 'stds' => $stds],
            'weights' => array_map(fn ($v) => round($v, 6), $w), 'intercept' => round($b, 6),
            'auc' => $metrics['auc'], 'accuracy' => $metrics['accuracy'], 'metrics' => $metrics,
            'is_active' => true, 'trained_by' => $userId,
        ]);
        AuditLog::record('risk_model_trained', $model, ['samples' => $n, 'auc' => $metrics['auc']]);
        return $model;
    }

    /** Logistic regression, batch gradient descent with L2 and class weighting. */
    private function fit(array $data, int $k): array
    {
        $w = array_fill(0, $k, 0.0); $b = 0.0;
        $pos = max(1, count(array_filter($data, fn ($d) => $d[1] === 1)));
        $neg = max(1, count($data) - $pos);
        $wPos = count($data) / (2 * $pos); $wNeg = count($data) / (2 * $neg);
        $lr = 0.1; $lambda = (float) Setting::get('risk_l2', 0.01); $n = count($data);

        for ($epoch = 0; $epoch < 800; $epoch++) {
            $gw = array_fill(0, $k, 0.0); $gb = 0.0;
            foreach ($data as [$x, $t]) {
                $z = $b; foreach ($x as $j => $v) $z += $w[$j] * $v;
                $p = 1 / (1 + exp(-max(-30, min(30, $z))));
                $err = ($p - $t) * ($t === 1 ? $wPos : $wNeg);
                foreach ($x as $j => $v) $gw[$j] += $err * $v;
                $gb += $err;
            }
            foreach ($w as $j => $_) $w[$j] -= $lr * ($gw[$j] / $n + $lambda * $w[$j]);
            $b -= $lr * $gb / $n;
        }
        return [$w, $b];
    }

    private function evaluate(array $data, array $w, float $b): array
    {
        $scored = [];
        foreach ($data as [$x, $t]) {
            $z = $b; foreach ($x as $j => $v) $z += $w[$j] * $v;
            $scored[] = [1 / (1 + exp(-$z)), $t];
        }
        $correct = count(array_filter($scored, fn ($s) => ($s[0] >= 0.5 ? 1 : 0) === $s[1]));
        // AUC = probability a random defaulter scores higher than a random good loan
        $pos = array_filter($scored, fn ($s) => $s[1] === 1); $neg = array_filter($scored, fn ($s) => $s[1] === 0);
        $auc = null;
        if ($pos && $neg) {
            $wins = 0;
            foreach ($pos as $p) foreach ($neg as $q) $wins += $p[0] > $q[0] ? 1 : ($p[0] == $q[0] ? 0.5 : 0);
            $auc = round($wins / (count($pos) * count($neg)), 4);
        }
        return ['auc' => $auc, 'accuracy' => round($correct / max(1, count($scored)), 4), 'validation_size' => count($scored)];
    }
}
