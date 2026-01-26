<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>รายงานสรุปผู้บริหาร</title>
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
        body { font-family: "THSarabunNew"; font-size: 16pt; color: #333; }

        .box-container { width: 100%; overflow: hidden; margin-bottom: 30px; }
        .box {
            float: left; width: 30%; margin-right: 3%; padding: 20px 10px;
            background: #f8f9fa; border: 1px solid #ddd; text-align: center; border-radius: 8px;
        }
        .box h3 { margin: 0; font-size: 14pt; color: #666; font-weight: normal; }
        .box h1 { margin: 10px 0; font-size: 32pt; color: #4A148C; font-weight: bold; line-height: 1; }

        h2 { color: #4A148C; border-bottom: 2px solid #FDB913; padding-bottom: 5px; margin-top: 30px; font-size: 18pt; }

        .progress-bar { width: 100%; background-color: #e0e0e0; border-radius: 10px; height: 16px; margin-top: 5px; overflow: hidden; }
        .progress-fill { height: 100%; background-color: #4A148C; text-align: center; line-height: 16px; color: white; font-size: 10pt; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: #4A148C; font-size: 24pt; margin: 0;">รายงานสรุปผู้บริหาร (Executive Summary)</h1>
        <p style="margin: 5px 0; color: #666;">PEA Smart Dashboard | ประจำวันที่ {{ $date }}</p>
    </div>

    <div class="box-container">
        <div class="box">
            <h3>โครงการทั้งหมด</h3>
            <h1>{{ $stats['total'] }}</h1>
        </div>
        <div class="box">
            <h3>งบประมาณรวม (MB)</h3>
            <h1 style="color: #2E7D32;">{{ number_format($stats['budget'] / 1000000, 2) }}</h1>
        </div>
        <div class="box" style="margin-right: 0;">
            <h3>ปัญหาวิกฤต</h3>
            <h1 style="color: #C62828;">{{ $stats['critical_issues'] }}</h1>
        </div>
    </div>

    <h2>โครงการสำคัญ (Top Projects by Budget)</h2>
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
        @foreach($topProjects as $project)
        <tr>
            <td style="border-bottom: 1px solid #eee; padding: 12px 0;">
                <div style="font-weight: bold; font-size: 14pt;">{{ $project->name }}</div>
                <div style="color: #666; font-size: 12pt;">งบประมาณ: {{ number_format($project->budget) }} บาท</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $project->progress }}%;"></div>
                </div>
            </td>
            <td style="width: 20%; text-align: right; border-bottom: 1px solid #eee; vertical-align: middle;">
                <span style="font-size: 16pt; font-weight: bold; color: #4A148C;">{{ $project->progress }}%</span>
                <div style="font-size: 12pt; color: #888;">{{ strtoupper($project->status) }}</div>
            </td>
        </tr>
        @endforeach
    </table>
</body>
</html>
