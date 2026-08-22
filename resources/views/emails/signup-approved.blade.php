@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Account approved</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        Hi {{ $user->name }}, your account has been approved by {{ $reviewerName }}.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc;border-radius:6px;border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:14px;font-size:13px;color:#64748b;width:140px;">Role</td>
            <td style="padding:14px;font-size:13px;color:#1e293b;font-weight:600;">{{ $roleName }}</td>
        </tr>
    </table>

    <p style="margin:18px 0 0;color:#64748b;font-size:14px;">
        You can now sign in and continue your onboarding.
    </p>

    <a href="{{ url('/login') }}"
       style="display:inline-block;margin-top:14px;background:#4f46e5;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:500;">
        Sign in
    </a>
@endsection

