<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Runs a company-creation transaction, retrying when a concurrent signup grabbed
 * the same subdomain/slug, and turns database errors into a friendly message
 * shown inside the form instead of a raw exception page.
 */
trait RetriesCompanyCreation
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|RedirectResponse
     */
    protected function createCompanyWithRetry(Request $request, callable $callback, int $attempts = 3, string $field = 'company_name')
    {
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction($callback);
            } catch (UniqueConstraintViolationException $e) {
                if ($try < $attempts && ! $this->isEmailConflict($e)) {
                    continue; // subdomain/slug race: recompute names and retry
                }
                report($e);

                return $this->companyCreationFailure($request, $e, $field);
            } catch (QueryException $e) {
                report($e);

                return $this->companyCreationFailure($request, $e, $field);
            }
        }
    }

    protected function companyCreationFailure(Request $request, QueryException $e, string $field = 'company_name'): RedirectResponse
    {
        if ($e instanceof UniqueConstraintViolationException && $this->isEmailConflict($e)) {
            $errors = ['email' => __('This email address is already used by another account.')];
        } elseif ($e instanceof UniqueConstraintViolationException) {
            $errors = [$field => __('This company name is already taken. Please try a variation (e.g. add your city).')];
        } else {
            $errors = [$field => __('Something went wrong while creating your workspace. Please try again.')];
        }

        return back()
            ->withInput($request->except('password', 'password_confirmation', 'admin_password', '_captcha_token', '_captcha_answer'))
            ->withErrors($errors);
    }

    private function isEmailConflict(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'users_email_unique');
    }
}
