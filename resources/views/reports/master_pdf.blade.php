<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>รายงานสรุปโครงการ PEA</title>
    <style>
        /* นำเข้าฟอนต์ภาษาไทย Sarabun */
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

        body {
            font-family: "THSarabunNew", sans-serif;
            font-size: 16pt;
            color: #333;
            line-height: 1.2;
        }

        h1, h2, h3 { font-weight: bold; margin: 0; }
        h1 { font-size: 24pt; color: #4A148C; }

        .header { text-align: center; margin-bottom: 20px; }
        .info-bar { margin-bottom: 10px; border-bottom: 2px solid #FDB913; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #4A148C; color: white; text-align: center; }

        .badge { padding: 2px 5px; border-radius: 4px; font-size: 12pt; color: white; }
        .bg-green { background-color: #28a745; }
        .bg-blue { background-color: #007bff; }
        .bg-gray { background-color: #6c757d; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 12pt; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h1>รายงานสรุปแผนงานและโครงการ (PEA SMART)</h1>
        <p>ข้อมูล ณ วันที่ {{ $date }}</p>
    </div>

    <div class="info-bar">
        <strong>สรุปภาพรวม:</strong>
        โครงการทั้งหมด {{ $stats['total'] }} โครงการ |
        เสร็จสิ้น {{ $stats['completed'] }} |
        งบประมาณรวม {{ number_format($stats['budget'], 2) }} บาท
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40%">ชื่อรายการ (Work Structure)</th>
                <th style="width: 15%">สถานะ</th>
                <th style="width: 25%">ระยะเวลา</th>
                <th style="width: 10%">ความคืบหน้า</th>
                <th style="width: 15%">งบประมาณ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($strategies as $strategy)
                <tr style="background-color: #f3e5f5;">
                    <td colspan="5"><strong>{{ $strategy->name }}</strong></td>
                </tr>

                @foreach($strategy->children as $plan)
                    <tr>
                        <td style="padding-left: 20px;">• {{ $plan->name }}</td>
                        <td class="text-center">{{ strtoupper($plan->status) }}</td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($plan->planned_start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($plan->planned_end_date)->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $plan->progress }}%</td>
                        <td class="text-right">{{ number_format($plan->budget) }}</td>
                    </tr>

                    @foreach($plan->children as $project)
                        <tr>
                            <td style="padding-left: 40px; color: #555;">- {{ $project->name }}</td>
                            <td class="text-center">{{ strtoupper($project->status) }}</td>
                            <td class="text-center">{{ \Carbon\Carbon::parse($project->planned_start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($project->planned_end_date)->format('d/m/Y') }}</td>
                            <td class="text-center">{{ $project->progress }}%</td>
                            <td class="text-right">{{ number_format($project->budget) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        เอกสารนี้ถูกสร้างโดยระบบอัตโนมัติ PEA Smart Dashboard
    </div>
</body>
</html>
