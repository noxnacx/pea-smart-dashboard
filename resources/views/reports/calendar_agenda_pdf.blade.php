<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>รายงานกำหนดการ (Agenda)</title>
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

        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #4A148C; padding-bottom: 10px; }
        .title { font-size: 24pt; font-weight: bold; color: #4A148C; }
        .subtitle { font-size: 16pt; color: #666; }

        /* ✨ ดีไซน์ส่วนหัวเดือน (เฉพาะรายปี) */
        .month-header {
            background-color: #FDB913;
            color: #4A148C;
            font-size: 16pt;
            font-weight: bold;
            padding: 5px 10px;
            margin-top: 20px;
            margin-bottom: 10px;
            border-radius: 4px;
            page-break-after: avoid; /* ห้ามขึ้นหน้าใหม่หลังหัวข้อนี้ */
        }

        .day-row { margin-bottom: 10px; page-break-inside: avoid; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .day-container { display: table; width: 100%; }

        .date-col { display: table-cell; width: 12%; vertical-align: top; text-align: center; border-right: 2px solid #ddd; padding-right: 10px; }
        .events-col { display: table-cell; width: 88%; vertical-align: top; padding-left: 15px; }

        .day-number { font-size: 28pt; font-weight: bold; color: #4A148C; line-height: 0.9; }
        .day-month { font-size: 12pt; color: #4A148C; font-weight: bold; margin-bottom: 2px; } /* ✨ เพิ่มเดือน */
        .day-name { font-size: 12pt; color: #888; text-transform: uppercase; }

        .event-item { margin-bottom: 4px; position: relative; padding-left: 10px; }
        .event-item::before { content: "•"; position: absolute; left: 0; top: 0; color: #ccc; }

        .badge { padding: 1px 4px; border-radius: 3px; font-size: 10pt; font-weight: bold; color: white; margin-right: 5px; }
        .type-project { background-color: #8b5cf6; }
        .type-plan { background-color: #3b82f6; }
        .type-task { background-color: #10b981; }
        .type-issue { background-color: #ef4444; }

        .event-title { font-weight: bold; font-size: 13pt; }
        .issue-text { color: #c62828; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">รายงานปฏิทินงาน (Agenda)</div>
        <div class="subtitle">{{ $reportTitle }}</div>
    </div>

    @php $currentMonth = null; @endphp

    @foreach($calendarData as $day)
        @if($viewType == 'year' && $day['month_full'] !== $currentMonth)
            <div class="month-header">{{ $day['month_full'] }}</div>
            @php $currentMonth = $day['month_full']; @endphp
        @endif

        <div class="day-row">
            <div class="day-container">
                <div class="date-col">
                    <div class="day-number">{{ $day['day'] }}</div>
                    <div class="day-month">{{ $day['month_label'] }}</div>
                    <div class="day-name">{{ $day['weekday'] }}</div>
                </div>

                <div class="events-col">
                    @if(count($day['events']) > 0)
                        @foreach($day['events'] as $event)
                            <div class="event-item">
                                @if($event['type'] == 'issue')
                                    <span class="badge type-issue">ISSUE</span>
                                    <span class="event-title issue-text">{{ $event['title'] }}</span>
                                    <span style="font-size: 11pt; color: #777;">(ความรุนแรง: {{ $event['severity'] }})</span>
                                @else
                                    <span class="badge type-{{ $event['type'] }}">{{ strtoupper($event['type']) }}</span>
                                    <span class="event-title">{{ $event['title'] }}</span>
                                    <span style="font-size: 11pt; color: #777;">({{ $event['status'] }} - {{ $event['progress'] }}%)</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div style="color: #ccc; font-style: italic;">- ไม่มีรายการงาน -</div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    <div style="position: fixed; bottom: 0; right: 0; font-size: 10pt; color: #aaa;">
        เอกสารจากระบบ PEA Smart Dashboard | พิมพ์เมื่อ {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
