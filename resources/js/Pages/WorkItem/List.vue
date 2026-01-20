<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import throttle from 'lodash/throttle';

const props = defineProps({
    type: String, // 'plan' หรือ 'project'
    items: Object,
    filters: Object
});

const pageTitle = props.type === 'plan' ? 'แผนงานทั้งหมด (All Plans)' : 'โครงการทั้งหมด (All Projects)';
const routeName = props.type === 'plan' ? 'plans.index' : 'projects.index';

// --- Search & Filter Logic ---
const filterForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    year: props.filters.year || '',
    sort_by: props.filters.sort_by || 'created_at',
    sort_dir: props.filters.sort_dir || 'desc',
});

watch(filterForm, throttle(() => {
    router.get(route(routeName), filterForm.value, {
        preserveState: true,
        replace: true,
    });
}, 500), { deep: true });

// --- Modal & Edit/Delete Logic ---
const showModal = ref(false);
const modalTitle = ref('');
const form = useForm({ id: null, name: '', type: props.type, budget: 0, progress: 0, status: 'pending', planned_start_date: '', planned_end_date: '' });

const openEditModal = (item) => {
    modalTitle.value = `แก้ไข: ${item.name}`;
    form.id = item.id;
    form.name = item.name;
    form.type = item.type;
    form.budget = item.budget;
    form.progress = item.progress;
    form.status = item.status;
    form.planned_start_date = item.planned_start_date;
    form.planned_end_date = item.planned_end_date;
    showModal.value = true;
};

const submit = () => {
    form.put(route('work-items.update', form.id), {
        onSuccess: () => showModal.value = false
    });
};

const deleteItem = (id) => {
    if (confirm('ยืนยันลบ? ข้อมูลทั้งหมดจะหายไป')) {
        useForm({}).delete(route('work-items.destroy', id));
    }
};

// --- Helpers ---
const statusColor = (status) => {
    const map = { completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', pending: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700' };
    return map[status] || 'bg-gray-100';
};
const formatDate = (date) => date ? new Date(date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';
</script>

<template>
    <Head :title="pageTitle" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1600px] mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 border-b border-gray-200 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">{{ pageTitle }}</h2>
                    <p class="text-gray-500 mt-1">ค้นหาและติดตามสถานะ{{ props.type === 'plan' ? 'แผนงาน' : 'โครงการ' }}ในระบบ</p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col md:flex-row gap-4 items-center">
                <div class="relative w-full md:w-96">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></div>
                    <input v-model="filterForm.search" type="text" class="pl-10 w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F]" placeholder="ค้นหาชื่อ..." />
                </div>
                <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
                    <select v-model="filterForm.status" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]"><option value="">ทุกสถานะ</option><option value="pending">Pending</option><option value="in_progress">In Progress</option><option value="completed">Completed</option><option value="delayed">Delayed</option></select>
                    <select v-model="filterForm.year" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]"><option value="">ทุกปี</option><option v-for="y in 5" :key="y" :value="new Date().getFullYear() - 2 + y">{{ new Date().getFullYear() - 2 + y + 543 }}</option></select>
                    <select v-model="filterForm.sort_by" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]"><option value="created_at">เรียงตาม: ล่าสุด</option><option value="budget">เรียงตาม: งบประมาณ</option><option value="progress">เรียงตาม: ความคืบหน้า</option></select>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ชื่อรายการ</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                            <th class="px-6 py-4 text-center">ความคืบหน้า</th>
                            <th class="px-6 py-4 text-right">งบประมาณ</th>
                            <th class="px-6 py-4 text-center">ระยะเวลา</th>
                            <th class="px-6 py-4 text-center w-32">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="items.data.length === 0"><td colspan="6" class="px-6 py-8 text-center text-gray-400">ไม่พบข้อมูล</td></tr>
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-purple-50 transition group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-[#7A2F8F] font-bold">{{ type === 'plan' ? 'P' : 'J' }}</div>
                                    <Link :href="route('work-items.show', item.id)" class="font-bold text-gray-800 hover:text-[#7A2F8F] hover:underline truncate max-w-[300px]">{{ item.name }}</Link>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center"><span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="statusColor(item.status)">{{ item.status }}</span></td>
                            <td class="px-6 py-4"><div class="flex items-center gap-2"><div class="w-full bg-gray-200 rounded-full h-1.5"><div class="bg-[#7A2F8F] h-1.5 rounded-full" :style="`width: ${item.progress}%`"></div></div><span class="text-xs font-medium">{{ item.progress }}%</span></div></td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-gray-700">{{ Number(item.budget).toLocaleString() }}</td>
                            <td class="px-6 py-4 text-center text-xs text-gray-500">{{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}</td>

                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <Link :href="route('work-items.show', item.id)" class="p-1.5 rounded-lg hover:bg-blue-50 text-lg transition" title="ดูรายละเอียด">🔍</Link>
                                    <button @click="openEditModal(item)" class="p-1.5 rounded-lg hover:bg-yellow-50 text-lg transition" title="แก้ไข">✏️</button>
                                    <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-lg transition" title="ลบ">🗑️</button>
                                </div>
                            </td>

                        </tr>
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center" v-if="items.links.length > 3">
                    <div class="flex gap-1"><Link v-for="(link, k) in items.links" :key="k" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded text-sm" :class="link.active ? 'bg-[#7A2F8F] text-white' : (link.url ? 'bg-white hover:bg-gray-100 border' : 'text-gray-400')"/></div>
                    <div class="text-xs text-gray-500">แสดง {{ items.from }} ถึง {{ items.to }} จาก {{ items.total }}</div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                    <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                    <button @click="showModal = false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700">ชื่อรายการ</label>
                        <input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] mt-1" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700">งบประมาณ</label>
                            <input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300 pr-8 mt-1">
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-gray-700">สถานะ</label>
                            <select v-model="form.status" class="w-full rounded-lg border-gray-300 mt-1"><option value="pending">Pending</option><option value="in_progress">In Progress</option><option value="completed">Completed</option><option value="delayed">Delayed</option></select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700">เริ่ม</label><input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300 mt-1"></div>
                        <div><label class="block text-sm font-bold text-gray-700">จบ</label><input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300 mt-1"></div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <label class="block text-xs font-bold text-gray-500 mb-2 flex justify-between"><span>ความคืบหน้า</span><span class="text-[#7A2F8F] font-black text-lg">{{ form.progress }}%</span></label>
                        <input v-model="form.progress" type="range" min="0" max="100" class="w-full accent-[#7A2F8F] cursor-pointer">
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-bold">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] hover:bg-[#5e2270] text-white rounded-lg font-bold shadow-md">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
