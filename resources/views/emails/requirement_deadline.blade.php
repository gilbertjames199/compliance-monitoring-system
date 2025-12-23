{{-- <h1>Deadline Alert</h1>
<p>The requirement <strong>{{ $requirement->title }}</strong> is due on {{ $requirement->due_date }}.</p> --}}
{{-- 
<h1>Good day,</h1>

<p>
    A new required document has been assigned to your office.
</p>

<p>
    <strong>Document:</strong> {{ $requirement->title }} <br>
    <strong>Deadline:</strong> {{ $requirement->due_date  }}
</p>

<p>
    Please log in to the Compliance Monitoring System to comply.
</p>

<p>Thank you.</p> --}}

<p>Good day,</p>

<p>
This is a reminder that the requirement
<strong>{{ $requirement->requirement }}</strong>
is due on
<strong>{{ \Carbon\Carbon::parse($requirement->due_date)->format('F d, Y') }}</strong>.
</p>

<p>Please ensure compliance before the deadline.</p>

<p>
Regards,<br>
<strong>Compliance Monitoring System</strong>
</p>
