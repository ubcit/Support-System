@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Task moved to trash</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        Hi {{ $employee->name }},
        @if($actorName)
            {{ $actorName }} deleted this task:
        @else
            a task was deleted:
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
    </table>

    <p style="margin:16px 0 0;color:#64748b;font-size:13px;">You can restore it from Trash if needed.</p>
@endsection
