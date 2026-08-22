@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">New Issue Created</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, a new issue has been created{{ $issue->project ? ' in ' . $issue->project->name : '' }}:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fef2f2;border-radius:6px;border:1px solid #fecaca;">
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;width:100px;">Title</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;font-weight:600;">{{ $issue->title }}</td>
        </tr>
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Priority</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ ucfirst($issue->priority?->value ?? 'medium') }}</td>
        </tr>
        @if($issue->customer)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Customer</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $issue->customer->name }}</td>
        </tr>
        @endif
        @if($issue->due_date)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Due Date</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $issue->due_date->format('M d, Y') }}</td>
        </tr>
        @endif
    </table>

    @if($issue->description)
    <p style="margin:16px 0 0;color:#64748b;font-size:13px;">{{ Str::limit($issue->description, 300) }}</p>
    @endif
@endsection
