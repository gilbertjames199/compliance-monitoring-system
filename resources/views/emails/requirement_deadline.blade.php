<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Compliance Reminder</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f0ece4;
            font-family: 'DM Sans', sans-serif;
        }

        /* Wrapper */
        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 16px;
            width: 100%;
        }

        /* Top Bar */

        .top-bar {
            background-color: #1a2e2a;
            border-radius: 12px 12px 0 0;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .top-bar .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #4caf87;
        }

        .top-bar span {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #a8c4b8;
        }


        /* Card */

        .card {
            background-color: #ffffff;
            padding: 48px 40px;
            border-left: 1px solid #e5e0d8;
            border-right: 1px solid #e5e0d8;
        }

        /* Badge */

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #fff4e5;
            border: 1px solid #f5c97a;
            border-radius: 100px;
            padding: 6px 14px;
            margin-bottom: 24px;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #e89c2f;
        }

        .badge span {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #b07020;
        }

        /* Heading */

        h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 32px;
            color: #1a2e2a;
            margin-bottom: 10px;
        }

        .subheading {
            font-size: 14px;
            color: #8a9e97;
            margin-bottom: 32px;
        }

        /* Divider */

        .divider {
            border: none;
            border-top: 1px solid #ede8e0;
            margin-bottom: 32px;
        }


        /* Info Table */

        .info-grid {
            width: 100%;
            border-spacing: 0 12px;
            margin-bottom: 32px;
        }

        .info-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #a0b0aa;
            padding: 14px 16px;
            border-left: 3px solid #e5efe9;
            background-color: #f8faf9;
            width: 35%;
        }

        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: #1a2e2a;
            padding: 14px 16px;
            background-color: #f8faf9;
        }

        .due-date {
            font-family: 'DM Serif Display', serif;
            font-size: 16px;
            color: #c0521a;
        }


        /* CTA */

        .cta-box {
            background: linear-gradient(135deg,#1a2e2a,#243d36);
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 32px;
        }

        .cta-box p {
            font-size: 14px;
            color: #c8ddd6;
            line-height: 1.6;
        }

        .cta-box strong {
            color: #fff;
        }


        /* Footer */

        .footer-bar {
            background-color: #f8faf9;
            border: 1px solid #e5e0d8;
            border-top: none;
            border-radius: 0 0 12px 12px;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .footer-brand {
            font-family: 'DM Serif Display', serif;
            font-size: 14px;
            color: #1a2e2a;
        }

        .footer-sub {
            font-size: 11px;
            color: #a0b0aa;
            margin-top: 4px;
        }

        .footer-seal {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #1a2e2a;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }

        .footer-seal svg {
            width: 18px;
            fill: #4caf87;
        }



        /* MOBILE */

        @media (max-width:600px){

            .wrapper{
                margin:20px auto;
                padding:0 10px;
            }

            .card{
                padding:30px 20px;
            }

            h1{
                font-size:24px;
            }

            .top-bar{
                padding:12px 16px;
            }

            .footer-bar{
                flex-direction:column;
                align-items:flex-start;
                gap:10px;
            }


            /* Stack Info Table */

            .info-grid tr{
                display:block;
                margin-bottom:12px;
            }

            .info-label,
            .info-value{
                display:block;
                width:100%;
                border-radius:6px;
            }

            .info-label{
                margin-bottom:3px;
            }

        }


        /* EXTRA SMALL */

        @media (max-width:400px){

            h1{
                font-size:22px;
            }

            .badge span{
                font-size:10px;
            }

            .subheading{
                font-size:13px;
            }

        }
</style>
</head>
<body>
  <div class="wrapper">

    <!-- Top Bar -->
    <div class="top-bar">
      <div class="dot"></div>
      <span>Compliance Monitoring System</span>
    </div>

    <!-- Card Body -->
    <div class="card">

      <div class="badge">
        <div class="badge-dot"></div>
        <span>Action Required</span>
      </div>

        <h1>Upcoming Deadline<br>Reminder</h1>
        <p class="subheading">
            Good day {{ $user->name }} from {{ $office }} — please review the details below and ensure timely compliance.
        </p>
      <hr class="divider" />

      <!-- Info Rows -->
      <table class="info-grid" role="presentation">
        <tr class="info-row">
          <td class="info-label">Requirement</td>
          <td class="info-value">{{ $requirement->requirement }}</td>
        </tr>
        <tr class="info-row">
          <td class="info-label">Requiring Agency</td>
          <td class="info-value">{{ $requirement->agency_name }}</td>
        </tr>
         <tr class="info-row">
          <td class="info-label">Start Date</td>
          <td class="info-value due-date">{{ \Carbon\Carbon::parse($requirement->start_date)->format('F d, Y') }}</td>
        </tr>
        <tr class="info-row">
          <td class="info-label">Deadline</td>
          <td class="info-value due-date">{{ \Carbon\Carbon::parse($requirement->due_date)->format('F d, Y') }}</td>
        </tr>
      </table>

      <!-- CTA Box -->
      <div class="cta-box">
        <p>
          Please ensure that all necessary documents and actions related to this requirement are 
          completed and submitted <strong>before the deadline</strong>. 
        </p>
      </div>

    </div>

    <!-- Footer -->
    <div class="footer-bar">
      <div>
        <div class="footer-brand">Compliance Monitoring System</div>
        <div class="footer-sub">This is an automated notification — do not reply.</div>
      </div>
      <div class="footer-seal">
        <!-- Shield check icon -->
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L4 5v6c0 5.25 3.5 10.15 8 11.35C16.5 21.15 20 16.25 20 11V5l-8-3zm-1.5 13.5l-3-3 1.06-1.06 1.94 1.93 4.44-4.43 1.06 1.06-5.5 5.5z"/>
        </svg>
      </div>
    </div>

  </div>
</body>
</html>


