@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">New signup request</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        Hi {{ $recipient->name }}, a new account is waiting for your approval.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;width:140px;">Name</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;font-weight:600;">{{ $pendingUser->name }}</td>
        </tr>
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;border-top:1px solid #e5e7eb;">Email</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;border-top:1px solid #e5e7eb;">{{ $pendingUser->email }}</td>
        </tr>
        @if ($pendingUser->phone)
            <tr>
                <td style="padding:14px;font-size:13px;color:#64748b;border-top:1px solid #e5e7eb;">Phone</td>
                <td style="padding:14px;font-size:13px;color:#1e293b;border-top:1px solid #e5e7eb;">{{ $pendingUser->phone }}</td>
            </tr>
        @endif
    </table>

    <a href="{{ route('signup-requests') }}"
       style="display:inline-block;margin-top:14px;background:#4f46e5;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:500;">
        Review signup requests
    </a>
@endsection
