@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Issue assigned to you</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, an issue has been assigned to you:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;width:100px;">Title</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;font-weight:600;">{{ $issue->title }}</td>
        </tr>
        @if($issue->project)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Project</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $issue->project->name }}</td>
        </tr>
        @endif
        @if($issue->priority)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Priority</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ is_object($issue->priority) ? ucfirst($issue->priority->value ?? (string) $issue->priority) : ucfirst((string) $issue->priority) }}</td>
        </tr>
        @endif
    </table>
@endsection
