<script setup>
import { onMounted, ref, onUnmounted, watch } from 'vue';
import { gantt } from 'dhtmlx-gantt';
import 'dhtmlx-gantt/codebase/dhtmlxgantt.css';
import axios from 'axios';

const props = defineProps({
    taskId: Number, // รับ ID ของ Project แม่
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

const toggleFullscreen = () => {
    if (!ganttContainer.value) return;

    if (!document.fullscreenElement) {
        ganttContainer.value.requestFullscreen().catch(err => {
            console.error(`Error attempting to enable full-screen mode: ${err.message}`);
        });
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

onMounted(() => {
    gantt.config.date_format = "%Y-%m-%d";

    // ตั้งค่า Columns (Grid ขวามือ)
    // ถ้าอยากให้แสดงแค่กราฟอย่างเดียวในช่องขวา (เพราะซ้ายมี Task List แล้ว)
    // สามารถลดขนาดหรือซ่อน Columns ได้ แต่ใส่ไว้ก่อนเพื่อให้ดูง่ายตอน Fullscreen
    gantt.config.columns = [
        { name: "text", label: "ชื่องาน", width: "*", tree: true },
        { name: "start_date", label: "เริ่ม", align: "center", width: 80 },
    ];
    gantt.config.scale_height = 50;
    gantt.config.scales = [
        { unit: "month", step: 1, format: "%F %Y" },
        { unit: "day", step: 1, format: "%d" },
    ];

    gantt.i18n.setLocale({
        labels: {
            new_task: "งานใหม่",
            icon_save: "บันทึก",
            icon_cancel: "ยกเลิก",
            icon_details: "รายละเอียด",
            icon_edit: "แก้ไข",
            icon_delete: "ลบ",
            gantt_save_btn: "บันทึก",
            gantt_cancel_btn: "ยกเลิก",
            gantt_delete_btn: "ลบ",
            confirm_closing: "การแก้ไขของคุณจะหายไป คุณแน่ใจหรือไม่?",
            confirm_deleting: "คุณแน่ใจหรือไม่ที่จะลบงานนี้?",
            section_description: "ชื่อรายการ",
            section_time: "ช่วงเวลา",
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
    <div class="flex flex-col h-full bg-white relative" ref="ganttContainer">
        <div class="bg-gray-100 p-2 flex justify-between items-center border-b border-gray-300 z-10">
            <div class="flex gap-2">
                <button @click="gantt.ext.zoom.zoomOut()" class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-200 text-gray-700 font-medium shadow-sm transition">- Zoom Out</button>
                <button @click="gantt.ext.zoom.zoomIn()" class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-200 text-gray-700 font-medium shadow-sm transition">+ Zoom In</button>
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
/* Ensure fullscreen covers everything */
:fullscreen {
    background-color: white;
}
</style>
