@extends('emails.layout')

@section('content')
    <h2 style="margin:0 0 8px;color:#1e293b;font-size:18px;">You were added to a project</h2>
    <p style="margin:0 0 20px;color:#64748b;font-size:14px;">
        Hi {{ $employee->name }}, you have been added to <strong>{{ $project->name }}</strong>.
    </p>

    @if($project->description)
        <p style="margin:0 0 16px;color:#64748b;font-size:13px;">{{ $project->description }}</p>
    @endif

    <a href="{{ url('/projects') }}"
       style="display:inline-block;margin-top:8px;background:#4f46e5;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:500;">
        Open projects
    </a>
@endsection
