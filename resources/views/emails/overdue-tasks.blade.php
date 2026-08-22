@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Overdue Tasks Reminder</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">Hi {{ $employee->name }}, you have {{ count($tasks) }} overdue {{ Str::plural('task', count($tasks)) }}:</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;">
        <tr style="background-color:#f8fafc;">
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Task</td>
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Due Date</td>
            <td style="padding:10px 14px;font-size:12px;font-weight:600;color:#64748b;border-bottom:1px solid #e5e7eb;">Priority</td>
        </tr>
        @foreach($tasks as $task)
        <tr>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ $task->title }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#ef4444;border-bottom:1px solid #f1f5f9;">{{ $task->due_date->format('M d, Y') }}</td>
            <td style="padding:10px 14px;font-size:13px;color:#1e293b;border-bottom:1px solid #f1f5f9;">{{ ucfirst($task->priority?->value ?? 'medium') }}</td>
        </tr>
        @endforeach
    </table>

    <p style="margin:20px 0 0;color:#64748b;font-size:13px;">Please review and update these tasks as soon as possible.</p>
@endsection
