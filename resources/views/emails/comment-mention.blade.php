@extends('emails.layout')

@section('content')
<h2 style="margin: 0 0 16px; font-size: 18px; color: #1f2937;">You were mentioned in a comment</h2>

<p style="margin: 0 0 12px; color: #4b5563;">
    <strong>{{ $comment->employee?->name ?? 'Someone' }}</strong> mentioned you in a comment on task:
</p>

<div style="background: #f3f4f6; border-radius: 8px; padding: 16px; margin: 0 0 16px;">
    <p style="margin: 0 0 8px; font-weight: 600; color: #111827;">
        {{ $comment->task?->title ?? 'Task' }}
    </p>
    <p style="margin: 0; color: #6b7280; font-style: italic;">
        "{{ \Illuminate\Support\Str::limit($comment->content, 200) }}"
    </p>
</div>

<a href="{{ url('/admin/task-detail/' . $comment->task_id) }}"
   style="display: inline-block; background: #4f46e5; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500;">
    View Task
</a>
@endsection
