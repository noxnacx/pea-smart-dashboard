<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import throttle from 'lodash/throttle';

const props = defineProps({
    logs: Object,
    filters: Object,
    actions: Array,
    systems: Array
});

const form = ref({
    user_search: props.filters.user_search || '',
    action: props.filters.action || '',
    model: props.filters.model || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

watch(form, throttle(() => {
    router.get(route('audit-logs.index'), form.value, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
}, 500), { deep: true });

// --- Helper Functions ---

const getDate = (date) => new Date(date).toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: '2-digit' });
const getTime = (date) => new Date(date).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

const formatLogValue = (val) => {
    if (val === null || val === undefined || val === '') return '-';
    if (val === true) return 'Yes (จริง)';
    if (val === false) return 'No (เท็จ)';

    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) {
        const d = new Date(val);
        return d.toLocaleString('th-TH', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    return val;
};

const ignoredFields = [
    'ev', 'pv', 'sv', 'performance_status',
    'id', 'created_at', 'deleted_at'
];

const shouldShowField = (key) => {
    return !ignoredFields.includes(key);
};

const fieldLabels = {
    name: 'ชื่อรายการ',
    description: 'รายละเอียด',
    status: 'สถานะ',
    progress: 'ความคืบหน้า (%)',
    budget: 'งบประมาณ',
    planned_start_date: 'วันเริ่มแผน',
    planned_end_date: 'วันจบแผน',
    weight: 'น้ำหนักงาน',
    is_active: 'สถานะ Active',
    parent_id: 'ID งานแม่',
    division_id: 'ID กอง',
    department_id: 'ID แผนก',
    project_manager_id: 'ID ผู้ดูแล',
    updated_at: 'เวลาแก้ไขล่าสุด',
    file_name: 'ชื่อไฟล์',
    file_size: 'ขนาดไฟล์',
    body: 'ข้อความ',
    comment: 'หมายเหตุ',
    message: 'ข้อความ',
    file_type: 'ประเภทไฟล์',
    note: 'บันทึก'
};

const getFieldName = (key) => fieldLabels[key] || key;

const actionColor = (action) => {
    switch(action) {
        case 'CREATE': return 'bg-green-100 text-green-700 border-green-200';
        case 'UPDATE':
        case 'UPDATE_PROGRESS': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'DELETE': return 'bg-red-100 text-red-700 border-red-200';
        case 'EXPORT':
        case 'DOWNLOAD': return 'bg-purple-100 text-purple-700 border-purple-200';
        case 'UPLOAD': return 'bg-indigo-100 text-indigo-700 border-indigo-200';
        default: return 'bg-gray-100 text-gray-600 border-gray-200';
    }
};

const getRoleBadge = (role) => {
    switch(role) {
        case 'admin': return 'bg-purple-100 text-[#7A2F8F] border-purple-200';
        case 'pm': return 'bg-orange-100 text-orange-700 border-orange-200';
        default: return 'bg-green-100 text-green-700 border-green-200';
    }
};
</script>

<template>
    <Head title="Audit Logs" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1800px] mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-end border-b border-gray-200 pb-4 gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">🔒 บันทึกการใช้งาน (Audit Logs)</h2>
                    <p class="text-gray-500 mt-1">ตรวจสอบประวัติการใช้งานและกิจกรรมทั้งหมดในระบบ</p>
                </div>
                <button @click="form = { user_search: '', action: '', model: '', start_date: '', end_date: '' }" class="text-sm text-gray-500 hover:text-[#7A2F8F] underline">
                    ล้างตัวกรองทั้งหมด
                </button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">ค้นหาผู้ทำรายการ (ชื่อ)</label>
                    <div class="relative">
                        <input type="text" v-model="form.user_search" placeholder="พิมพ์ชื่อ..." class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F] pl-8">
                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">การกระทำ (Action)</label>
                    <select v-model="form.action" class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]">
                        <option value="">ทั้งหมด</option>
                        <option value="CREATE">สร้าง (Create)</option>
                        <option value="UPDATE">แก้ไข (Update)</option>
                        <option value="UPDATE_PROGRESS">อัปเดตความคืบหน้า (Progress)</option>
                        <option value="DELETE">ลบ (Delete)</option>
                        <option value="EXPORT">ดาวน์โหลด/ส่งออก (Export)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">ระบบ (System)</label>
                    <select v-model="form.model" class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]">
                        <option value="">ทั้งหมด</option>
                        <option value="WorkItem">โครงการ/งาน</option>
                        <option value="User">ผู้ใช้งาน</option>
                        <option value="Attachment">ไฟล์แนบ</option>
                        <option value="Comment">ความคิดเห็น</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-gray-500 mb-1">ตั้งแต่วันที่</label>
                        <input type="date" v-model="form.start_date" class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]">
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-gray-500 mb-1">ถึงวันที่</label>
                        <input type="date" v-model="form.end_date" class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]">
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-200 uppercase text-xs">
                        <tr>
                            <th class="p-4 w-24">วันที่</th>
                            <th class="p-4 w-24 border-l border-gray-100">เวลา</th>
                            <th class="p-4 w-40 border-l border-gray-100">ผู้ทำรายการ</th>
                            <th class="p-4 w-32 border-l border-gray-100 text-center">ตำแหน่ง</th>
                            <th class="p-4 w-32 border-l border-gray-100">IP Address</th>
                            <th class="p-4 w-24 text-center border-l border-gray-100">Action</th>
                            <th class="p-4 w-40 border-l border-gray-100">ประเภท</th>
                            <th class="p-4 border-l border-gray-100 w-full">รายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="logs.data.length === 0">
                            <td colspan="8" class="p-8 text-center text-gray-400">ไม่พบประวัติการใช้งานตามเงื่อนไข</td>
                        </tr>
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-purple-50 transition">
                            <td class="p-4 text-gray-600 font-mono">{{ getDate(log.created_at) }}</td>
                            <td class="p-4 text-gray-500 font-mono border-l border-gray-100">{{ getTime(log.created_at) }}</td>

                            <td class="p-4 border-l border-gray-100 font-bold text-[#4A148C] text-sm truncate max-w-[200px]" :title="log.user ? log.user.name : 'System'">
                                {{ log.user ? log.user.name : 'System/Guest' }}
                            </td>

                            <td class="p-4 border-l border-gray-100 text-center">
                                <span v-if="log.user" class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase border" :class="getRoleBadge(log.user.role)">{{ log.user.role }}</span>
                                <span v-else class="text-gray-400 text-xs">-</span>
                            </td>

                            <td class="p-4 text-gray-400 text-xs font-mono border-l border-gray-100">{{ log.ip_address || '-' }}</td>

                            <td class="p-4 text-center border-l border-gray-100">
                                <span class="px-2 py-1 rounded text-[10px] font-bold border" :class="actionColor(log.action)">
                                    {{ (log.action === 'DOWNLOAD' || log.action === 'EXPORT') ? 'EXPORT' : log.action }}
                                </span>
                            </td>

                            <td class="p-4 border-l border-gray-100 text-xs">
                                <span class="font-bold text-gray-700 block">{{ log.model_type }}</span>
                                <span class="text-gray-400 text-[10px]" v-if="log.model_id > 0">ID: #{{ log.model_id }}</span>
                            </td>

                            <td class="p-4 border-l border-gray-100 text-xs text-gray-600 whitespace-normal min-w-[350px]">

                                <div v-if="log.target_name" class="font-bold text-[#4A148C] mb-2 pb-1 border-b border-gray-100 flex items-center gap-2">
                                    📂 {{ log.target_name }}
                                </div>

                                <div v-if="log.changes">
                                    <div v-if="(log.action === 'UPDATE' || log.action === 'UPDATE_PROGRESS') && log.changes.after" class="space-y-1.5">
                                        <template v-for="(val, key) in log.changes.after" :key="key">
                                            <div v-if="shouldShowField(key)" class="flex items-start flex-wrap gap-1">
                                                <span class="font-bold text-gray-700 min-w-[80px]">{{ getFieldName(key) }}:</span>
                                                <span v-if="log.changes.before && log.changes.before[key] !== undefined" class="text-red-400 line-through bg-red-50 px-1 rounded">{{ formatLogValue(log.changes.before[key]) }}</span>
                                                <span v-if="log.changes.before && log.changes.before[key] !== undefined" class="text-gray-400">➜</span>
                                                <span class="text-green-600 font-bold bg-green-50 px-1 rounded">{{ formatLogValue(val) }}</span>
                                            </div>
                                        </template>
                                    </div>

                                    <div v-else-if="log.action === 'EXPORT' || log.action === 'DOWNLOAD'" class="flex flex-col gap-1 items-start">
                                        <div class="text-blue-600 flex items-center gap-2 font-bold bg-blue-50 p-2 rounded w-fit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            <span>ดาวน์โหลด/ส่งออกข้อมูล</span>
                                        </div>
                                        <div v-if="log.changes && (log.changes.filename || log.changes.file_name || log.changes.file_type || log.changes.note)" class="text-gray-500 text-[11px] ml-1 mt-1 space-y-0.5">
                                            <div v-if="log.changes.filename || log.changes.file_name">📄 ไฟล์: <span class="font-bold text-gray-700">{{ log.changes.filename || log.changes.file_name }}</span></div>
                                            <div v-if="log.changes.file_type">🏷️ ประเภท: {{ log.changes.file_type }}</div>
                                            <div v-if="log.changes.note">📝 บันทึก: {{ log.changes.note }}</div>
                                        </div>
                                    </div>

                                    <div v-else-if="log.changes.after || (!log.changes.before && !log.changes.after)" class="space-y-1">
                                        <template v-for="(val, key) in (log.changes.after || log.changes)" :key="key">
                                            <div v-if="shouldShowField(key)">
                                                <span class="font-bold text-gray-700">{{ getFieldName(key) }}:</span>
                                                <span class="ml-1">{{ formatLogValue(val) }}</span>
                                            </div>
                                        </template>
                                    </div>
                                    <div v-else class="text-gray-400 italic">- ไม่มีรายละเอียด -</div>
                                </div>
                                <div v-else class="text-gray-400 italic">- ไม่มีรายละเอียดเพิ่มเติม -</div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center bg-gray-50" v-if="logs.links.length > 3">
                    <div class="flex gap-1">
                        <Link v-for="(link, i) in logs.links" :key="i" :href="link.url || '#'" v-html="link.label"
                              class="px-3 py-1 rounded text-xs border transition"
                              :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : (link.url ? 'bg-white hover:bg-gray-100 text-gray-600 border-gray-300' : 'text-gray-400 border-gray-200')"/>
                    </div>
                    <div class="text-xs text-gray-500">แสดง {{ logs.from }} ถึง {{ logs.to }} จากทั้งหมด {{ logs.total }} รายการ</div>
                </div>
            </div>

        </div>
    </PeaSidebarLayout>
</template>
