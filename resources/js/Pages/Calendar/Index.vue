<script setup>
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import multiMonthPlugin from '@fullcalendar/multimonth';
import thLocale from '@fullcalendar/core/locales/th';
import { ref } from 'vue';

const props = defineProps({
    events: Array,
});

// --- State ---
const showEventModal = ref(false);
const showDayModal = ref(false);
const showExportModal = ref(false); // ✨ เพิ่ม State สำหรับ Modal Export
const selectedEvent = ref(null);
const selectedDayDate = ref(null);
const selectedDayEvents = ref([]);

// ✨ State สำหรับ Form Export
const exportForm = ref({
    type: 'month',
    date: new Date().toISOString().slice(0, 10)
});

// --- Functions ---
const openEventDetail = (eventObj) => {
    showDayModal.value = false;
    selectedEvent.value = {
        title: eventObj.title,
        start: eventObj.start,
        end: eventObj.end,
        url: eventObj.url,
        ... (eventObj.extendedProps || eventObj)
    };
    showEventModal.value = true;
};

const handleMoreLinkClick = (info) => {
    selectedDayDate.value = info.date;
    selectedDayEvents.value = info.allSegs.map(seg => ({
        title: seg.event.title,
        start: seg.event.start,
        end: seg.event.end,
        url: seg.event.url,
        extendedProps: seg.event.extendedProps,
        backgroundColor: seg.event.backgroundColor,
        borderColor: seg.event.borderColor
    }));
    showDayModal.value = true;
    return 'function';
};

const closeEventModal = () => { showEventModal.value = false; selectedEvent.value = null; };
const closeDayModal = () => { showDayModal.value = false; selectedDayEvents.value = []; };
const goToDetail = () => { if (selectedEvent.value?.url) window.location.href = selectedEvent.value.url; };

const formatDate = (date) => date ? new Date(date).toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
const formatShortDate = (date) => date ? new Date(date).toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long' }) : '-';

// ✨ เปลี่ยนฟังก์ชันปุ่ม Export ให้เปิด Modal แทน
const openExportModal = () => {
    showExportModal.value = true;
};

// ✨ ฟังก์ชันสั่ง Export จริงๆ
const submitExport = () => {
    const url = route('calendar.export-agenda', {
        type: exportForm.value.type,
        date: exportForm.value.date
    });
    window.open(url, '_blank');
    showExportModal.value = false;
};

// --- Calendar Config ---
const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin, multiMonthPlugin],
    initialView: 'dayGridMonth',
    locale: thLocale,
    eventOrder: 'displayOrder',
    dayMaxEvents: 4,
    moreLinkClick: handleMoreLinkClick,

    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'multiMonthYear,dayGridMonth,timeGridWeek,listWeek'
    },

    buttonText: {
        today: 'วันนี้',
        year: 'รายปี',
        month: 'เดือน',
        week: 'สัปดาห์',
        list: 'รายการ',
    },

    views: {
        multiMonthYear: {
            multiMonthMaxColumns: 4,
            titleFormat: { year: 'numeric' }
        }
    },

    events: props.events,
    editable: false,
    selectable: true,
    weekends: true,

    eventContent: function(arg) {
        let contentEl = document.createElement('div');
        if (arg.view.type === 'multiMonthYear') {
             if (arg.event.extendedProps.type === 'work_item') {
                 contentEl.innerHTML = `<div class="w-2 h-2 rounded-full mx-auto mt-0.5" style="background-color:${arg.event.backgroundColor}"></div>`;
             } else {
                 contentEl.innerHTML = `<div class="text-[8px] text-center text-red-600 font-bold">!</div>`;
             }
             contentEl.title = arg.event.title;
             return { domNodes: [contentEl] };
        }
        if (arg.event.extendedProps.type === 'work_item') {
             contentEl.innerHTML = `
                <div class="flex items-center justify-between px-1 overflow-hidden text-xs">
                    <span class="truncate font-medium">${arg.event.title}</span>
                    <span class="text-[9px] bg-white/20 px-1 rounded ml-1">${arg.event.extendedProps.progress}</span>
                </div>`;
        } else {
            contentEl.innerHTML = `
                <div class="flex items-center px-1 text-xs font-bold text-gray-900">
                    <i class="mr-1">⚠️</i> <span class="truncate">${arg.event.title}</span>
                </div>`;
        }
        return { domNodes: [contentEl] }
    },

    eventClick: function(info) {
        info.jsEvent.preventDefault();
        openEventDetail(info.event);
    }
});
</script>

