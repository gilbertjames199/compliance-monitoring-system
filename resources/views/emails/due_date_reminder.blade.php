<p>Good day,</p>

<p>
This is a reminder that the requirement
<strong>{{ $document->requirement }}</strong>
is due on
<strong>{{ \Carbon\Carbon::parse($document->due_date)->format('F d, Y') }}</strong>.
</p>

<p>Please ensure compliance before the deadline.</p>

<p>
Regards,<br>
<strong>Compliance Monitoring System</strong>
</p>
