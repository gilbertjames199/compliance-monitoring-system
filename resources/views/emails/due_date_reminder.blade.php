{{-- <p>Good day,</p>

<p>
This is a reminder that the requirement
<strong>{{ $document->requirement }}</strong>
from
<strong>{{ $requirement->agency_name }}</strong>
is due on
<strong>{{ \Carbon\Carbon::parse($document->due_date)->format('F d, Y') }}</strong>.
</p>

<p>Please ensure compliance before the deadline.</p>

<p>
Regards,<br>
<strong>Compliance Monitoring System</strong>
</p> --}}

<div class="max-w-xl mx-auto bg-white border rounded-lg shadow-sm overflow-hidden font-sans">

    <!-- Header -->
    <div class="bg-blue-600 text-white px-6 py-4">
        <h2 class="text-lg font-semibold">
            Compliance Reminder
        </h2>
    </div>

    <!-- Body -->
    <div class="p-6 text-gray-700">

        <p class="mb-4">
            Good day,
        </p>

        <p class="mb-4">
            This is a reminder that the following requirement is approaching its deadline:
        </p>

        <!-- Requirement Card -->
        <div class="bg-gray-50 border-l-4 border-blue-600 rounded-md p-4 mb-5">

            <div class="mb-3">
                <p class="text-sm text-gray-500">
                    Requirement
                </p>
                <p class="font-semibold text-gray-800">
                    {{ $document->requirement }}
                </p>
            </div>

            <div class="mb-3">
                <p class="text-sm text-gray-500">
                    Requiring Agency
                </p>
                <p class="font-medium text-gray-800">
                    {{ $requirement->agency_name }}
                </p>
            </div>

            <div>
                <p class="text-sm text-gray-500">
                    Due Date
                </p>
                <p class="font-semibold text-red-600">
                    {{ \Carbon\Carbon::parse($document->due_date)->format('F d, Y') }}
                </p>
            </div>

        </div>

        <p class="mb-4">
            Please ensure compliance before the deadline to avoid delays or issues.
        </p>

        <p class="mb-6">
            Thank you.
        </p>

        <p>
            Regards,<br>
            <span class="font-semibold">
                Compliance Monitoring System
            </span>
        </p>

    </div>

    <!-- Footer -->
    <div class="bg-gray-100 text-gray-500 text-sm px-6 py-3">
        This is an automated message. Please do not reply.
    </div>

</div>