<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns database and unexpected errors into clear messages for users instead of a stack trace.
 *  - Form submissions go back to the form with the input kept, the bad field highlighted, and an alert.
 *  - API / AJAX calls get JSON {"error": "..."}.
 *  - Everything is still logged in full (storage/logs/laravel.log) with a reference code shown to the user.
 *  - With APP_DEBUG=true, the alert also shows the technical message for developers.
 */
class FriendlyErrors
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (QueryException $e, Request $request) {
            [$field, $message] = self::translate($e);
            return self::respond($request, $message, $field, $e);
        });

        // Session expired (CSRF token mismatch) on a form post
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 419 && ! $request->expectsJson() && ! $request->isMethod('GET')) {
                return back()->withInput($request->except(self::SENSITIVE))
                    ->with('error', 'Your session expired while you were filling the form. Please check the details and submit again.');
            }
            return null; // other HTTP errors use the error pages in resources/views/errors
        });

        // Anything else that blows up while saving a form
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface
                || $e instanceof \Illuminate\Auth\AuthenticationException || $e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return null;
            }
            if ($request->isMethod('GET') && ! $request->expectsJson()) return null; // falls through to errors/500
            return self::respond($request, 'Something went wrong while saving. Nothing was changed. Please try again, and contact the system administrator if it keeps happening.', null, $e);
        });
    }

    private const SENSITIVE = ['password', 'password_confirmation', 'current_password', 'signature', 'selfie', 'otp', 'code'];

    private static function respond(Request $request, string $message, ?string $field, Throwable $e)
    {
        $ref = strtoupper(Str::random(6));
        Log::error("[ref $ref] " . $e->getMessage(), ['url' => $request->fullUrl(), 'user' => $request->user()?->id, 'exception' => $e]);
        $full = "$message (Ref: $ref)";

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message, 'field' => $field, 'ref' => $ref], $field ? 422 : 500);
        }

        $redirect = url()->previous() && url()->previous() !== $request->fullUrl() ? back() : redirect()->to('/');
        $redirect = $redirect->withInput($request->except(self::SENSITIVE))->with('error', $full);
        if ($field) $redirect = $redirect->withErrors([$field => $message]);
        if (config('app.debug')) $redirect = $redirect->with('error_detail', Str::limit($e->getMessage(), 1500));
        return $redirect;
    }

    /** @return array{0: ?string, 1: string} [field, user-friendly message] */
    public static function translate(QueryException $e): array
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $msg = $e->errorInfo[2] ?? $e->getMessage();
        $names = ['national_id' => 'National ID number', 'kra_pin' => 'KRA PIN', 'member_no' => 'Member number', 'payroll_no' => 'Payroll number',
                  'loan_no' => 'Loan number', 'account_no' => 'Account number', 'email' => 'Email address', 'phone' => 'Phone number',
                  'monthly_contribution' => 'Monthly contribution', 'date_of_birth' => 'Date of birth', 'joined_on' => 'Date joined'];
        $label = fn (?string $col) => $col ? ($names[$col] ?? Str::of(preg_replace('/_id$/', '', $col))->replace('_', ' ')->trim()->ucfirst()->toString()) : 'A value';

        switch ($code) {
            case 1048: // Column 'x' cannot be null
                preg_match("/Column '([^']+)'/", $msg, $m);
                return [$m[1] ?? null, $label($m[1] ?? null) . ' is required. Please fill it in.'];
            case 1062: // Duplicate entry 'v' for key 'table_col_unique'
                preg_match("/Duplicate entry '([^']*)' for key '([^']+)'/", $msg, $m);
                $col = isset($m[2]) ? self::guessColumn($m[2]) : null;
                return [$col, $label($col) . ' "' . ($m[1] ?? '') . '" already exists. Each record must be unique.'];
            case 1452: // FK fails on insert/update
                preg_match('/FOREIGN KEY \(`([^`]+)`\)/', $msg, $m);
                return [$m[1] ?? null, 'The selected ' . strtolower($label($m[1] ?? null)) . ' does not exist (it may have been deleted). Please choose again.'];
            case 1451: // FK prevents delete
                return [null, 'This record is linked to other records (for example transactions or loans), so it cannot be deleted. Deactivate it instead.'];
            case 1406: // Data too long
                preg_match("/column '([^']+)'/", $msg, $m);
                return [$m[1] ?? null, $label($m[1] ?? null) . ' is too long. Please shorten it.'];
            case 1264: // Out of range
                preg_match("/column '([^']+)'/", $msg, $m);
                return [$m[1] ?? null, $label($m[1] ?? null) . ' is too large.'];
            case 1366: case 1292: // Incorrect value / datetime
                preg_match("/column '([^']+)'/", $msg, $m);
                return [$m[1] ?? null, $label($m[1] ?? null) . ' has an invalid value.'];
            case 1364: // Field doesn't have a default value
                preg_match("/Field '([^']+)'/", $msg, $m);
                return [$m[1] ?? null, $label($m[1] ?? null) . ' is required.'];
            case 1146: case 1054: // missing table / column
                return [null, 'The database is not up to date. An administrator needs to run "php artisan migrate".'];
            case 1213: case 1205: // deadlock / lock wait timeout
                return [null, 'The system was busy with another transaction. Please submit again.'];
            case 2002: case 2006: case 1045: case 1049:
                return [null, 'Cannot connect to the database. Make sure MySQL is running (XAMPP) and the database settings in .env are correct.'];
            default:
                return [null, 'The record could not be saved because of a database error.'];
        }
    }

    /** members_national_id_unique -> national_id */
    private static function guessColumn(string $key): ?string
    {
        $key = preg_replace('/_unique$/', '', preg_replace('/^.*\./', '', $key));
        foreach (['members_', 'users_', 'loans_', 'employers_', 'loan_products_', 'account_types_', 'member_accounts_', 'branches_', 'gl_accounts_', 'fixed_assets_'] as $prefix) {
            if (str_starts_with($key, $prefix)) return substr($key, strlen($prefix));
        }
        return $key;
    }
}
