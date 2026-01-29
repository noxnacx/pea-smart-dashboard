<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import throttle from 'lodash/throttle';

const props = defineProps({
    logs: Object,
    filters: Object
});

// ✅ ปรับ Form ให้รับ start_date และ end_date
const form = ref({
    user_search: props.filters.user_search || '',
    action: props.filters.action || '',
    model: props.filters.model || '',
    start_date: props.filters.start_date || '', // วันเริ่มต้น
    end_date: props.filters.end_date || '',     // วันสิ้นสุด
});

watch(form, throttle(() => {
    router.get(route('audit-logs.index'), form.value, {
        preserveState: true,
        replace: true,
    });
}, 500), { deep: true });

// --- Helper Functions ---
const actionColor = (action) => {
    switch(action) {
        case 'CREATE': return 'bg-green-100 text-green-700 border-green-200';
        case 'UPDATE': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
        case 'DELETE': return 'bg-red-100 text-red-700 border-red-200';
        case 'EXPORT':
        case 'DOWNLOAD': return 'bg-blue-100 text-blue-700 border-blue-200';
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

const getDate = (date) => new Date(date).toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit', year: '2-digit' });
const getTime = (date) => new Date(date).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
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
                        <option value="DELETE">ลบ (Delete)</option>
                        <option value="DOWNLOAD_ALL">⬇️ การดาวน์โหลด (Downloads)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">ระบบ (System)</label>
                    <select v-model="form.model" class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]">
                        <option value="">ทั้งหมด</option>
                        <option value="WorkItem">โครงการ/งาน (WorkItem)</option>
                        <option value="User">ผู้ใช้งาน (User)</option>
                        <option value="Report">รายงาน (Report)</option>
                        <option value="Attachment">ไฟล์แนบ (Attachment)</option>
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
                            <th class="p-4 w-40 border-l border-gray-100">รายการ</th>
                            <th class="p-4 border-l border-gray-100">รายละเอียด</th>
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
                                <span class="px-2 py-1 rounded text-[10px] font-bold border" :class="actionColor(log.action)">{{ log.action }}</span>
                            </td>

                            <td class="p-4 border-l border-gray-100 text-xs">
                                <span class="font-bold text-gray-700 block">{{ log.target_name || log.model_type }}</span>
                                <span class="text-gray-400 text-[10px]" v-if="log.model_id > 0">ID: #{{ log.model_id }}</span>
                            </td>

                            <td class="p-4 text-xs font-mono text-gray-600 border-l border-gray-100">
                                <div v-if="log.action === 'UPDATE' && log.changes && log.changes.after">
                                    <div v-for="(val, key) in log.changes.after" :key="key" class="mb-0.5">
                                        <span class="font-bold text-gray-800">{{ key }}:</span>
                                        <span class="text-red-400 line-through mx-1">{{ log.changes.before[key] }}</span> -> <span class="text-green-600 font-bold ml-1">{{ val }}</span>
                                    </div>
                                </div>

                                <div v-else-if="log.action === 'EXPORT' || log.action === 'DOWNLOAD'" class="text-blue-600 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    <span>ดาวน์โหลด PDF</span>
                                    <span v-if="log.changes && log.changes.filename" class="text-gray-400 text-[10px] ml-1">({{ log.changes.filename }})</span>
                                </div>

                                <div v-else-if="log.action === 'CREATE'" class="text-green-600 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg> สร้างรายการใหม่
                                </div>

                                <div v-else-if="log.action === 'DELETE'" class="text-red-600 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg> ลบข้อมูล
                                </div>
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
