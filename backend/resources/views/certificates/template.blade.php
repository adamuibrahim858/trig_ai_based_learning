<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        @page {
            size: letter landscape;
            margin: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background-color: #0f172a;
            color: #f8fafc;
            box-sizing: border-box;
            height: 100%;
        }
        .border-container {
            border: 4px solid #38bdf8;
            padding: 40px 60px;
            text-align: center;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 12px;
            box-sizing: border-box;
        }
        .header {
            font-size: 16px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #38bdf8;
            margin-bottom: 12px;
        }
        .title {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #ffffff;
            margin: 0 0 20px 0;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 16px;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .student-name {
            font-size: 34px;
            font-weight: 700;
            color: #38bdf8;
            border-bottom: 2px solid #334155;
            display: inline-block;
            padding-bottom: 8px;
            margin-bottom: 24px;
        }
        .course-title {
            font-size: 24px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 12px;
        }
        .description {
            font-size: 14px;
            color: #cbd5e1;
            max-width: 600px;
            margin: 0 auto 30px auto;
            line-height: 1.6;
        }
        .footer-table {
            width: 100%;
            margin-top: 30px;
            border-top: 1px solid #334155;
            padding-top: 20px;
        }
        .footer-table td {
            vertical-align: middle;
            color: #94a3b8;
            font-size: 12px;
        }
        .cert-number {
            font-family: monospace;
            font-size: 13px;
            color: #38bdf8;
            font-weight: bold;
        }
        .score-badge {
            background-color: #0369a1;
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="border-container">
        <div class="header">Antigravity Learning Academy</div>
        <h1 class="title">Certificate of Mastery</h1>
        <div class="subtitle">This is officially presented to</div>
        
        <div class="student-name">{{ $studentName }}</div>
        
        <div class="description">
            For successfully completing all eight weeks of rigorous curriculum, practical assessments, and passing the comprehensive final examination in:
        </div>

        <div class="course-title">{{ $courseTitle }}</div>

        <div>
            <span class="score-badge">Final Examination Score: {{ $finalScore }}%</span>
        </div>

        <table class="footer-table">
            <tr>
                <td style="text-align: left; width: 33%;">
                    <div>Issue Date: <strong>{{ $issuedDate }}</strong></div>
                    <div style="margin-top: 4px;">Certificate ID: <span class="cert-number">{{ $certificateNumber }}</span></div>
                </td>
                <td style="text-align: center; width: 34%;">
                    <div style="font-style: italic; color: #cbd5e1;">"Scientia Potentia Est"</div>
                </td>
                <td style="text-align: right; width: 33%;">
                    <div>Verification URL:</div>
                    <div style="font-size: 11px; word-break: break-all; color: #38bdf8;">{{ $verifyUrl }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
