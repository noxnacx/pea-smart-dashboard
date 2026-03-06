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
        body { font-family: "THSarabunNew"; font-size: 14pt; color: #333; line-height: 1.1; margin: 0; padding: 20px; }

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
        .progress-bar-bg { width: 100%; background: #eee; height: 6px; border-radius: 3px; margin-top: 3px; }
        .progress-bar-fill { height: 100%; background: #4A148C; border-radius: 3px; }

        .info-grid { width: 100%; margin-bottom: 15px; }
        .info-grid td { border: none; padding: 4px; }
        .label { font-weight: bold; color: #555; width: 120px; }

        /* --- CSS สำหรับ Milestone (ป้องกันการแตกของ DomPDF) --- */
        .page-break { page-break-before: always; }
        .milestone-header { text-align: center; margin-bottom: 20px; }
        .milestone-header h2 { color: #4A148C; margin: 0; font-size: 24pt; }

        .timeline-wrapper { width: 100%; margin-bottom: 40px; page-break-inside: avoid; }
        .timeline-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .cell { width: 25%; text-align: center; vertical-align: top; position: relative; padding: 0 10px; }

        .cell-top { vertical-align: bottom; height: 160px; padding-bottom: 5px; }
        .cell-bottom { vertical-align: top; height: 160px; padding-top: 5px; }
        .cell-line { height: 30px; vertical-align: middle; padding: 0; }

        .box { background-color: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; text-align: left; display: inline-block; width: 100%; box-sizing: border-box; }
        .box-manual { border: 2px solid #a855f7; background-color: #faf5ff; }
        .month-title { background-color: #f3e8ff; color: #4a148c; font-weight: bold; text-align: center; padding: 4px; border-radius: 4px; font-size: 14pt; margin-bottom: 8px; }

        .task-item { margin-bottom: 6px; line-height: 1.1; display: table; width: 100%; }
        .task-dot-wrapper { display: table-cell; width: 15px; vertical-align: top; padding-top: 3px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
        .desc { display: table-cell; font-size: 13pt; color: #444; }
        .desc-manual { font-weight: bold; color: #4A148C; font-size: 14pt; }

        .line-container { width: 100%; height: 3px; background-color: #cbd5e1; position: relative; margin: 0 auto; }
        .node { width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; position: absolute; top: -8px; left: 50%; margin-left: -11px; }

        .line-up { border-left: 2px solid #ccc; height: 20px; width: 0; margin: 0 auto; margin-top: 5px; }
        .line-down { border-left: 2px solid #ccc; height: 20px; width: 0; margin: 0 auto; margin-bottom: 5px; }

        .legend { text-align: center; font-size: 12pt; color: #666; margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; }
        .legend-item { display: inline-block; margin: 0 10px; }
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

    <div class="page-break"></div>

    <div class="milestone-header">
        <h2>Milestone (เหตุการณ์สำคัญ)</h2>
        <p style="color: #666;">สรุปเป้าหมายและการส่งมอบงานของโครงการ</p>
    </div>

    @php
        function getGroupNodeColor($group) {
            if ($group['manual']) {
                $s = $group['manual']['status'];
                if ($s === 'completed') return '#22c55e'; // เขียว
                if ($s === 'delayed') return '#ef4444'; // แดง
                if ($s === 'in_progress') return '#3b82f6'; // ฟ้า
                if ($s === 'pending' || $s === 'in_active') return '#9ca3af'; // เทา
                return '#a855f7'; // ม่วง (ค่าเผื่อเหลือเผื่อขาด)
            }
            $tasks = $group['tasks'];
            $hasDelayed = false; $hasProgress = false; $allCompleted = true;
            foreach($tasks as $t) {
                if($t['status'] == 'delayed') $hasDelayed = true;
                if($t['status'] == 'in_progress' || $t['progress'] > 0) $hasProgress = true;
                if($t['status'] != 'completed') $allCompleted = false;
            }
            if($hasDelayed) return '#ef4444';
            if($hasProgress) return '#3b82f6';
            if($allCompleted && count($tasks) > 0) return '#22c55e';
            return '#9ca3af';
        }
        function getTaskDotColor($status, $progress) {
            if($status == 'completed' || $progress == 100) return '#22c55e';
            if($status == 'delayed') return '#ef4444';
            if($status == 'in_progress' || $progress > 0) return '#3b82f6';
            return '#9ca3af';
        }
    @endphp

    @if(count($chunkedMilestones) > 0)
        @foreach($chunkedMilestones as $rowChunk)
            <div class="timeline-wrapper">
                <table class="timeline-table">
                    <tr>
                        @foreach($rowChunk as $index => $group)
                            <td class="cell cell-top">
                                @if($index % 2 == 0)
                                    <div class="box {{ $group['manual'] ? 'box-manual' : '' }}">
                                        <div class="month-title">{{ $group['label'] }}</div>

                                        @if($group['manual'])
                                            <div class="task-item">
                                                <div class="task-dot-wrapper">
                                                    <span class="dot" style="background-color: {{ $group['manual']['status'] == 'completed' ? '#22c55e' : '#a855f7' }}"></span>
                                                </div>
                                                <div class="desc desc-manual">{!! nl2br(e($group['manual']['title'])) !!}</div>
                                            </div>
                                        @else
                                            @foreach($group['tasks'] as $task)
                                                <div class="task-item">
                                                    <div class="task-dot-wrapper">
                                                        <span class="dot" style="background-color: {{ getTaskDotColor($task['status'], $task['progress']) }}"></span>
                                                    </div>
                                                    <div class="desc">{{ !empty($task['description']) ? $task['description'] : $task['name'] }}</div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    <div class="line-down"></div>
                                @endif
                            </td>
                        @endforeach
                        @for($i = count($rowChunk); $i < 4; $i++) <td class="cell cell-top"></td> @endfor
                    </tr>

                    <tr>
                        @foreach($rowChunk as $index => $group)
                            <td class="cell cell-line">
                                <div class="line-container">
                                    <div class="node" style="background-color: {{ getGroupNodeColor($group) }}"></div>
                                </div>
                            </td>
                        @endforeach
                        @for($i = count($rowChunk); $i < 4; $i++)
                            <td class="cell cell-line"><div class="line-container"></div></td>
                        @endfor
                    </tr>

                    <tr>
                        @foreach($rowChunk as $index => $group)
                            <td class="cell cell-bottom">
                                @if($index % 2 != 0)
                                    <div class="line-up"></div>
                                    <div class="box {{ $group['manual'] ? 'box-manual' : '' }}">
                                        <div class="month-title">{{ $group['label'] }}</div>

                                        @if($group['manual'])
                                            <div class="task-item">
                                                <div class="task-dot-wrapper">
                                                    <span class="dot" style="background-color: {{ $group['manual']['status'] == 'completed' ? '#22c55e' : '#a855f7' }}"></span>
                                                </div>
                                                <div class="desc desc-manual">{!! nl2br(e($group['manual']['title'])) !!}</div>
                                            </div>
                                        @else
                                            @foreach($group['tasks'] as $task)
                                                <div class="task-item">
                                                    <div class="task-dot-wrapper">
                                                        <span class="dot" style="background-color: {{ getTaskDotColor($task['status'], $task['progress']) }}"></span>
                                                    </div>
                                                    <div class="desc">{{ !empty($task['description']) ? $task['description'] : $task['name'] }}</div>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            </td>
                        @endforeach
                        @for($i = count($rowChunk); $i < 4; $i++) <td class="cell cell-bottom"></td> @endfor
                    </tr>
                </table>
            </div>
        @endforeach
    @else
        <div style="text-align: center; margin-top: 80px; color: #888; font-size: 16pt;">
            -- ยังไม่มีเป้าหมายที่ต้องส่งมอบในขณะนี้ --
        </div>
    @endif

    <div class="legend">
        <div class="legend-item"><span class="dot" style="background-color: #22c55e;"></span> เสร็จสิ้น</div>
        <div class="legend-item"><span class="dot" style="background-color: #3b82f6;"></span> กำลังทำ</div>
        <div class="legend-item"><span class="dot" style="background-color: #ef4444;"></span> ล่าช้า</div>
        <div class="legend-item"><span class="dot" style="background-color: #9ca3af;"></span> รอเริ่ม</div>
        <div class="legend-item"><span class="dot" style="background-color: #a855f7;"></span> กำหนดเอง (Manual)</div>
    </div>
</body>
</html>
