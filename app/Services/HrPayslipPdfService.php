<?php

namespace App\Services;

use App\Enums\HrPayrollRuleKind;
use App\Models\HrPayslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrPayslipPdfService
{
    public function output(HrPayslip $payslip): string
    {
        $payslip->loadMissing(['employee.department', 'payrollRun', 'company']);
        $logoDataUri = app(CompanyThemeService::class)->logoDataUriForPdf($payslip->company);

        $breakdown = is_array($payslip->breakdown) ? $payslip->breakdown : [];
        $lines = $this->normalizeLines($breakdown['lines'] ?? []);

        if ($lines === []) {
            $lines = [[
                'name' => __('hr_base_salary'),
                'kind' => HrPayrollRuleKind::Earning->value,
                'amount_minor' => (int) $payslip->gross_minor,
                'percent' => null,
            ]];
        }

        $html = view('hr.payroll.payslip-pdf', [
            'payslip' => $payslip,
            'employee' => $payslip->employee,
            'run' => $payslip->payrollRun,
            'company' => $payslip->company,
            'lines' => $lines,
            'logoDataUri' => $logoDataUri,
        ])->render();

        $html = app(DomPdfArabicFixer::class)->reshapeHtml($html);

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    public function stream(HrPayslip $payslip): StreamedResponse
    {
        $employee = $payslip->employee;
        $slug = Str::slug($employee?->full_name ?? 'payslip', '-');
        if ($slug === '') {
            $slug = 'payslip';
        }
        $period = $payslip->payrollRun?->period_end?->format('Y-m') ?? $payslip->id;
        $filename = 'fiche-de-paie-'.$slug.'-'.$period.'.pdf';

        return response()->streamDownload(function () use ($payslip): void {
            echo $this->output($payslip);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @param  mixed  $rawLines
     * @return list<array{name: string, kind: string, amount_minor: int, percent: float|null}>
     */
    private function normalizeLines(mixed $rawLines): array
    {
        if (! is_array($rawLines)) {
            return [];
        }

        $lines = [];
        foreach ($rawLines as $raw) {
            if (is_object($raw)) {
                $raw = (array) $raw;
            }
            if (! is_array($raw)) {
                continue;
            }

            $kind = strtolower((string) ($raw['kind'] ?? HrPayrollRuleKind::Earning->value));
            if (! in_array($kind, [HrPayrollRuleKind::Earning->value, HrPayrollRuleKind::Deduction->value], true)) {
                $kind = HrPayrollRuleKind::Earning->value;
            }

            $amount = $raw['amount_minor'] ?? $raw['amount'] ?? 0;

            $lines[] = [
                'name' => (string) ($raw['name'] ?? $raw['label'] ?? '—'),
                'kind' => $kind,
                'amount_minor' => (int) $amount,
                'percent' => isset($raw['percent']) && $raw['percent'] !== null && $raw['percent'] !== ''
                    ? (float) $raw['percent']
                    : null,
            ];
        }

        return $lines;
    }
}
