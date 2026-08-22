@extends('emails.layout')

@section('content')
<h2 style="margin: 0 0 16px; font-size: 18px; color: #1f2937;">A task is ready for review</h2>
<p style="margin: 0 0 12px; color: #4b5563;">
    <strong>{{ $submitterName }}</strong> moved <strong>{{ $task->title }}</strong> to Review.
</p>

@if($task->project)
<p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">
    Project: {{ $task->project->name }}
</p>
@endif

<a href="{{ url('/admin/task-detail/' . $task->id) }}"
   style="display: inline-block; background: #4f46e5; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500;">
    Open Task
</a>
@endsection
