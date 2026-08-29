@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">New Task Assigned</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        @if($intro)
            {{ $intro }}
        @else
            Hi {{ $employee->name }}, a task has been assigned to you:
        @endif
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;width:100px;">Title</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;font-weight:600;">{{ $task->title }}</td>
        </tr>
        @if($task->project)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Project</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $task->project->name }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Priority</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ ucfirst($task->priority?->value ?? 'medium') }}</td>
        </tr>
        @if($task->due_date)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Due Date</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $task->due_date->format('M d, Y') }}</td>
        </tr>
        @endif
    </table>

    @if($task->summary)
    <p style="margin:16px 0 0;color:#64748b;font-size:13px;">{{ $task->summary }}</p>
    @endif
@endsection
