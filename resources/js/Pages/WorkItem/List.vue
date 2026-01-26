<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import throttle from 'lodash/throttle';

const props = defineProps({
    type: String,
    items: Object,
    filters: Object,
    parentOptions: Array // รับข้อมูล Parent ทั้งหมด
});

const pageTitle = props.type === 'plan' ? 'แผนงานทั้งหมด (All Plans)' : 'โครงการทั้งหมด (All Projects)';
const routeName = props.type === 'plan' ? 'plans.index' : 'projects.index';

// --- Search & Filter ---
const filterForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    year: props.filters.year || '',
    sort_by: props.filters.sort_by || 'created_at',
    sort_dir: props.filters.sort_dir || 'desc',
});

watch(filterForm, throttle(() => {
    router.get(route(routeName), filterForm.value, { preserveState: true, replace: true });
}, 500), { deep: true });

// --- Helpers ---
const hasActiveIssues = (issues) => issues?.some(i => i.type === 'issue' && i.status !== 'resolved');
const hasActiveRisks = (issues) => issues?.some(i => i.type === 'risk' && i.status !== 'resolved');
const getSeverityClass = (s) => ({ critical: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-green-100 text-green-700' }[s] || 'bg-gray-100');
const statusColor = (status) => ({ completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', pending: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700' }[status] || 'bg-gray-100');
const formatDate = (date) => date ? new Date(date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';

// --- ✨ Parent Search Logic (พิมพ์เพื่อค้นหา) ---
const showParentDropdown = ref(false);
const parentSearch = ref('');
const parentDropdownRef = ref(null);

const filteredParents = computed(() => {
    if (!parentSearch.value) return props.parentOptions;
    const lowerSearch = parentSearch.value.toLowerCase();
    return props.parentOptions.filter(p =>
        p.name.toLowerCase().includes(lowerSearch) ||
        p.type_label.includes(parentSearch.value)
    );
});

const selectParent = (parent) => {
    form.parent_id = parent.id;
    parentSearch.value = `[${parent.type_label}] ${parent.name}`;
    showParentDropdown.value = false;
};

// คลิกข้างนอกเพื่อปิด Dropdown
const handleClickOutside = (e) => {
    if (parentDropdownRef.value && !parentDropdownRef.value.contains(e.target)) {
        showParentDropdown.value = false;
    }
};
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

// --- Modal Logic ---
const showModal = ref(false);
const modalTitle = ref('');
const form = useForm({
    id: null, name: '', type: props.type, budget: 0, progress: 0,
    status: 'pending', planned_start_date: '', planned_end_date: '', parent_id: ''
});

const openCreateModal = () => {
    form.reset(); form.id = null; form.type = props.type;
    form.parent_id = ''; parentSearch.value = '';
    modalTitle.value = `✨ สร้าง${props.type === 'plan' ? 'แผนงาน' : 'โครงการ'}ใหม่`;
    showModal.value = true;
};

const openEditModal = (item) => {
    modalTitle.value = `✏️ แก้ไข: ${item.name}`;
    form.id = item.id; form.name = item.name; form.type = item.type;
    form.budget = item.budget; form.progress = item.progress; form.status = item.status;
    form.planned_start_date = item.planned_start_date; form.planned_end_date = item.planned_end_date;
    form.parent_id = item.parent_id;

    // ตั้งค่าชื่อ Parent ให้แสดงในช่องค้นหา
    if (item.parent) {
        const typeMap = { strategy: 'ยุทธศาสตร์', plan: 'แผนงาน', project: 'โครงการ', task: 'งานย่อย' };
        parentSearch.value = `[${typeMap[item.parent.type] || item.parent.type}] ${item.parent.name}`;
    } else {
        parentSearch.value = '';
    }
    showModal.value = true;
};

const submit = () => {
    const action = form.id ? form.put(route('work-items.update', form.id)) : form.post(route('work-items.store'));
    action.then(() => showModal.value = false);
};

const deleteItem = (id) => { if (confirm('ยืนยันลบ?')) useForm({}).delete(route('work-items.destroy', id)); };

// --- Quick View ---
const showQuickView = ref(false);
const quickViewTitle = ref('');
const quickViewItems = ref([]);
const quickViewType = ref('');

const openQuickView = (item, type) => {
    const activeItems = item.issues?.filter(i => i.type === type && i.status !== 'resolved') || [];
    if (!activeItems.length) return;
    quickViewType.value = type;
    quickViewTitle.value = type === 'issue' ? `🔥 ปัญหา (${activeItems.length})` : `⚠️ ความเสี่ยง (${activeItems.length})`;
    quickViewItems.value = activeItems;
    showQuickView.value = true;
};
</script>

<template>
    <Head :title="pageTitle" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1600px] mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-gray-200 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">{{ pageTitle }}</h2>
                    <p class="text-gray-500 mt-1">ค้นหาและติดตามสถานะ{{ props.type === 'plan' ? 'แผนงาน' : 'โครงการ' }}ในระบบ</p>
                </div>
                <button @click="openCreateModal" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-5 py-2.5 rounded-xl font-bold shadow-md transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่ม{{ props.type === 'plan' ? 'แผนงาน' : 'โครงการ' }}
                </button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col md:flex-row gap-4 items-center">
                <div class="relative w-full md:w-96">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></div>
                    <input v-model="filterForm.search" type="text" class="pl-10 w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F]" placeholder="ค้นหาชื่อ..." />
                </div>
                <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
                    <select v-model="filterForm.status" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F]"><option value="">ทุกสถานะ</option><option value="pending">Pending</option><option value="in_progress">In Progress</option><option value="completed">Completed</option><option value="delayed">Delayed</option></select>
                    <select v-model="filterForm.year" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F]"><option value="">ทุกปี</option><option v-for="y in 5" :key="y" :value="new Date().getFullYear() - 2 + y">{{ new Date().getFullYear() - 2 + y + 543 }}</option></select>
                    <select v-model="filterForm.sort_by" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F]"><option value="created_at">ล่าสุด</option><option value="budget">งบประมาณ</option><option value="progress">ความคืบหน้า</option></select>
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
                                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center text-[#7A2F8F] font-bold text-lg shrink-0">{{ type === 'plan' ? 'P' : 'J' }}</div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <Link :href="route('work-items.show', item.id)" class="font-bold text-gray-800 hover:text-[#7A2F8F] hover:underline truncate max-w-[280px]">{{ item.name }}</Link>
                                            <button v-if="hasActiveIssues(item.issues)" @click.stop="openQuickView(item, 'issue')" class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center hover:scale-110 transition"><div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div></button>
                                            <button v-if="hasActiveRisks(item.issues)" @click.stop="openQuickView(item, 'risk')" class="w-5 h-5 rounded-full bg-orange-100 flex items-center justify-center hover:scale-110 transition"><div class="w-2 h-2 rounded-full bg-orange-400"></div></button>
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-0.5 flex items-center gap-1" v-if="item.parent">
                                            <span class="bg-gray-100 px-1.5 rounded border">📂 {{ item.parent.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center"><span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="statusColor(item.status)">{{ item.status }}</span></td>
                            <td class="px-6 py-4"><div class="flex items-center gap-2"><div class="w-full bg-gray-200 rounded-full h-1.5"><div class="bg-[#7A2F8F] h-1.5 rounded-full" :style="`width: ${item.progress}%`"></div></div><span class="text-xs font-medium">{{ item.progress }}%</span></div></td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-gray-700">{{ Number(item.budget).toLocaleString() }}</td>
                            <td class="px-6 py-4 text-center text-xs text-gray-500">{{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}</td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <Link :href="route('work-items.show', item.id)" class="p-1.5 rounded-lg hover:bg-blue-50 text-lg transition">🔍</Link>
                                    <button @click="openEditModal(item)" class="p-1.5 rounded-lg hover:bg-yellow-50 text-lg transition">✏️</button>
                                    <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-lg transition">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center" v-if="items.links.length > 3">
                    <div class="flex gap-1"><Link v-for="(link, k) in items.links" :key="k" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded text-sm" :class="link.active ? 'bg-[#7A2F8F] text-white' : 'text-gray-400 hover:bg-gray-100 border'"/></div>
                    <div class="text-xs text-gray-500">Total: {{ items.total }}</div>
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

                    <div ref="parentDropdownRef" class="relative">
                        <label class="block text-sm font-bold text-gray-700 mb-1">อยู่ภายใต้ (สังกัด) <span class="text-red-500">*</span></label>
                        <input
                            type="text"
                            v-model="parentSearch"
                            @focus="showParentDropdown = true"
                            placeholder="พิมพ์เพื่อค้นหา ยุทธศาสตร์ / แผนงาน / โครงการ..."
                            class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]"
                        >
                        <div v-if="showParentDropdown" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <ul class="py-1 text-sm text-gray-700">
                                <li v-if="filteredParents.length === 0" class="px-4 py-2 text-gray-400 italic">ไม่พบข้อมูล</li>
                                <li v-for="parent in filteredParents" :key="parent.id"
                                    @click="selectParent(parent)"
                                    class="px-4 py-2 hover:bg-purple-50 cursor-pointer flex justify-between items-center group transition">
                                    <span>{{ parent.name }}</span>
                                    <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded group-hover:bg-purple-100 group-hover:text-purple-700">{{ parent.type_label }}</span>
                                </li>
                            </ul>
                        </div>
                        <input type="hidden" v-model="form.parent_id" required> </div>

                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label><input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" required></div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label><input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]"></div>
                         <div><label class="block text-sm font-bold text-gray-700 mb-1">สถานะ</label><select v-model="form.status" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]"><option value="pending">Pending</option><option value="in_progress">In Progress</option><option value="completed">Completed</option><option value="delayed">Delayed</option></select></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">วันเริ่ม</label><input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">วันจบ</label><input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300"></div>
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

        <div v-if="showQuickView" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showQuickView = false">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-6 py-4 flex justify-between items-center text-white" :class="quickViewType === 'issue' ? 'bg-red-500' : 'bg-orange-500'">
                    <h3 class="text-lg font-bold flex items-center gap-2"><span>{{ quickViewType === 'issue' ? '🔥' : '⚠️' }}</span> {{ quickViewTitle }}</h3>
                    <button @click="showQuickView = false" class="text-2xl">&times;</button>
                </div>
                <div class="p-6 bg-gray-50 max-h-[70vh] overflow-y-auto space-y-3">
                    <div v-for="item in quickViewItems" :key="item.id" class="bg-white p-4 rounded-xl border shadow-sm">
                        <div class="flex justify-between mb-2"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded border" :class="getSeverityClass(item.severity)">{{ item.severity }}</span></div>
                        <h4 class="font-bold text-gray-800">{{ item.title }}</h4>
                        <p class="text-xs text-gray-600 mt-1">{{ item.description }}</p>
                    </div>
                </div>
            </div>
        </div>

    </PeaSidebarLayout>
</template>
