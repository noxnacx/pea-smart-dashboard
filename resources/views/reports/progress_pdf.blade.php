<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>รายงานความก้าวหน้าโครงการ</title>
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

        body {
            font-family: "THSarabunNew", sans-serif;
            font-size: 14pt;
            color: #333;
            line-height: 1.2;
        }

        h1 { font-size: 20pt; color: #4A148C; margin: 0; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #FDB913; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background-color: #4A148C; color: white; text-align: center; font-weight: bold; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .badge { padding: 2px 6px; border-radius: 4px; color: white; font-size: 10pt; font-weight: bold; }
        .bg-green { background-color: #22c55e; }
        .bg-blue { background-color: #3b82f6; }
        .bg-gray { background-color: #9ca3af; }

        .footer { position: fixed; bottom: 0; width: 100%; text-align: right; font-size: 10pt; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <h1>รายงานความก้าวหน้าโครงการ (Project Progress Report)</h1>
        <div>PEA Smart Dashboard | ข้อมูล ณ วันที่ {{ $date }}</div>
    </div>

    <div style="background-color: #f3f4f6; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #e5e7eb;">
        <strong>สรุปภาพรวม:</strong>
        โครงการทั้งหมด {{ $stats['total'] }} |
        เสร็จสิ้น {{ $stats['completed'] }} |
        งบประมาณรวม {{ number_format($stats['budget'], 2) }} บาท
    </div>

    <table>
        <thead>
            <tr>
                <th width="40%">ชื่อรายการ (Work Structure)</th>
                <th width="10%">สถานะ</th>
                <th width="20%">ระยะเวลา</th>
                <th width="15%">ความคืบหน้า</th>
                <th width="15%">งบประมาณ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($strategies as $strategy)
                <tr style="background-color: #f3e5f5;">
                    <td colspan="5" style="color: #4A148C;"><strong>{{ $strategy->name }}</strong></td>
                </tr>

                @foreach($strategy->children as $plan)
                    <tr style="background-color: #fff;">
                        <td style="padding-left: 20px;">
                            <strong>&#9658; {{ $plan->name }}</strong>
                        </td>
                        <td class="text-center">{{ strtoupper($plan->status) }}</td>
                        <td class="text-center" style="font-size: 12pt;">
                            {{ $plan->planned_start_date ? \Carbon\Carbon::parse($plan->planned_start_date)->format('d/m/y') : '-' }} -
                            {{ $plan->planned_end_date ? \Carbon\Carbon::parse($plan->planned_end_date)->format('d/m/y') : '-' }}
                        </td>
                        <td class="text-center">
                            {{ $plan->progress }}%
                            <div style="width: 100%; background: #eee; height: 4px; margin-top: 2px;">
                                <div style="width: {{ $plan->progress }}%; background: #4A148C; height: 100%;"></div>
                            </div>
                        </td>
                        <td class="text-right">{{ number_format($plan->budget) }}</td>
                    </tr>

                    @foreach($plan->children as $project)
                        <tr>
                            <td style="padding-left: 40px; color: #555;">
                                - {{ $project->name }}
                            </td>
                            <td class="text-center">
                                @if($project->status == 'completed')
                                    <span style="color: green; font-weight: bold;">DONE</span>
                                @elseif($project->status == 'in_progress')
                                    <span style="color: blue; font-weight: bold;">DOING</span>
                                @else
                                    <span style="color: gray;">TODO</span>
                                @endif
                            </td>
                            <td class="text-center" style="font-size: 12pt;">
                                {{ $project->planned_start_date ? \Carbon\Carbon::parse($project->planned_start_date)->format('d/m/y') : '-' }} -
                                {{ $project->planned_end_date ? \Carbon\Carbon::parse($project->planned_end_date)->format('d/m/y') : '-' }}
                            </td>
                            <td class="text-center">{{ $project->progress }}%</td>
                            <td class="text-right">{{ number_format($project->budget) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        เอกสารอัตโนมัติจากระบบ PEA Smart Dashboard
    </div>
</body>
</html>
