@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Customer session ready</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, a customer request finished collecting and is ready for the team:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;width:100px;">Customer</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;font-weight:600;">{{ $customer?->name ?? 'Unknown' }}</td>
        </tr>
        @if($project)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Project</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $project->name }}@if($project->code) ({{ $project->code }})@endif</td>
        </tr>
        @endif
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Title</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;">{{ $session->title ?: 'Customer request' }}</td>
        </tr>
        @if($session->summary)
        <tr>
            <td style="padding:6px 14px 14px;font-size:13px;color:#64748b;">Summary</td>
            <td style="padding:6px 14px 14px;font-size:13px;color:#1e293b;white-space:pre-wrap;">{{ $session->summary }}</td>
        </tr>
        @endif
    </table>
@endsection
