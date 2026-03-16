<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { watch, reactive } from 'vue';
import { debounce } from 'lodash';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    modelData: Object,
    workItems: Object,
    divisions: Array,
    workItemTypes: Array,
    filters: Object,
});

const filterForm = reactive({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    status: props.filters?.status || '',
    division_id: props.filters?.division_id || '',
    progress: props.filters?.progress || '',
});

watch(filterForm, debounce((value) => {
    router.get(
        route('kpis.show', props.modelData.id),
        value,
        { preserveState: true, replace: true }
    );
}, 300), { deep: true });

const resetFilters = () => {
    filterForm.search = '';
    filterForm.type = '';
    filterForm.status = '';
    filterForm.division_id = '';
    filterForm.progress = '';
};

// Helpers
const getStatusBadge = (s) => ({
    completed: 'bg-green-100 text-green-700 border-green-200', delayed: 'bg-red-100 text-red-700 border-red-200',
    in_active: 'bg-gray-100 text-gray-600 border-gray-200', pending: 'bg-gray-100 text-gray-600 border-gray-200',
    in_progress: 'bg-blue-100 text-blue-700 border-blue-200', cancelled: 'bg-gray-200 text-gray-500 border-gray-300'
}[s] || 'bg-gray-100 text-gray-700 border-gray-200');

const getStatusTextTh = (s) => ({
    completed: 'เสร็จสมบูรณ์', delayed: 'ล่าช้า', in_active: 'รอเริ่มดำเนินการ',
    pending: 'รอเริ่มดำเนินการ', in_progress: 'กำลังดำเนินการ', cancelled: 'ยกเลิก'
}[s] || s);
</script>

