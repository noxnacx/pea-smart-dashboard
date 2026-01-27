<script setup>
import { onMounted, ref, onUnmounted, watch } from 'vue';
import { gantt } from 'dhtmlx-gantt';
import 'dhtmlx-gantt/codebase/dhtmlxgantt.css';
import axios from 'axios';

const props = defineProps({
    taskId: Number,
});

const ganttContainer = ref(null);
const isFullscreen = ref(false);

const loadData = async () => {
    if (!props.taskId) return;
    try {
        gantt.clearAll();
        const response = await axios.get(route('work-items.gantt-data', props.taskId));
        gantt.parse(response.data);
    } catch (error) {
        console.error("Error loading Gantt data:", error);
    }
};

// ✅ แก้ไข: สลับการเรียกฟังก์ชันให้ตรงกับความรู้สึก (Zoom In = ขยายดูรายละเอียด)
const zoomIn = () => {
    gantt.ext.zoom.zoomIn();
};

const zoomOut = () => {
    gantt.ext.zoom.zoomOut();
};

const fitToScreen = () => {
    const range = gantt.getSubtaskDates();
    if (!range.start_date || !range.end_date) return;

    const start = gantt.date.add(range.start_date, -1, "month");
    const end = gantt.date.add(range.end_date, 1, "month");

    gantt.config.start_date = start;
    gantt.config.end_date = end;

    // คำนวณเลือกระดับการ Zoom ให้เหมาะกับระยะเวลาโปรเจกต์
    const totalMonths = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
    if (totalMonths > 60) {
        gantt.ext.zoom.setLevel("year");
    } else if (totalMonths > 24) {
        gantt.ext.zoom.setLevel("quarter");
    } else if (totalMonths > 3) {
        gantt.ext.zoom.setLevel("month");
    } else {
        gantt.ext.zoom.setLevel("week");
    }

    gantt.render();
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;

    setTimeout(() => {
        if (isFullscreen.value) {
            fitToScreen();
        } else {
            gantt.config.start_date = undefined;
            gantt.config.end_date = undefined;
            gantt.ext.zoom.setLevel("month");
            gantt.render();
        }
    }, 200);
};

onMounted(() => {
    // 1. เปิด Plugin Tooltip
    gantt.plugins({
        tooltip: true
    });

    gantt.config.date_format = "%Y-%m-%d";

    gantt.config.columns = [
        { name: "text", label: "ชื่องาน", width: "*", tree: true },
        { name: "start_date", label: "เริ่ม", align: "center", width: 80 },
        { name: "end_date", label: "สิ้นสุด", align: "center", width: 80 },
        { name: "duration", label: "ระยะเวลา", align: "center", width: 60 },
    ];

    // ✅ 2. แก้ไข Template Tooltip (บังคับสีตัวอักษรให้เข้มชัดเจน)
    gantt.templates.tooltip_text = function(start, end, task) {
        const progress = Math.round((task.progress || 0) * 100);
        // เพิ่ม style='color: #1f2937' เพื่อแก้ปัญหาตัวหนังสือสีขาวมองไม่เห็น
        return `
            <div class='font-sans text-sm' style='color: #1f2937; text-align: left;'>
                <div class='font-bold mb-1' style='color: #4A148C; border-bottom: 1px solid #eee; padding-bottom: 4px;'>${task.text}</div>
                <div style='margin-top: 4px;'><b>📅 เริ่ม:</b> ${gantt.templates.tooltip_date_format(start)}</div>
                <div><b>🏁 สิ้นสุด:</b> ${gantt.templates.tooltip_date_format(end)}</div>
                <div><b>⏳ ระยะเวลา:</b> ${task.duration} วัน</div>
                <div class='mt-1'><b>📊 ความคืบหน้า:</b> ${progress}%</div>
            </div>
        `;
    };
    gantt.templates.tooltip_date_format = gantt.date.date_to_str("%d %F %Y");

    // ตั้งค่า Level การ Zoom
    const zoomConfig = {
        levels: [
            {
                name: "year",
                scale_height: 50,
                min_column_width: 30,
                scales: [
                    { unit: "year", step: 1, format: "%Y" }
                ]
            },
            {
                name: "quarter",
                height: 50,
                min_column_width: 90,
                scales: [
                    { unit: "year", step: 1, format: "%Y" },
                    { unit: "quarter", step: 1, format: function (date) {
                        return "Q" + (Math.floor(date.getMonth() / 3) + 1);
                    }}
                ]
            },
            {
                name: "month",
                scale_height: 50,
                min_column_width: 120,
                scales: [
                    { unit: "month", format: "%F %Y" },
                    { unit: "week", format: "Week %W" }
                ]
            },
            {
                name: "week",
                scale_height: 50,
                min_column_width: 50,
                scales: [
                    { unit: "month", format: "%F %Y" },
                    { unit: "day", step: 1, format: "%d %D" }
                ]
            },
            {
                name: "day",
                scale_height: 27,
                min_column_width: 80,
                scales: [
                    { unit: "month", format: "%F %Y" },
                    { unit: "day", step: 1, format: "%d" }
                ]
            }
        ]
    };

    gantt.ext.zoom.init(zoomConfig);
    gantt.ext.zoom.setLevel("month");

    // ตั้งค่าภาษาไทย
    gantt.i18n.setLocale({
        date: {
            month_full: ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"],
            month_short: ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."],
            day_full: ["อาทิตย์", "จันทร์", "อังคาร", "พุธ", "พฤหัสบดี", "ศุกร์", "เสาร์"],
            day_short: ["อา.", "จ.", "อ.", "พ.", "พฤ.", "ศ.", "ส."]
        },
        labels: {
            new_task: "รายการใหม่",
            icon_save: "บันทึก",
            icon_cancel: "ยกเลิก",
            icon_details: "รายละเอียด",
            icon_edit: "แก้ไข",
            icon_delete: "ลบ",
            gantt_save_btn: "บันทึก",
            gantt_cancel_btn: "ยกเลิก",
            gantt_delete_btn: "ลบ",
            confirm_closing: "การแก้ไขของคุณจะหายไป คุณแน่ใจหรือไม่?",
            confirm_deleting: "คุณแน่ใจหรือไม่ที่จะลบรายการนี้?",
            section_description: "ชื่อรายการ",
            section_time: "ช่วงเวลา",
            minutes: "นาที", hours: "ชั่วโมง", days: "วัน", weeks: "สัปดาห์", months: "เดือน", years: "ปี"
        }
    });

    gantt.init(ganttContainer.value.querySelector('#gantt_here'));
    loadData();
});

