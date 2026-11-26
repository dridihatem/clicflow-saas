<x-emails.layout :title="__('Comment')">
    <p style="margin:0 0 12px;">{{ __('Hello,') }}</p>

    @if ($notifyClient)
        <p style="margin:0 0 14px;">{{ __(':company replied to a comment on task « :task » in project « :project ».', [
            'company' => $company->name,
            'task' => $task->title,
            'project' => $project->title,
        ]) }}</p>
    @else
        <p style="margin:0 0 14px;">{{ __(':author left a comment on task « :task » in project « :project ».', [
            'author' => $comment->user?->name ?? __('Client'),
            'task' => $task->title,
            'project' => $project->title,
        ]) }}</p>
    @endif

    <blockquote style="margin:0 0 18px; padding:12px 14px; border-left:3px solid #EA580C; background:#F6F7FB; color:#334155; border-radius:0 10px 10px 0;">
        {{ $comment->body }}
    </blockquote>

    <p style="margin:0 0 12px;">
        <a href="{{ $actionUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">
            {{ $notifyClient ? __('View project in portal') : __('Open project tasks') }}
        </a>
    </p>

    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('If the button does not work, copy this link:') }}<br><span style="word-break:break-all; color:#EA580C;">{{ $actionUrl }}</span></p>
</x-emails.layout>