<template>
    <Head :title="`วิเคราะห์ KPI: ${modelData.name}`" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1600px] mx-auto space-y-6">
            <Link :href="route('kpis.index')" class="text-blue-600 font-bold hover:underline mb-2 inline-flex items-center gap-1">
                &larr; กลับไปหน้าจัดการ KPI
            </Link>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-blue-200 border-l-8 border-l-blue-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-black text-blue-800">🎯 KPI: {{ modelData.name }}</h2>
                    <p class="text-gray-600 mt-2 leading-relaxed">{{ modelData.description || 'ไม่ได้ระบุคำอธิบายเพิ่มเติม' }}</p>
                </div>
                <div class="bg-blue-50 text-blue-800 font-bold px-4 py-2.5 rounded-lg border border-blue-100 whitespace-nowrap shrink-0">
                    พบโครงการ/งานที่เกี่ยวข้องทั้งหมด {{ workItems.total }} รายการ
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <div class="md:col-span-2 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input v-model="filterForm.search" type="text" class="pl-10 w-full rounded-lg border-gray-300 focus:ring-blue-600 focus:border-blue-600 shadow-sm text-sm" placeholder="ค้นหาชื่องาน..." />
                    </div>

                    <div>
                        <select v-model="filterForm.type" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-600 focus:border-blue-600 shadow-sm">
                            <option value="">ทุกประเภท</option>
                            <option v-for="t in workItemTypes" :key="t.key" :value="t.key">{{ t.name }}</option>
                        </select>
                    </div>

                    <div>
                        <select v-model="filterForm.status" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-600 focus:border-blue-600 shadow-sm">
                            <option value="">ทุกสถานะ</option>
                            <option value="pending">รอเริ่มดำเนินการ</option>
                            <option value="in_progress">กำลังดำเนินการ</option>
                            <option value="delayed">ล่าช้า</option>
                            <option value="completed">เสร็จสมบูรณ์</option>
                            <option value="cancelled">ยกเลิก</option>
                        </select>
                    </div>

                    <div>
                        <select v-model="filterForm.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-600 focus:border-blue-600 shadow-sm">
                            <option value="">ทุกกอง/หน่วยงาน</option>
                            <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <select v-model="filterForm.progress" class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-blue-600 focus:border-blue-600 shadow-sm">
                            <option value="">% ความคืบหน้า</option>
                            <option value="0">0% (ยังไม่เริ่ม)</option>
                            <option value="1-25">1% - 25%</option>
                            <option value="26-50">26% - 50%</option>
                            <option value="51-75">51% - 75%</option>
                            <option value="76-99">76% - 99%</option>
                            <option value="100">100% (เสร็จสิ้น)</option>
                        </select>
                        <button @click="resetFilters" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-sm font-bold transition" title="ล้างตัวกรอง">↺</button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 font-black">ชื่องาน / โครงการ</th>
                            <th class="px-6 py-4 font-black w-36 text-center">ประเภท</th>
                            <th class="px-6 py-4 font-black w-40 text-center">ความคืบหน้า</th>
                            <th class="px-6 py-4 font-black w-40 text-center">สถานะ</th>
                            <th class="px-6 py-4 font-black w-48">กอง/หน่วยงาน</th>
                            <th class="px-6 py-4 font-black w-24 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="workItems.data.length === 0">
                            <td colspan="6" class="px-6 py-16 text-center text-gray-400 bg-gray-50/50">
                                <div class="text-5xl mb-3 opacity-30">🔍</div>
                                <p class="text-lg font-bold text-gray-500">ไม่พบโครงการที่ตรงกับเงื่อนไข</p>
                            </td>
                        </tr>
                        <tr v-for="item in workItems.data" :key="item.id" class="hover:bg-blue-50 transition group">
                            <td class="px-6 py-4">
                                <Link :href="route('work-items.show', item.id)" class="font-bold text-blue-700 group-hover:underline text-base line-clamp-2">
                                    {{ item.name }}
                                </Link>
                                <div v-if="item.project_manager" class="text-xs text-gray-500 mt-1">👤 PM: {{ item.project_manager.name }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="item.work_type" class="text-[11px] px-3 py-1.5 rounded-lg border font-bold flex items-center justify-center gap-1 mx-auto w-fit shadow-sm whitespace-nowrap" :style="`background-color: ${item.work_type.color_code}15; color: ${item.work_type.color_code}; border-color: ${item.work_type.color_code}40;`">
                                    <span>{{ item.work_type.icon }}</span> <span>{{ item.work_type.name }}</span>
                                </span>
                                <span v-else class="text-[11px] px-3 py-1.5 rounded-lg border font-bold shadow-sm bg-gray-100 text-gray-700 border-gray-200 uppercase whitespace-nowrap">{{ item.type }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2" :title="`ความคืบหน้า: ${item.progress || 0}%`">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full transition-all duration-500" :class="item.status === 'delayed' ? 'bg-red-500' : 'bg-blue-600'" :style="{ width: (item.progress || 0) + '%' }"></div>
                                    </div>
                                    <span class="text-[10px] font-bold text-gray-600 w-6 text-right">{{ item.progress || 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-[11px] px-3 py-1.5 rounded-lg border font-bold shadow-sm whitespace-nowrap inline-block" :class="getStatusBadge(item.status)">
                                    {{ getStatusTextTh(item.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                <div class="font-bold text-gray-700">{{ item.division ? item.division.name : '-' }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <Link :href="route('work-items.show', item.id)" class="inline-flex items-center justify-center bg-white border border-gray-300 hover:border-blue-600 text-gray-600 hover:text-blue-600 p-2 rounded-lg shadow-sm transition" title="ดูรายละเอียดโครงการ">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center bg-gray-50" v-if="workItems.links && workItems.links.length > 3">
                    <div class="flex flex-wrap gap-1">
                        <Link v-for="(link, key) in workItems.links" :key="key" :href="link.url || '#'" v-html="link.label" class="px-3 py-1.5 rounded-lg text-sm font-bold border shadow-sm transition" :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-blue-50 hover:text-blue-700'" preserve-scroll />
                    </div>
                    <div class="text-xs text-gray-500 font-medium hidden md:block">
                        แสดง <span class="font-bold text-gray-800">{{ workItems.from || 0 }}</span> ถึง <span class="font-bold text-gray-800">{{ workItems.to || 0 }}</span> จากทั้งหมด <span class="font-bold text-gray-800">{{ workItems.total }}</span> รายการ
                    </div>
                </div>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
