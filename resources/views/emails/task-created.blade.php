@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">New task in your project</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        Hi {{ $employee->name }},
        @if($creatorName)
            {{ $creatorName }} created a task in a project you are on:
        @else
            a new task was created in a project you are on:
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
    </table>
@endsection
