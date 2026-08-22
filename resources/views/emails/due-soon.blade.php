@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Tasks Due Soon</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, the following {{ Str::plural('task', count($tasks)) }} {{ count($tasks) === 1 ? 'is' : 'are' }} due within 24 hours:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
        <tr style="background-color:#f8fafc;">
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Task</td>
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Due Date</td>
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Project</td>
        </tr>
        @foreach($tasks as $task)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ $task->title }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#f59e0b;border-bottom:1px solid #f1f5f9;">{{ $task->due_date->format('M d, Y') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">{{ $task->project?->name ?? '—' }}</td>
        </tr>
        @endforeach
    </table>
@endsection
