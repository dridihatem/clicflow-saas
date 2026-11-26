<?php

namespace App\Http\Controllers;

use App\Models\Form as LeadForm;
use App\Models\FormSubmission;
use App\Models\Project;
use App\Services\FormSubmissionConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class FormSubmissionController extends Controller
{
    public function __construct(
        private FormSubmissionConversionService $conversion,
    ) {}

    public function index(Request $request, LeadForm $form): View
    {
        $this->authorizeForm($form);

        $submissions = FormSubmission::query()->withoutGlobalScopes()
            ->where('form_id', $form->id)
            ->where('company_id', $form->company_id)
            ->latest()
            ->paginate(20);

        return view('forms.submissions', [
            'form' => $form,
            'submissions' => $submissions,
            'conversion' => $this->conversion,
        ]);
    }

    public function show(Request $request, FormSubmission $submission): View
    {
        $form = $submission->form;
        $this->authorizeForm($form);
        abort_if((string) $submission->form_id !== (string) $form->id, 404);

        $data = is_array($submission->data) ? $submission->data : [];
        $contact = $this->conversion->extractContact($data);

        $existingProject = Project::query()->withoutGlobalScope('tenant')
            ->where('company_id', $form->company_id)
            ->where('form_submission_id', $submission->id)
            ->first();

        return view('forms.submission-show', [
            'form' => $form,
            'submission' => $submission,
            'data' => $data,
            'contact' => $contact,
            'existingProject' => $existingProject,
            'conversion' => $this->conversion,
        ]);
    }

    public function convertToProject(Request $request, FormSubmission $submission): RedirectResponse
    {
        $form = $submission->form;
        $this->authorizeForm($form);
        abort_if((string) $submission->form_id !== (string) $form->id, 404);

        $company = $request->user()->company;
        abort_if(! $company, 403);

        $existing = Project::query()->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('form_submission_id', $submission->id)
            ->first();

        if ($existing) {
            return redirect()
                ->route('projects.show', $existing)
                ->with('status', __('A project already exists for this submission.'));
        }

        $withAi = $request->boolean('with_ai', true);

        try {
            $result = $this->conversion->convertToProject($request->user(), $submission, $withAi);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('forms.submissions.show', $submission)
                ->with('error', $e->getMessage());
        }

        $project = $result['project'];
        $message = $result['ai_used']
            ? __('Project created from submission with AI tasks and pricing.')
            : __('Project created from submission.');

        return redirect()->route('projects.show', $project)->with('status', $message);
    }

    private function authorizeForm(LeadForm $form): void
    {
        $company = auth()->user()?->company;
        abort_if(! $company || (string) $form->company_id !== (string) $company->id, 403);
    }
}