watch(() => props.taskId, () => loadData());

onUnmounted(() => {
    gantt.clearAll();
});
</script>

<template>
    <div class="flex flex-col h-full bg-white relative transition-all duration-300"
         :class="{ 'is-fullscreen-mode': isFullscreen }"
         ref="ganttContainer">

        <div class="bg-gray-100 p-2 flex justify-between items-center border-b border-gray-300 z-10 shrink-0">
            <div class="flex gap-2">
                <button @click="zoomOut" class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-200 text-gray-700 font-medium shadow-sm transition">+ Zoom In</button>
                <button @click="zoomIn" class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-200 text-gray-700 font-medium shadow-sm transition">- Zoom Out</button>
            </div>
            <div>
                <button @click="toggleFullscreen" class="flex items-center gap-2 px-4 py-1.5 bg-[#4A148C] text-white rounded text-sm font-bold hover:bg-[#380d6b] shadow-sm transition">
                    <span v-if="!isFullscreen">⛶ ขยายเต็มจอ</span>
                    <span v-else>❌ ออกจากเต็มจอ</span>
                </button>
            </div>
        </div>

        <div id="gantt_here" class="flex-1 w-full relative h-full"></div>
    </div>
</template>

<style>
/* CSS DHTMLX Customization */
.gantt_task_line {
    border-radius: 4px;
    border: 1px solid #4A148C;
}
.gantt_task_progress {
    background-color: rgba(0, 0, 0, 0.2);
}
.gantt_task_line.gantt_project {
    background-color: #4A148C;
    border-color: #380d6b;
}
.gantt_grid_head_cell {
    font-weight: bold;
    color: #4A148C;
}
.gantt_line_wrapper div {
    background-color: #FDB913;
}
.gantt_link_arrow {
    border-color: #FDB913;
}

/* Modal Styling */
.gantt_cal_light {
    z-index: 10001 !important;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    font-family: 'Sarabun', sans-serif !important;
}
.gantt_cal_cover {
    z-index: 10000 !important;
}
.gantt_cal_lsection .gantt_cal_ltext select {
    color: #1f2937 !important;
    font-weight: 600;
    border: 1px solid #d1d5db;
    padding: 4px;
    border-radius: 4px;
}
.gantt_btn_set.gantt_left_btn_set.gantt_save_btn_set {
    background: #4A148C;
    color: white;
    border-radius: 4px;
}
.gantt_btn_set.gantt_left_btn_set.gantt_cancel_btn_set {
    background: #f3f4f6;
    color: #374151;
    border-radius: 4px;
    margin-left: 10px;
}
.gantt_btn_set.gantt_right_btn_set.gantt_delete_btn_set {
    background: #ef4444;
    color: white;
    border-radius: 4px;
}
.gantt_popup_button {
    text-shadow: none !important;
}

/* ✅ Tooltip Styling: ปรับแต่งให้สวยงามและมองเห็นชัด */
.gantt_tooltip {
    z-index: 10002 !important;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    background-color: #ffffff !important; /* บังคับพื้นหลังขาว */
    color: #1f2937 !important; /* บังคับตัวหนังสือดำ */
    font-family: 'Sarabun', sans-serif;
    line-height: 1.5;
}

/* Fullscreen Mode */
.is-fullscreen-mode {
    position: fixed !important;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 9999 !important;
    background: white;
    padding: 0 !important;
    margin: 0 !important;
}
</style>
