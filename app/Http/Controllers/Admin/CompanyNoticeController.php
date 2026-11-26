<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AuditLogService;
use App\Services\FcmPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyNoticeController extends Controller
{
    public function store(Request $request, Company $company, AuditLogService $audit, FcmPushService $push): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            'send_push' => ['sometimes', 'boolean'],
        ]);

        $title = trim((string) ($data['title'] ?? '')) ?: __('ClicFlow notice');
        $sendPush = $request->boolean('send_push', true);

        $audit->record(
            action: 'platform_notice',
            auditable: $company,
            properties: [
                'title' => $title,
                'message' => $data['message'],
                'send_push' => $sendPush,
            ],
            companyId: (string) $company->id,
        );

        $status = __('Notice sent to company activity feed.');

        if ($sendPush) {
            $result = $push->sendToCompany(
                (string) $company->id,
                $title,
                $data['message'],
                [
                    'type' => 'admin_notice',
                    'company_id' => (string) $company->id,
                ],
            );
            $status .= ' '.__('Push: :sent sent, :failed failed.', [
                'sent' => $result['sent'],
                'failed' => $result['failed'],
            ]);
        }

        return redirect()->route('admin.companies.show', $company)->with('status', $status);
    }
}
