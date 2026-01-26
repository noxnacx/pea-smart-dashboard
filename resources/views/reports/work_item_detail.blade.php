<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $item->name }}</title>
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
        body { font-family: "THSarabunNew"; font-size: 14pt; color: #333; line-height: 1.1; }

        .header { border-bottom: 2px solid #4A148C; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 22pt; font-weight: bold; color: #4A148C; }
        .meta { color: #666; font-size: 12pt; }

        .section-title {
            font-size: 16pt; font-weight: bold; color: #4A148C;
            margin-top: 20px; margin-bottom: 5px;
            border-left: 5px solid #FDB913; padding-left: 10px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background-color: #f3e5f5; color: #4A148C; font-weight: bold; text-align: center; }

        .badge { padding: 2px 5px; border-radius: 4px; color: white; font-size: 10pt; }
        .badge-status { background-color: #3b82f6; }
        .progress-bar-bg { width: 100%; background: #eee; height: 6px; border-radius: 3px; margin-top: 3px; }
        .progress-bar-fill { height: 100%; background: #4A148C; border-radius: 3px; }

        .info-grid { width: 100%; margin-bottom: 15px; }
        .info-grid td { border: none; padding: 4px; }
        .label { font-weight: bold; color: #555; width: 120px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">รายงานรายละเอียดงาน/โครงการ</div>
        <div class="meta">รหัสอ้างอิง: WI-{{ str_pad($item->id, 5, '0', STR_PAD_LEFT) }} | พิมพ์เมื่อ: {{ $date }}</div>
    </div>

    <div style="background-color: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
        <h2 style="margin: 0 0 10px 0; color: #333;">{{ $item->name }}</h2>
        <table class="info-grid">
            <tr>
                <td class="label">ประเภท:</td>
                <td>{{ strtoupper($item->type) }}</td>
                <td class="label">สถานะ:</td>
                <td>{{ strtoupper($item->status) }}</td>
            </tr>
            <tr>
                <td class="label">ระยะเวลา:</td>
                <td>
                    {{ $item->planned_start_date ? \Carbon\Carbon::parse($item->planned_start_date)->format('d/m/Y') : '-' }}
                    ถึง
                    {{ $item->planned_end_date ? \Carbon\Carbon::parse($item->planned_end_date)->format('d/m/Y') : '-' }}
                </td>
                <td class="label">งบประมาณ:</td>
                <td>{{ number_format($item->budget, 2) }} บาท</td>
            </tr>
            <tr>
                <td class="label">ความคืบหน้า:</td>
                <td colspan="3">
                    <div style="display: flex; align-items: center;">
                        <span style="font-weight: bold; color: #4A148C; margin-right: 10px;">{{ $item->progress }}%</span>
                        <div class="progress-bar-bg" style="width: 200px; display: inline-block; vertical-align: middle;">
                            <div class="progress-bar-fill" style="width: {{ $item->progress }}%;"></div>
                        </div>
                    </div>
                </td>
            </tr>
            @if($item->parent)
            <tr>
                <td class="label">ภายใต้งาน:</td>
                <td colspan="3">{{ $item->parent->name }}</td>
            </tr>
            @endif
        </table>
        @if($item->description)
        <div style="margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 10px;">
            <strong>รายละเอียด:</strong><br>
            <span style="color: #555;">{{ $item->description }}</span>
        </div>
        @endif
    </div>

    <div class="section-title">โครงสร้างงานย่อย (Work Breakdown Structure)</div>
    @if($item->children->count() > 0)
    <table>
        <thead>
            <tr>
                <th width="45%">ชื่องาน</th>
                <th width="15%">ประเภท</th>
                <th width="20%">ระยะเวลา</th>
                <th width="10%">สถานะ</th>
                <th width="10%">คืบหน้า</th>
            </tr>
        </thead>
        <tbody>
            @foreach($item->children as $child)
            <tr>
                <td>{{ $child->name }}</td>
                <td style="text-align: center;">{{ $child->type }}</td>
                <td style="text-align: center; font-size: 12pt;">
                    {{ \Carbon\Carbon::parse($child->planned_start_date)->format('d/m/y') }} -
                    {{ \Carbon\Carbon::parse($child->planned_end_date)->format('d/m/y') }}
                </td>
                <td style="text-align: center;">{{ $child->status }}</td>
                <td style="text-align: center;">{{ $child->progress }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="color: #888; text-align: center;">- ไม่มีงานย่อย -</p>
    @endif

    <div class="section-title">รายการปัญหาและความเสี่ยง (Issues & Risks)</div>
    @if($item->issues->count() > 0)
    <table>
        <thead>
            <tr>
                <th width="40%">หัวข้อปัญหา</th>
                <th width="15%">ความรุนแรง</th>
                <th width="15%">สถานะ</th>
                <th width="30%">รายละเอียด</th>
            </tr>
        </thead>
        <tbody>
            @foreach($item->issues as $issue)
            <tr>
                <td>{{ $issue->title }}</td>
                <td style="text-align: center; color: {{ $issue->severity == 'critical' ? 'red' : 'orange' }}; font-weight: bold;">
                    {{ strtoupper($issue->severity) }}
                </td>
                <td style="text-align: center;">{{ $issue->status }}</td>
                <td>{{ $issue->description }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="color: #888; text-align: center;">- ไม่พบรายการปัญหา -</p>
    @endif

    <div class="section-title">รายการเอกสารแนบ (Attachments)</div>
    @if($item->attachments->count() > 0)
    <ul>
        @foreach($item->attachments as $file)
        <li style="margin-bottom: 5px;">
            <strong>{{ $file->file_name }}</strong>
            <span style="color: #888; font-size: 12pt;">(อัปโหลดเมื่อ: {{ $file->created_at->format('d/m/Y H:i') }})</span>
        </li>
        @endforeach
    </ul>
    @else
    <p style="color: #888; text-align: center;">- ไม่มีเอกสารแนบ -</p>
    @endif

</body>
</html>