<template>
    <Head title="ปฏิทินรวมงาน" />

    <PeaSidebarLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 class="font-bold text-2xl text-[#4A148C] leading-tight">ปฏิทินรวมงาน (Project Calendar)</h2>
                        <p class="text-gray-500 text-sm mt-1">ติดตามกำหนดการ แผนงาน และรายการแก้ไขปัญหาทั้งหมด</p>
                    </div>

                    <button @click="openExportModal" class="flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg font-bold shadow-sm hover:bg-gray-50 transition">
                        <svg class="w-5 h-5 text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        พิมพ์ Agenda
                    </button>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl border border-gray-100 flex flex-col md:flex-row">
                    <div class="w-full md:w-64 p-6 border-b md:border-b-0 md:border-r border-gray-100 bg-gray-50/50">
                        <h3 class="font-bold text-lg mb-4 text-[#4A148C] flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            คำอธิบาย (Legend)
                        </h3>
                        <div class="space-y-4 text-sm">
                            <div>
                                <p class="text-xs font-bold text-gray-400 uppercase mb-2">สถานะแผนงาน</p>
                                <div class="flex items-center mb-2">
                                    <span class="w-3 h-3 rounded-full bg-[#3b82f6] mr-2 shadow-sm"></span>
                                    <span>แผนงาน (Plan)</span>
                                </div>
                                <div class="flex items-center mb-2">
                                    <span class="w-3 h-3 rounded-full bg-[#8b5cf6] mr-2 shadow-sm"></span>
                                    <span>โครงการ (Project)</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-[#10b981] mr-2 shadow-sm"></span>
                                    <span>งานย่อย (Task)</span>
                                </div>
                            </div>
                            <div class="border-t border-gray-200 pt-3">
                                <p class="text-xs font-bold text-gray-400 uppercase mb-2">ปัญหา/ความเสี่ยง</p>
                                <div class="flex items-center mb-2">
                                    <span class="w-3 h-3 rounded-full bg-[#ef4444] mr-2 shadow-sm"></span>
                                    <span>วิกฤต (Critical)</span>
                                </div>
                                <div class="flex items-center mb-2">
                                    <span class="w-3 h-3 rounded-full bg-[#f97316] mr-2 shadow-sm"></span>
                                    <span>สูง (High)</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-[#eab308] mr-2 shadow-sm"></span>
                                    <span>ปานกลาง (Medium)</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 p-4 bg-purple-50 rounded-xl border border-purple-100 text-purple-800 text-xs">
                             <p><strong>Tips:</strong> กดที่ปุ่ม "รายปี" เพื่อดูภาพรวมทั้งปี งานในมุมมองรายปีจะแสดงเป็นจุดสีเล็กๆ เพื่อความสะอาดตา</p>
                        </div>
                    </div>

                    <div class="flex-1 p-6 bg-white relative z-0">
                        <div class="calendar-theme-wrapper">
                            <FullCalendar :options="calendarOptions" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showExportModal" @close="showExportModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4 border-b pb-2 flex items-center gap-2"><span>🖨️</span> เลือกรูปแบบรายงาน</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">ประเภทช่วงเวลา</label>
                        <select v-model="exportForm.type" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <option value="day">รายวัน (Daily)</option>
                            <option value="week">รายสัปดาห์ (Weekly)</option>
                            <option value="month">รายเดือน (Monthly)</option>
                            <option value="year">รายปี (Yearly)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">เลือกวันที่อ้างอิง</label>
                        <input type="date" v-model="exportForm.date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                        <p class="text-xs text-gray-500 mt-2 bg-gray-50 p-2 rounded">
                            <span v-if="exportForm.type === 'year'">ℹ️ ระบบจะพิมพ์ทั้งปี (ม.ค.-ธ.ค.) ของปีที่เลือก พร้อมแยกหมวดเดือน</span>
                            <span v-else>ℹ️ ระบบจะพิมพ์ช่วงเวลาตามที่เลือก</span>
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showExportModal = false">ยกเลิก</SecondaryButton>
                    <PrimaryButton @click="submitExport" class="bg-[#4A148C] hover:bg-[#380d6b]">ดาวน์โหลด PDF</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showDayModal" @close="closeDayModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-4">
                    <div>
                         <h2 class="text-lg font-bold text-gray-900">รายการงานวันที่</h2>
                        <p class="text-purple-700 font-medium">{{ formatShortDate(selectedDayDate) }}</p>
                    </div>
                    <button @click="closeDayModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-2 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-for="(event, index) in selectedDayEvents" :key="index" @click="openEventDetail(event)" class="flex items-center p-3 rounded-lg border border-gray-100 hover:shadow-md hover:border-purple-200 cursor-pointer transition-all bg-white group">
                        <div class="w-2 h-10 rounded-full mr-3" :style="{ backgroundColor: event.backgroundColor }"></div>
                        <div class="flex-1 min-w-0">
                             <div class="flex items-center gap-2 mb-1">
                                <span v-if="event.extendedProps.type === 'issue'" class="text-[10px] font-bold uppercase text-red-600 bg-red-50 px-1.5 py-0.5 rounded">Issue</span>
                                <span v-else class="text-[10px] font-bold uppercase text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ event.extendedProps.work_type }}</span>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-800 truncate group-hover:text-purple-700">{{ event.title }}</h3>
                        </div>
                        <div class="ml-2"><svg class="w-5 h-5 text-gray-300 group-hover:text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></div>
                    </div>
                    <div v-if="selectedDayEvents.length === 0" class="text-center py-8 text-gray-400">ไม่มีรายการงาน</div>
                </div>
            </div>
        </Modal>

        <Modal :show="showEventModal" @close="closeEventModal">
            <div class="p-6">
                <div v-if="selectedEvent">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span v-if="selectedEvent.type === 'issue'" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-red-100 text-red-700 border border-red-200">Issue / Risk</span>
                                <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-700 border border-blue-200">{{ selectedEvent.work_type }}</span>
                                <span class="text-xs text-gray-500">{{ formatDate(selectedEvent.start) }}</span>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ selectedEvent.title }}</h2>
                            <p v-if="selectedEvent.parent_name" class="text-sm text-gray-500 mt-1">งานที่เกี่ยวข้อง: <span class="font-medium text-purple-700">{{ selectedEvent.parent_name }}</span></p>
                        </div>
                        <button @click="closeEventModal" class="text-gray-400 hover:text-gray-600 transition"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <div class="space-y-4 border-t border-gray-100 pt-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div><p class="text-xs font-bold text-gray-400 uppercase">สถานะ</p><p class="font-medium capitalize text-gray-800">{{ selectedEvent.status }}</p></div>
                            <div v-if="selectedEvent.type === 'work_item'"><p class="text-xs font-bold text-gray-400 uppercase">ความคืบหน้า</p><p class="font-medium text-green-600">{{ selectedEvent.progress }}</p></div>
                            <div v-if="selectedEvent.type === 'issue'"><p class="text-xs font-bold text-gray-400 uppercase">ระดับความรุนแรง</p><p class="font-medium capitalize" :class="{'text-red-600': selectedEvent.severity === 'critical', 'text-orange-500': selectedEvent.severity === 'high', 'text-yellow-600': selectedEvent.severity === 'medium', 'text-green-600': selectedEvent.severity === 'low'}">{{ selectedEvent.severity }}</p></div>
                        </div>
                        <div><p class="text-xs font-bold text-gray-400 uppercase mb-1">รายละเอียด</p><div class="bg-gray-50 p-3 rounded-lg text-sm text-gray-700 leading-relaxed max-h-40 overflow-y-auto">{{ selectedEvent.description || 'ไม่มีรายละเอียดเพิ่มเติม' }}</div></div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="closeEventModal">ปิดหน้าต่าง</SecondaryButton>
                        <PrimaryButton @click="goToDetail" class="gap-2 bg-[#4A148C] hover:bg-[#380d6b]">ไปที่หน้างาน <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg></PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </PeaSidebarLayout>
