<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compliance Monitoring System</title>
</head>

<body style="margin:0; padding:0; background-color:#f5f3d9; font-family: Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3d9; padding:20px 0;">
<tr>
<td align="center">

    <!-- MAIN CONTAINER -->
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border-radius:10px; overflow:hidden;">

        <!-- HEADER -->
        <tr>
            <td style="background: linear-gradient(135deg, #a86b00, #f2b705); padding:20px; text-align:center; color:#ffffff;">
                <div style="font-size:18px; font-weight:bold; letter-spacing:1px;">
                    COMPLIANCE MONITORING SYSTEM
                </div>
                <div style="font-size:12px; margin-top:5px;">
                    Provincial Government of Davao de Oro
                </div>
            </td>
        </tr>

        <!-- BODY -->
        <tr>
            <td style="padding:30px;">

                <h2 style="margin:0 0 10px; color:#a86b00;">Compliance Deadline Reminder</h2>

                <p style="font-size:14px; color:#555; line-height:1.6;">
                    Good day <strong>{{ $user->name }}</strong> from <strong>{{ $office }}</strong>,<br><br>
                    This is a reminder regarding an upcoming compliance requirement. Please review the details below and ensure timely submission.
                </p>

                <!-- TABLE -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px; border-collapse:collapse;">
                    
                    <tr>
                        <td style="padding:10px; background:#f4f4f4; font-weight:bold; font-size:13px;">Requirement</td>
                        <td style="padding:10px; font-size:13px;">{{ $requirement->requirement }}</td>
                    </tr>

                    <tr>
                        <td style="padding:10px; background:#f9f9f9; font-weight:bold; font-size:13px;">Requiring Agency</td>
                        <td style="padding:10px; font-size:13px;">{{ $requirement->agency_name }}</td>
                    </tr>

                    <tr>
                        <td style="padding:10px; background:#f4f4f4; font-weight:bold; font-size:13px;">Start Date</td>
                        <td style="padding:10px; font-size:13px;">
                            {{ \Carbon\Carbon::parse($requirement->start_date)->format('F d, Y') }}
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px; background:#f9f9f9; font-weight:bold; font-size:13px;">Deadline</td>
                        <td style="padding:10px; font-size:13px; color:#d35400; font-weight:bold;">
                            {{ \Carbon\Carbon::parse($requirement->due_date)->format('F d, Y') }}
                        </td>
                    </tr>

                </table>

                <!-- NOTICE -->
                <div style="margin-top:25px; padding:15px; background:#fff3e0; border-left:4px solid #f2b705; font-size:13px; color:#555;">
                    Please ensure that all required documents and actions are completed 
                    <strong>on or before the deadline</strong> to maintain compliance.
                </div>

                 <!-- ✅ NEW: ACCESS LINK -->
                <div style="margin-top:20px; padding:15px; background:#fdf6e3; border-left:4px solid #a86b00; font-size:13px; color:#555; line-height:1.8;">
                    You may access the Compliance Monitoring System using your <strong>OPCR credentials</strong>:<br><br>
                    <a href="https://cms.davaodeoro.gov.ph/"
                       style="display:inline-block; background: linear-gradient(135deg, #a86b00, #f2b705); color:#ffffff; padding:10px 22px; border-radius:6px; text-decoration:none; font-weight:bold; font-size:13px;">
                        &#128279; Access CMS Portal
                    </a>
                    <br><br>
                    <span style="font-size:12px; color:#888;">
                        Or copy this link: 
                        <a href="https://cms.davaodeoro.gov.ph/" style="color:#a86b00; text-decoration:none;">
                            https://cms.davaodeoro.gov.ph/
                        </a>
                    </span>
                </div>

            </td>
        </tr>

        <!-- FOOTER -->
        <tr>
            <td style="background:#f0f0f0; padding:15px; text-align:center; font-size:12px; color:#777;">
                <strong style="color:#a86b00;">Compliance Monitoring System</strong><br>
                Provincial Government of Davao de Oro<br><br>
                This is an automated email. Please do not reply.
            </td>
        </tr>

    </table>

</td>
</tr>
</table>

</body>
</html>