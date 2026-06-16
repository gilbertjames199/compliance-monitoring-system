<x-mail::message>
# Compliance Reminder

Dear **{{ $officeName }}**,

This is an automated daily reminder that your office has a **pending compliance submission**.

<x-mail::panel>
**Requirement:** {{ $document->requirement }}
**Requiring Agency:** {{ $document->agency_name }}
@if($document->due_date)
**Deadline:** {{ \Carbon\Carbon::parse($document->due_date)->format('F d, Y') }}
@endif
@if($document->year)
**Year:** {{ $document->year }}
@endif
</x-mail::panel>

Please submit your compliance documents as soon as possible.

You will continue to receive this reminder **every day** until your submission is recorded.

Thanks,
{{ config('app.name') }}
</x-mail::message>