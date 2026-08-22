@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Task Completed</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, the following task has been marked as completed:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;">
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
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Completed</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#16a34a;font-weight:600;">{{ $task->completed_at?->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}</td>
        </tr>
    </table>
@endsection
