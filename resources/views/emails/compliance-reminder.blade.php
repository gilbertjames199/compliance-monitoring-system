<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Compliance Reminder</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: #f0ece4; font-family: 'Segoe UI', Arial, sans-serif; color: #2C1A0E; padding: 40px 16px; }
        .wrapper { max-width: 600px; margin: 0 auto; border-radius: 10px; overflow: hidden; }

        .band-top { background: linear-gradient(90deg, #8B6914 0%, #C9960C 40%, #E8B422 60%, #C9960C 80%, #8B6914 100%); height: 6px; }

        .header { background: #2C1A0E; padding: 28px 32px 24px; text-align: center; }
        .logo-ring { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #E8B422, #C9960C); margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; border: 3px solid #8B6914; }
        .logo-ring img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; }
        .header h1 { color: #E8B422; font-size: 20px; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px; }
        .header p { color: #C9960C; font-size: 12px; letter-spacing: 1.5px; text-transform: uppercase; }

        .subheader { background: #3D2410; padding: 12px 32px; display: flex; align-items: center; gap: 10px; border-top: 1px solid #5C3A1A; border-bottom: 1px solid #5C3A1A; }
        .subheader-dot { width: 8px; height: 8px; border-radius: 50%; background: #E8B422; flex-shrink: 0; }
        .subheader span { color: #E8B422; font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }

        .body { background: #ffffff; padding: 36px 40px; }

        .letter { font-size: 14px; color: #2C1A0E; line-height: 1.9; }
        .letter p { margin-bottom: 18px; }
        .letter p:last-child { margin-bottom: 0; }
        .letter a { color: #8B6914; text-decoration: underline; word-break: break-all; }
        .letter strong { font-weight: 700; color: #2C1A0E; }

        .divider { border: none; border-top: 1px solid #F5EDD5; margin: 28px 0; }

        .signature { font-size: 13px; color: #6B4A00; line-height: 1.8; }
        .signature strong { display: block; font-size: 14px; color: #2C1A0E; font-weight: 700; }
        .signature span { font-size: 12px; color: #A07C3A; text-transform: uppercase; letter-spacing: 0.8px; }

        .footer-note { margin-top: 28px; background: #FBF3DC; border-radius: 6px; padding: 12px 16px; font-size: 12px; color: #6B4A00; line-height: 1.6; text-align: center; border: 1px solid #E8D5A3; }

        .footer { background: #2C1A0E; padding: 20px 32px; text-align: center; border-top: 4px solid #C9960C; }
        .footer-name { color: #E8B422; font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .footer p { color: #8B6914; font-size: 11px; line-height: 1.7; }

        .band-bottom { background: linear-gradient(90deg, #8B6914 0%, #C9960C 40%, #E8B422 60%, #C9960C 80%, #8B6914 100%); height: 4px; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="band-top"></div>

    
    <div class="body">
        <div class="letter">

            <p>Dear <strong>{{ $officeName }}</strong>,</p>

            <p>
                This is an automated daily notice reminding your office of an overdue compliance
                requirement that your office has not yet acted upon.
            </p>

            <p>
                Your office has not submitted the necessary documents for the requirement titled
                <strong>"{{ $document->requirement }}"</strong>, as required by the
                <strong>{{ $document->agency_name }}</strong>.
                @if($document->due_date)
                    This requirement was due on
                    <strong>{{ \Carbon\Carbon::parse($document->due_date)->format('F d, Y') }}</strong>
                    and your office has yet to comply as of today.
                @else
                    Your office has yet to comply as of today.
                @endif
            </p>

            <p>
                Please log in to the Compliance Monitoring System at https://cms.davaodeoro.gov.ph
                and upload all required documents at your earliest convenience.
            </p>

            <p>
                This reminder will be sent daily until your submission has been recorded in the system.
            </p>

        </div>

        <hr class="divider">

        <div class="signature">
            <strong>{{ config('app.name') }}</strong>
            <span>Automated Notification</span>
        </div>

        <div class="footer-note">
            This is a system-generated email. Please do not reply to this message.
            If you believe you received this in error, please contact your system administrator.
        </div>

    </div>

    <div class="footer">
        <p class="footer-name">{{ config('app.name') }}</p>
        <p>Province of Davao de Oro &nbsp;|&nbsp; © {{ date('Y') }} All rights reserved.</p>
    </div>

    <div class="band-bottom"></div>

</div>
</body>
</html>