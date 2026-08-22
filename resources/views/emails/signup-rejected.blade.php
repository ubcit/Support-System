@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">Account request rejected</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        Hi {{ $user->name }}, your account request was rejected by {{ $reviewerName }}.
    </p>

    <div style="background-color:#fef2f2;border-radius:6px;border:1px solid #fecaca;padding:14px 16px;margin:0 0 16px;">
        <p style="margin:0;color:#7f1d1d;font-size:14px;white-space:pre-wrap;">
            <strong>Message:</strong> {{ $rejectionMessage }}
        </p>
    </div>

    <p style="margin:0;color:#64748b;font-size:14px;">
        If you believe this is a mistake, please contact your admin team.
    </p>

    <a href="{{ url('/login') }}"
       style="display:inline-block;margin-top:16px;background:#6b7280;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:500;">
        Back to sign in
    </a>
@endsection

