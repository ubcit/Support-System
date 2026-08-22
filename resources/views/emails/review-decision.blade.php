@extends('emails.layout')

@section('content')
@if($decision === 'approved')
<h2 style="margin: 0 0 16px; font-size: 18px; color: #1f2937;">Your task was approved</h2>
<p style="margin: 0 0 12px; color: #4b5563;">
    <strong>{{ $reviewerName }}</strong> approved <strong>{{ $task->title }}</strong>. It is now complete.
</p>
@else
<h2 style="margin: 0 0 16px; font-size: 18px; color: #1f2937;">Changes requested</h2>
<p style="margin: 0 0 12px; color: #4b5563;">
    <strong>{{ $reviewerName }}</strong> sent <strong>{{ $task->title }}</strong> back to In Progress.
</p>
@endif

@if($note)
<div style="background: #f3f4f6; border-radius: 8px; padding: 16px; margin: 0 0 16px;">
    <p style="margin: 0; color: #6b7280; font-style: italic;">"{{ $note }}"</p>
</div>
@endif

<a href="{{ url('/admin/task-detail/' . $task->id) }}"
   style="display: inline-block; background: #4f46e5; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500;">
    Open Task
</a>
@endsection
