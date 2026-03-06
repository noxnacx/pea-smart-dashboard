<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Milestone Timeline</title>
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
        body { font-family: 'THSarabunNew', sans-serif; color: #333; margin: 0; padding: 20px; }

        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { color: #4A148C; margin: 0; font-size: 26px; }
        .header p { margin: 5px 0; font-size: 16px; color: #666; }

        .timeline-wrapper { width: 100%; margin-bottom: 60px; page-break-inside: avoid; }
        .timeline-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .cell { width: 25%; text-align: center; vertical-align: top; position: relative; padding: 0 10px; }

        .cell-top { vertical-align: bottom; height: 180px; padding-bottom: 5px; }
        .cell-bottom { vertical-align: top; height: 180px; padding-top: 5px; }
        .cell-line { height: 30px; vertical-align: middle; padding: 0; }

        .box { background-color: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; text-align: left; display: inline-block; width: 100%; box-sizing: border-box; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .box-manual { border: 2px solid #a855f7; background-color: #faf5ff; }
        .month-title { background-color: #f3e8ff; color: #4a148c; font-weight: bold; text-align: center; padding: 4px; border-radius: 4px; font-size: 16px; margin-bottom: 8px; }

        .task-item { margin-bottom: 6px; line-height: 1.2; display: table; width: 100%; }
        .task-dot-wrapper { display: table-cell; width: 15px; vertical-align: top; padding-top: 4px; }
        .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
        .desc { display: table-cell; font-size: 15px; color: #444; font-weight: bold; }
        .desc-manual { font-weight: bold; color: #4A148C; font-size: 15px; }

        .line-container { width: 100%; height: 4px; background-color: #cbd5e1; position: relative; margin: 0 auto; }
        .node { width: 20px; height: 20px; border-radius: 50%; border: 3px solid #fff; position: absolute; top: -11px; left: 50%; margin-left: -13px; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }

        .line-up { border-left: 2px solid #ccc; height: 20px; width: 0; margin: 0 auto; margin-top: 5px; }
        .line-down { border-left: 2px solid #ccc; height: 20px; width: 0; margin: 0 auto; margin-bottom: 5px; }

        .legend { text-align: center; font-size: 14px; color: #666; margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; position: fixed; bottom: 10px; width: 100%; }
        .legend-item { display: inline-block; margin: 0 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ลำดับเวลาเป้าหมาย (Delivery Timeline)</h1>
        <p><strong>โครงการ:</strong> {{ $item->name }}</p>
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
        <div style="text-align: center; margin-top: 80px; color: #888; font-size: 20px;">
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