</template>

<style>
/* ✨ ธีมปุ่มสีม่วง/เหลืองที่คุณต้องการ (คงไว้เหมือนเดิม) */
.fc-toolbar-title { font-size: 1.5rem !important; font-weight: 700 !important; color: #4A148C !important; }

/* ปุ่มปกติ: สีม่วง */
.fc-button-primary { background-color: #4A148C !important; border-color: #4A148C !important; font-weight: 600 !important; transition: all 0.2s; }
.fc-button-primary:hover { background-color: #380d6b !important; border-color: #380d6b !important; }

/* ปุ่มที่ถูกเลือก (Active): สีเหลือง ตัวหนังสือม่วง */
.fc-button-primary:not(:disabled).fc-button-active, .fc-button-primary:not(:disabled):active { background-color: #FDB913 !important; border-color: #FDB913 !important; color: #4A148C !important; }

.fc-day-today { background-color: #f3e8ff !important; }
.fc-theme-standard td, .fc-theme-standard th { border-color: #f3f4f6 !important; }
.fc-col-header-cell-cushion { color: #4b5563; font-weight: 600; padding-top: 8px !important; padding-bottom: 8px !important; text-decoration: none !important; }
.fc-event { cursor: pointer; border: none !important; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); border-radius: 4px; margin-bottom: 2px !important; }
.fc-header-toolbar { margin-bottom: 1.5em !important; }
.fc-popover { display: none !important; }

/* Multi Month View Adjustments */
.fc-multimonth-title { font-size: 1rem !important; font-weight: 700 !important; color: #4A148C !important; }
.fc-multimonth-header { background-color: #f9fafb !important; }
</style>
