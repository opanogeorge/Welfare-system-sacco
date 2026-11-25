<?php

use App\Models\Setting;

if (! function_exists('money')) {
    function money($value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default; // before migrations run
        }
    }
}

if (! function_exists('sacco_name')) {
    function sacco_name(): string
    {
        return setting('sacco_name') ?: config('sacco.name');
    }
}

if (! function_exists('theme')) {
    /** Colour theme (Settings → Theme). brand = sidebar/dark, brand2 = buttons/links, accent = highlights. */
    function theme(?string $name = null): array
    {
        $themes = theme_presets();
        try { $name ??= (string) setting('theme', 'navy'); } catch (\Throwable $e) { $name = 'navy'; }
        return ($themes[$name] ?? $themes['navy']) + ['key' => isset($themes[$name]) ? $name : 'navy'];
    }

    function theme_presets(): array
    {
        return [
            'navy'     => ['label' => 'Navy blue',    'brand' => '#14284b', 'brand2' => '#1f5fbf', 'accent' => '#7fb2ff', 'bg' => '#f3f5f9'],
            'royal'    => ['label' => 'Royal purple', 'brand' => '#2e1a5c', 'brand2' => '#6a3fc8', 'accent' => '#c3a8ff', 'bg' => '#f5f3fa'],
            'maroon'   => ['label' => 'Maroon',       'brand' => '#4a1420', 'brand2' => '#a3243b', 'accent' => '#ffb0bd', 'bg' => '#faf4f5'],
            'teal'     => ['label' => 'Ocean teal',   'brand' => '#0f3b44', 'brand2' => '#137c8b', 'accent' => '#7fe0ea', 'bg' => '#f2f7f8'],
            'charcoal' => ['label' => 'Charcoal & gold', 'brand' => '#1f2329', 'brand2' => '#3b4452', 'accent' => '#f0b429', 'bg' => '#f4f4f5'],
            'orange'   => ['label' => 'Sunset orange', 'brand' => '#3d1f0f', 'brand2' => '#d0621b', 'accent' => '#ffc28f', 'bg' => '#faf6f2'],
            'green'    => ['label' => 'Green (original)', 'brand' => '#0f5132', 'brand2' => '#198754', 'accent' => '#9be3b9', 'bg' => '#f4f6f5'],
        ];
    }

    function hex_rgb(string $hex): string
    {
        $h = ltrim($hex, '#');
        return implode(',', array_map('hexdec', str_split(strlen($h) === 3 ? preg_replace('/(.)/', '$1$1', $h) : $h, 2)));
    }
}
