@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Project Deadline Approaching</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, the following {{ Str::plural('project', count($projects)) }} {{ count($projects) === 1 ? 'has a' : 'have' }} deadline{{ count($projects) === 1 ? '' : 's' }} approaching:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
        <tr style="background-color:#f8fafc;">
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Project</td>
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Deadline</td>
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Days Left</td>
        </tr>
        @foreach($projects as $project)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ $project->name }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#f59e0b;border-bottom:1px solid #f1f5f9;">{{ $project->deadline_at->format('M d, Y') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ (int) now()->diffInDays($project->deadline_at, false) }}</td>
        </tr>
        @endforeach
    </table>
@endsection
