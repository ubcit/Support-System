@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Daily Digest</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, here's your daily summary for {{ now()->format('M d, Y') }}:</p>

    {{-- Stats --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
        <tr>
            <td width="25%" style="padding:12px;text-align:center;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#1e293b;">{{ $stats['open_tasks'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Open Tasks</div>
            </td>
            <td width="4%"></td>
            <td width="25%" style="padding:12px;text-align:center;background-color:#fef2f2;border:1px solid #fecaca;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#ef4444;">{{ $stats['overdue_tasks'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Overdue</div>
            </td>
            <td width="4%"></td>
            <td width="25%" style="padding:12px;text-align:center;background-color:#fffbeb;border:1px solid #fde68a;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#f59e0b;">{{ $stats['due_soon'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Due Soon</div>
            </td>
            <td width="4%"></td>
            <td width="25%" style="padding:12px;text-align:center;background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;">
                <div style="font-size:24px;font-weight:700;color:#2563eb;">{{ $stats['open_issues'] }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Open Issues</div>
            </td>
        </tr>
    </table>

    @php
        $listLimit = $stats['list_limit'] ?? 10;
    @endphp

    {{-- Overdue Tasks --}}
    @if($overdueTasks->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#ef4444;font-size:14px;">Overdue Tasks</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #fecaca;border-radius:6px;overflow:hidden;margin-bottom:20px;">
        <tr style="background-color:#fef2f2;">
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fecaca;">Task</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fecaca;">Due</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fecaca;">Priority</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fecaca;">Project</td>
        </tr>
        @foreach($overdueTasks as $task)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #fef2f2;">
                <a href="{{ url('/admin/task-detail/' . $task->id) }}" style="color:#1e293b;text-decoration:none;font-weight:600;">{{ $task->title }}</a>
            </td>
            <td style="padding:10px 14px;font-size:13px;color:#ef4444;border-bottom:1px solid #fef2f2;white-space:nowrap;">{{ $task->due_date->format('M d') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #fef2f2;white-space:nowrap;">{{ ucfirst($task->priority?->value ?? 'medium') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #fef2f2;">{{ $task->project?->name ?? '—' }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- Due Soon --}}
    @if($dueSoonTasks->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#f59e0b;font-size:14px;">Due Soon</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #fde68a;border-radius:6px;overflow:hidden;margin-bottom:8px;">
        <tr style="background-color:#fffbeb;">
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fde68a;">Task</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fde68a;">Due</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fde68a;">Project</td>
        </tr>
        @foreach($dueSoonTasks as $task)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #fffbeb;">
                <a href="{{ url('/admin/task-detail/' . $task->id) }}" style="color:#1e293b;text-decoration:none;font-weight:600;">{{ $task->title }}</a>
            </td>
            <td style="padding:10px 14px;font-size:13px;color:#f59e0b;border-bottom:1px solid #fffbeb;white-space:nowrap;">{{ $task->due_date->format('M d, g:ia') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #fffbeb;">{{ $task->project?->name ?? '—' }}</td>
        </tr>
        @endforeach
    </table>
    @if($stats['due_soon'] > $dueSoonTasks->count())
    <p style="margin:0 0 20px;color:#9ca3af;font-size:12px;">Showing {{ $dueSoonTasks->count() }} of {{ $stats['due_soon'] }}</p>
    @else
    <div style="margin-bottom:20px;"></div>
    @endif
    @endif

    {{-- Open Tasks --}}
    @if($openTasks->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#1e293b;font-size:14px;">Open Tasks</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;margin-bottom:8px;">
        <tr style="background-color:#f8fafc;">
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Task</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Status</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Due</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #e2e8f0;">Project</td>
        </tr>
        @foreach($openTasks as $task)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;">
                <a href="{{ url('/admin/task-detail/' . $task->id) }}" style="color:#1e293b;text-decoration:none;font-weight:600;">{{ $task->title }}</a>
            </td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap;">{{ $task->currentState?->name ?? '—' }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap;">{{ $task->due_date?->format('M d') ?? '—' }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">{{ $task->project?->name ?? '—' }}</td>
        </tr>
        @endforeach
    </table>
    @if($stats['open_tasks'] > $openTasks->count())
    <p style="margin:0 0 20px;color:#9ca3af;font-size:12px;">Showing {{ $openTasks->count() }} of {{ $stats['open_tasks'] }}</p>
    @else
    <div style="margin-bottom:20px;"></div>
    @endif
    @endif

    {{-- Open Issues --}}
    @if($openIssues->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#2563eb;font-size:14px;">Open Issues</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #bfdbfe;border-radius:6px;overflow:hidden;margin-bottom:8px;">
        <tr style="background-color:#eff6ff;">
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #bfdbfe;">ID</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #bfdbfe;">Issue</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #bfdbfe;">Status</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #bfdbfe;">Priority</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #bfdbfe;">Project</td>
        </tr>
        @foreach($openIssues as $issue)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#2563eb;border-bottom:1px solid #eff6ff;white-space:nowrap;font-weight:600;">
                <a href="{{ route('issues') }}" style="color:#2563eb;text-decoration:none;">#{{ $issue->id }}</a>
            </td>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #eff6ff;">
                <a href="{{ route('issues') }}" style="color:#1e293b;text-decoration:none;font-weight:600;">{{ $issue->title }}</a>
            </td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #eff6ff;white-space:nowrap;">{{ ($issue->status ?? \Modules\Issues\Enums\IssueStatus::New)->label() }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #eff6ff;white-space:nowrap;">{{ ucfirst($issue->priority?->value ?? 'medium') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #eff6ff;">{{ $issue->project?->name ?? '—' }}</td>
        </tr>
        @endforeach
    </table>
    @if($stats['open_issues'] > $openIssues->count())
    <p style="margin:0 0 20px;color:#9ca3af;font-size:12px;">Showing {{ $openIssues->count() }} of {{ $stats['open_issues'] }}</p>
    @else
    <div style="margin-bottom:20px;"></div>
    @endif
    @endif

    {{-- Upcoming Deadlines --}}
    @if($upcomingDeadlines->isNotEmpty())
    <h3 style="margin:0 0 10px;color:#f59e0b;font-size:14px;">Upcoming Project Deadlines</h3>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #fde68a;border-radius:6px;overflow:hidden;margin-bottom:20px;">
        <tr style="background-color:#fffbeb;">
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fde68a;">Project</td>
            <td style="padding:8px 14px;font-size:11px;font-weight:600;color:#64748b;border-bottom:1px solid #fde68a;">Deadline</td>
        </tr>
        @foreach($upcomingDeadlines as $project)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #fffbeb;">{{ $project->name }}@if($project->code) <span style="color:#94a3b8;">({{ $project->code }})</span>@endif</td>
            <td style="padding:10px 14px;font-size:13px;color:#f59e0b;border-bottom:1px solid #fffbeb;white-space:nowrap;">{{ $project->deadline_at->format('M d, Y') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">This digest covers tasks and issues assigned to you, plus projects you're on.</p>
@endsection
