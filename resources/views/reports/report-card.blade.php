<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $student->full_name }} — Report Card</title>
    <style>
        /* Dompdf renders a subset of CSS — keep this simple: no flexbox,
           no CSS grid, table-based layout throughout. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a1a; }
        .header { width: 100%; margin-bottom: 16px; border-bottom: 2px solid #1F5C4A; padding-bottom: 10px; }
        .header td { vertical-align: middle; }
        .school-name { font-size: 20px; font-weight: bold; color: #1F5C4A; }
        .school-meta { font-size: 10px; color: #555; }
        .logo { max-height: 60px; }
        .title { text-align: center; font-size: 14px; font-weight: bold; margin: 10px 0; text-transform: uppercase; letter-spacing: 1px; }

        .info-table { width: 100%; margin-bottom: 14px; }
        .info-table td { padding: 2px 0; font-size: 11px; }
        .info-label { color: #555; width: 110px; }

        table.scores { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.scores th, table.scores td { border: 1px solid #ccc; padding: 5px 6px; font-size: 10px; text-align: left; }
        table.scores th { background: #f0f5f3; text-transform: uppercase; font-size: 9px; color: #444; }
        table.scores td.num { text-align: center; }

        .summary-table { width: 100%; margin-top: 6px; margin-bottom: 14px; }
        .summary-box { border: 1px solid #ccc; padding: 8px 10px; width: 32%; }
        .summary-box .label { font-size: 9px; color: #555; text-transform: uppercase; }
        .summary-box .value { font-size: 16px; font-weight: bold; color: #1F5C4A; }

        .draft-banner { text-align: center; color: #b45309; background: #fef3c7; padding: 6px; margin-bottom: 12px; font-size: 10px; font-weight: bold; }

        .footer { margin-top: 30px; font-size: 9px; color: #777; text-align: center; }
    </style>
</head>
<body>
    @if(!$isApproved ?? false)
        <div class="draft-banner">DRAFT — this term's results have not yet been approved/published</div>
    @endif

    <table class="header">
        <tr>
            <td style="width: 70px;">
                @if($logo_data_uri)
                    <img src="{{ $logo_data_uri }}" class="logo">
                @endif
            </td>
            <td>
                <div class="school-name">{{ $school->name }}</div>
                <div class="school-meta">
                    {{ $school->address }}
                    @if($school->phone) &middot; {{ $school->phone }} @endif
                    @if($school->email) &middot; {{ $school->email }} @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="title">Student Report Card</div>

    <table class="info-table">
        <tr>
            <td class="info-label">Student Name</td>
            <td><strong>{{ $student->full_name }}</strong></td>
            <td class="info-label">Admission No.</td>
            <td>{{ $student->admission_number }}</td>
        </tr>
        <tr>
            <td class="info-label">Class</td>
            <td>{{ $student->schoolClass->full_name }}</td>
            <td class="info-label">Term</td>
            <td>{{ $term->name }} — {{ $term->academicSession->name }}</td>
        </tr>
    </table>

    <table class="scores">
        <thead>
            <tr>
                <th>Subject</th>
                <th class="num">CA</th>
                <th class="num">Assignment</th>
                <th class="num">Exam</th>
                <th class="num">Total</th>
                <th class="num">Grade</th>
                <th class="num">Position</th>
                <th>Teacher's Comment</th>
            </tr>
        </thead>
        <tbody>
            @forelse($result['subjects'] as $s)
                <tr>
                    <td>{{ $s['subject_name'] }}</td>
                    <td class="num">{{ $s['ca']['score'] ?? '—' }}</td>
                    <td class="num">{{ $s['assignment']['score'] ?? '—' }}</td>
                    <td class="num">{{ $s['exam']['score'] ?? '—' }}</td>
                    <td class="num"><strong>{{ $s['total_score'] }}</strong></td>
                    <td class="num">{{ $s['grade'] ?? '—' }}</td>
                    <td class="num">{{ $s['position_in_subject'] ?? '—' }} / {{ $s['class_size'] ?? '—' }}</td>
                    <td>{{ $s['teacher_comment'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No scores entered for this term yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-box">
                <div class="label">Grand Total</div>
                <div class="value">{{ $result['grand_total'] ?? '—' }}</div>
            </td>
            <td style="width: 10px;"></td>
            <td class="summary-box">
                <div class="label">Class Position</div>
                <div class="value">{{ $result['overall_position'] ?? '—' }} / {{ $result['class_size'] ?? '—' }}</div>
            </td>
            <td style="width: 10px;"></td>
            <td class="summary-box">
                <div class="label">Attendance</div>
                <div class="value">
                    {{ $attendance['attendance_percentage'] ?? '—' }}{{ $attendance['attendance_percentage'] !== null ? '%' : '' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Generated on {{ now()->format('d M Y') }} — {{ $school->name }}
    </div>
</body>
</html>
