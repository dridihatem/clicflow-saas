<?php

namespace App\Services;

use App\Enums\TaskCommentVisibility;
use App\Mail\ProjectTaskCommentMail;
use App\Models\ProjectTaskComment;
use App\Models\User;
use Throwable;

class ProjectTaskCommentMailService
{
    public function __construct(
        private TenantSmtpMailer $tenantMailer,
    ) {}

    public function notifyForComment(ProjectTaskComment $comment): void
    {
        $comment->loadMissing(['user', 'task.project.client.user', 'company']);

        $task = $comment->task;
        $project = $task?->project;
        $company = $comment->company;

        if (! $task || ! $project || ! $company) {
            return;
        }

        if ($comment->is_client) {
            $this->notifyStaff($comment, $task, $project, $company);
        } elseif ($this->isVisibleToClient($comment)) {
            $this->notifyClient($comment, $task, $project, $company);
        }
    }

    private function isVisibleToClient(ProjectTaskComment $comment): bool
    {
        $visibility = $comment->visibility;
        if ($visibility instanceof TaskCommentVisibility) {
            return $visibility === TaskCommentVisibility::Shared;
        }

        return (string) ($visibility ?? TaskCommentVisibility::Shared->value) === TaskCommentVisibility::Shared->value;
    }

    private function notifyStaff(ProjectTaskComment $comment, $task, $project, $company): void
    {
        $actionUrl = flowdesk_tenant_url($company, route('projects.show', [$project, 'tab' => 'tasks'], false));

        $recipients = User::query()
            ->where('company_id', $company->id)
            ->workspaceStaff()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        foreach ($recipients as $recipient) {
            $this->send($comment, $task, $project, $company, $actionUrl, false, $recipient->email);
        }
    }

    private function notifyClient(ProjectTaskComment $comment, $task, $project, $company): void
    {
        $clientEmail = $project->client?->email
            ?: $project->client?->user?->email;

        if ($clientEmail === null || $clientEmail === '') {
            return;
        }

        $actionUrl = flowdesk_tenant_url($company, route('portal.projects.show', $project, false));

        $this->send($comment, $task, $project, $company, $actionUrl, true, $clientEmail);
    }

    private function send(
        ProjectTaskComment $comment,
        $task,
        $project,
        $company,
        string $actionUrl,
        bool $notifyClient,
        string $toEmail,
    ): void {
        $mailable = new ProjectTaskCommentMail($comment, $task, $project, $company, $actionUrl, $notifyClient);

        try {
            $this->tenantMailer->send($company, $mailable, $toEmail);
        } catch (Throwable) {
            // Best-effort notification.
        }
    }
}
