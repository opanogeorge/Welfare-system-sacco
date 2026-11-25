<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\GlAccount;
use App\Services\AssetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AssetController extends Controller
{
    public const CATEGORIES = ['Land & Buildings', 'Furniture & Fittings', 'Computers & ICT', 'Motor Vehicles', 'Office Equipment', 'Software'];

    public function __construct(private AssetService $assets) {}

    public function index(Request $r)
    {
        $assets = FixedAsset::when($r->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'active'))
            ->orderBy('code')->get();
        $cashAccounts = GlAccount::where('is_cash', true)->orderBy('code')->get();
        return view('assets.index', ['assets' => $assets, 'categories' => self::CATEGORIES, 'cashAccounts' => $cashAccounts]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'code' => 'required|string|max:30|unique:fixed_assets,code', 'name' => 'required|string|max:150',
            'category' => ['required', Rule::in(self::CATEGORIES)], 'location' => 'nullable|string|max:120', 'custodian' => 'nullable|string|max:120',
            'purchase_date' => 'required|date', 'cost' => 'required|numeric|min:1', 'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_years' => 'required|integer|min:1|max:100', 'method' => 'required|in:straight_line,reducing_balance',
            'rate' => 'nullable|numeric|min:0|max:100', 'next_maintenance_on' => 'nullable|date',
            'paid_from' => 'nullable|exists:gl_accounts,id',
        ]);
        $paid = $d['paid_from'] ?? null; unset($d['paid_from']);
        $d['salvage_value'] = $d['salvage_value'] ?? 0;
        $d['rate'] = $d['rate'] ?? 0;
        try {
            $this->assets->register($d, $paid ? GlAccount::find($paid) : null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Asset registered.');
    }

    public function update(Request $r, FixedAsset $asset)
    {
        $asset->update($r->validate(['location' => 'nullable|string|max:120', 'custodian' => 'nullable|string|max:120', 'next_maintenance_on' => 'nullable|date']));
        return back()->with('success', 'Asset updated (location / custodian / maintenance).');
    }

    public function dispose(Request $r, FixedAsset $asset)
    {
        $d = $r->validate(['proceeds' => 'required|numeric|min:0', 'disposed_on' => 'required|date', 'received_into' => 'required|exists:gl_accounts,id']);
        try {
            $this->assets->dispose($asset, (float) $d['proceeds'], $d['disposed_on'], GlAccount::find($d['received_into']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Asset disposed.');
    }

    public function depreciate(Request $r)
    {
        $r->validate(['month' => 'required|date_format:Y-m']);
        try {
            $res = $this->assets->depreciateMonth(Carbon::createFromFormat('Y-m', $r->month));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', "Depreciation posted for {$res['count']} asset(s): KES " . number_format($res['total'], 2));
    }
}
