@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Daily Digest</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, here's your daily summary for {{ now()->format('M d, Y') }}:</p>

    {{-- Stats --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
        <tr>
            <td width="25%" style="padding:12px;text-align:center;background-color:#f8fafc;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#1e293b;">{{ $stats['open_tasks'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Open Tasks</div>
            </td>
            <td width="4%"></td>
            <td width="25%" style="padding:12px;text-align:center;background-color:#fef2f2;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#ef4444;">{{ $stats['overdue_tasks'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Overdue</div>
            </td>
            <td width="4%"></td>
            <td width="25%" style="padding:12px;text-align:center;background-color:#fffbeb;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#f59e0b;">{{ $stats['due_soon'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Due Soon</div>
            </td>
            <td width="4%"></td>
            <td width="25%" style="padding:12px;text-align:center;background-color:#f0fdf4;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#16a34a;">{{ $stats['open_issues'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Open Issues</div>
            </td>
        </tr>
    </table>

    {{-- Overdue Tasks --}}
    @if($overdueTasks->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#ef4444;font-size:14px;">Overdue Tasks</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #fecaca;border-radius:6px;overflow:hidden;margin-bottom:20px;">
        @foreach($overdueTasks as $task)
        <tr>
            <td style="padding:8px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #fef2f2;">{{ $task->title }}</td>
            <td style="padding:8px 14px;font-size:13px;color:#ef4444;border-bottom:1px solid #fef2f2;white-space:nowrap;">{{ $task->due_date->format('M d') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- Upcoming Deadlines --}}
    @if($upcomingDeadlines->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#f59e0b;font-size:14px;">Upcoming Project Deadlines</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #fde68a;border-radius:6px;overflow:hidden;margin-bottom:20px;">
        @foreach($upcomingDeadlines as $project)
        <tr>
            <td style="padding:8px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #fffbeb;">{{ $project->name }}</td>
            <td style="padding:8px 14px;font-size:13px;color:#f59e0b;border-bottom:1px solid #fffbeb;white-space:nowrap;">{{ $project->deadline_at->format('M d') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">This digest covers tasks and projects assigned to you.</p>
@endsection
