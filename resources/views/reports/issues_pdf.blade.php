<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>รายงานปัญหาและความเสี่ยง</title>
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: normal;
            src: url("{{ public_path('fonts/THSarabunNew.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: bold;
            src: url("{{ public_path('fonts/THSarabunNew Bold.ttf') }}") format('truetype');
        }
        body { font-family: "THSarabunNew"; font-size: 14pt; }
        h1 { color: #C62828; font-weight: bold; font-size: 20pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #C62828; color: white; text-align: center; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>รายงานสรุปปัญหาและความเสี่ยง (Issue & Risk Log)</h1>
        <p>PEA Smart Dashboard | ข้อมูล ณ วันที่ {{ $date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="30%">ปัญหา/ความเสี่ยง</th>
                <th width="15%">ความรุนแรง</th>
                <th width="15%">สถานะ</th>
                <th width="25%">โครงการที่เกี่ยวข้อง</th>
                <th width="15%">กำหนดแก้ไข</th>
            </tr>
        </thead>
        <tbody>
            @foreach($issues as $issue)
            <tr>
                <td>
                    <strong>{{ $issue->title }}</strong><br>
                    <span style="color: #666; font-size: 12pt;">{{ \Illuminate\Support\Str::limit($issue->description, 100) }}</span>
                </td>
                <td style="text-align: center; font-weight: bold; color: {{ $issue->severity == 'critical' ? '#C62828' : ($issue->severity == 'high' ? '#E65100' : '#333') }}">
                    {{ strtoupper($issue->severity) }}
                </td>
                <td style="text-align: center;">{{ strtoupper($issue->status) }}</td>
                <td>{{ $issue->workItem ? $issue->workItem->name : '-' }}</td>
                <td style="text-align: center;">
                    {{ $issue->end_date ? \Carbon\Carbon::parse($issue->end_date)->format('d/m/Y') : '-' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
