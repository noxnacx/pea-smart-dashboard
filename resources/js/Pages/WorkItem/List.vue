<script setup>
// ✅ เพิ่ม usePage
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import PmAutocomplete from '@/Components/PmAutocomplete.vue';
import throttle from 'lodash/throttle';

const props = defineProps({
    type: String,
    items: Object,
    filters: Object,
    parentOptions: Array,
    divisions: Array
});

// ✅ เพิ่ม Check Role
const page = usePage();
const canEdit = computed(() => ['admin', 'pm', 'project_manager'].includes(page.props.auth.user.role));

const pageTitle = props.type === 'plan' ? 'แผนงานทั้งหมด' : 'โครงการทั้งหมด';
const routeName = props.type === 'plan' ? 'plans.index' : 'projects.index';
const showSuccessModal = ref(false);

// --- Search & Filter ---
const filterForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    year: props.filters.year || '',
    division_id: props.filters.division_id || '',
    department_id: props.filters.department_id || '',
    sort_by: props.filters.sort_by || 'created_at',
    sort_dir: props.filters.sort_dir || 'desc',
});

const filterDepartments = computed(() => {
    if (!filterForm.value.division_id) return [];
    const div = props.divisions.find(d => d.id == filterForm.value.division_id);
    return div ? div.departments : [];
});

watch(filterForm, throttle(() => {
    if (!filterForm.value.division_id) filterForm.value.department_id = '';
    router.get(route(routeName), filterForm.value, { preserveState: true, replace: true });
}, 500), { deep: true });

// --- Helpers ---
const hasActiveIssues = (issues) => issues?.some(i => i.type === 'issue' && i.status !== 'resolved');
const hasActiveRisks = (issues) => issues?.some(i => i.type === 'risk' && i.status !== 'resolved');
const statusColor = (status) => ({ completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', pending: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700', cancelled: 'bg-gray-200 text-gray-500' }[status] || 'bg-gray-100');
const formatDate = (date) => date ? new Date(date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';
const formatDateForInput = (dateString) => dateString ? String(dateString).split('T')[0].split(' ')[0] : '';

// --- Parent Search Logic ---
const showParentDropdown = ref(false);
const parentSearch = ref('');
const parentDropdownRef = ref(null);

const filteredParents = computed(() => {
    if (!parentSearch.value) return props.parentOptions;
    const lowerSearch = parentSearch.value.toLowerCase();
    return props.parentOptions.filter(p => p.name.toLowerCase().includes(lowerSearch) || p.type_label.includes(parentSearch.value));
});

const selectParent = (parent) => {
    form.parent_id = parent.id;
    parentSearch.value = `[${parent.type_label}] ${parent.name}`;
    showParentDropdown.value = false;
};

const handleClickOutside = (e) => { if (parentDropdownRef.value && !parentDropdownRef.value.contains(e.target)) showParentDropdown.value = false; };
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

// --- Modal Logic ---
const showModal = ref(false);
const modalTitle = ref('');
const form = useForm({
    id: null, name: '', type: props.type, budget: 0, progress: 0,
    status: 'pending', planned_start_date: '', planned_end_date: '', parent_id: '',
    division_id: '', department_id: '', pm_name: '', weight: 1
});

const modalDepartments = computed(() => {
    if (!form.division_id) return [];
    const div = props.divisions.find(d => d.id == form.division_id);
    return div ? div.departments : [];
});

const openCreateModal = () => {
    form.reset(); form.clearErrors();
    form.id = null; form.type = props.type;
    form.parent_id = ''; parentSearch.value = '';
    form.division_id = ''; form.department_id = ''; form.pm_name = '';
    form.weight = 1;
    modalTitle.value = `✨ เพิ่มข้อมูลใหม่`;
    showModal.value = true;
};

const openEditModal = (item) => {
    form.clearErrors();
    modalTitle.value = `✏️ แก้ไข: ${item.name}`;
    form.id = item.id; form.name = item.name; form.type = item.type;
    form.budget = item.budget; form.progress = item.progress; form.status = item.status;
    form.planned_start_date = formatDateForInput(item.planned_start_date);
    form.planned_end_date = formatDateForInput(item.planned_end_date);
    form.parent_id = item.parent_id;
    form.division_id = item.division_id || '';
    form.department_id = item.department_id || '';
    form.pm_name = item.project_manager ? item.project_manager.name : '';
    form.weight = item.weight || 1;

    if (item.parent) {
        const typeMap = { strategy: 'ยุทธศาสตร์', plan: 'แผนงาน', project: 'โครงการ', task: 'งานย่อย' };
        parentSearch.value = `[${typeMap[item.parent.type] || item.parent.type}] ${item.parent.name}`;
    } else { parentSearch.value = ''; }
    showModal.value = true;
};

const submit = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            showSuccessModal.value = true;
            setTimeout(() => showSuccessModal.value = false, 2000);
        }
    };
    if (form.id) form.put(route('work-items.update', form.id), options);
    else form.post(route('work-items.store'), options);
};

const deleteItem = (id) => { if (confirm('ยืนยันลบข้อมูลนี้?')) useForm({}).delete(route('work-items.destroy', id)); };

// --- Quick View (เพิ่มส่วนที่ขาดไป) ---
const showQuickView = ref(false);
const quickViewTitle = ref('');
const quickViewItems = ref([]);
const quickViewType = ref('');
const quickViewItemId = ref(null); // ✅ เพิ่มตัวแปรเก็บ ID

const openQuickView = (item, type) => {
    const activeItems = item.issues?.filter(i => i.type === type && i.status !== 'resolved') || [];
    if (!activeItems.length) return;
    quickViewType.value = type;
    quickViewItemId.value = item.id; // ✅ เก็บ ID ไว้ทำ Link
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
                    <p class="text-gray-500 mt-1">ค้นหาและติดตามสถานะในระบบ</p>
                </div>
                <button v-if="canEdit" @click="openCreateModal" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-5 py-2.5 rounded-xl font-bold shadow-md transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่มข้อมูล
                </button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col md:flex-row gap-4 items-center flex-wrap">
                <div class="relative w-full md:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></div>
                    <input v-model="filterForm.search" type="text" class="pl-10 w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F]" placeholder="ค้นหาชื่อ หรือ PM..." />
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <select v-model="filterForm.division_id" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] w-full md:w-40">
                        <option value="">ทุกกอง</option>
                        <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                    </select>
                </div>

                <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
                    <select v-model="filterForm.status" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F]">
                        <option value="">ทุกสถานะ</option>
                        <option value="pending">รอเริ่ม</option>
                        <option value="in_progress">กำลังดำเนินการ</option>
                        <option value="completed">เสร็จสิ้น</option>
                        <option value="delayed">ล่าช้า</option>
                        <option value="cancelled">ยกเลิก</option>
                    </select>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ชื่อรายการ</th>
                            <th class="px-6 py-4 text-center">ผู้ดูแล (PM)</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                            <th class="px-6 py-4 text-center">ความคืบหน้า</th>
                            <th class="px-6 py-4 text-right">งบประมาณ</th>
                            <th class="px-6 py-4 text-center">ระยะเวลา</th>
                            <th v-if="canEdit" class="px-6 py-4 text-center w-32">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="items.data.length === 0"><td colspan="7" class="px-6 py-8 text-center text-gray-400">ไม่พบข้อมูล</td></tr>
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-purple-50 transition group" :class="{'opacity-50 bg-gray-50 grayscale': item.status === 'cancelled'}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center text-[#7A2F8F] font-bold text-lg shrink-0" :class="item.status === 'cancelled' ? 'bg-gray-200 text-gray-500' : ''">{{ type === 'plan' ? 'P' : 'J' }}</div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <Link :href="route('work-items.show', item.id)" class="font-bold text-gray-800 hover:text-[#7A2F8F] hover:underline truncate max-w-[280px]">
                                                {{ item.name }} <span v-if="item.status === 'cancelled'" class="text-[10px] text-gray-500 font-normal">(ยกเลิก)</span>
                                            </Link>

                                            <button v-if="hasActiveIssues(item.issues)" @click.stop="openQuickView(item, 'issue')" class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center hover:scale-110 transition cursor-pointer" title="ดูรายการปัญหา">
                                                <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
                                            </button>

                                            <button v-if="hasActiveRisks(item.issues)" @click.stop="openQuickView(item, 'risk')" class="w-5 h-5 rounded-full bg-yellow-100 flex items-center justify-center hover:scale-110 transition cursor-pointer" title="ดูรายการความเสี่ยง">
                                                <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                            </button>

                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-0.5 flex flex-wrap items-center gap-1">
                                            <span v-if="item.division" class="bg-blue-50 text-blue-600 px-1.5 rounded border border-blue-100">🏢 {{ item.division.name }}</span>
                                            <span v-if="item.parent" class="bg-gray-100 px-1.5 rounded border">📂 {{ item.parent.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="item.project_manager" class="bg-green-50 text-green-700 border border-green-200 px-2 py-1 rounded text-xs font-bold whitespace-nowrap">👤 {{ item.project_manager.name }}</span>
                                <span v-else class="text-gray-300">-</span>
                            </td>
                            <td class="px-6 py-4 text-center"><span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="statusColor(item.status)">{{ item.status }}</span></td>
                            <td class="px-6 py-4"><div class="flex items-center gap-2"><div class="w-full bg-gray-200 rounded-full h-1.5"><div class="h-1.5 rounded-full" :class="item.status === 'cancelled' ? 'bg-gray-400' : 'bg-[#7A2F8F]'" :style="`width: ${item.progress}%`"></div></div><span class="text-xs font-medium">{{ item.progress }}%</span></div></td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-gray-700">{{ Number(item.budget).toLocaleString() }}</td>
                            <td class="px-6 py-4 text-center text-xs text-gray-500">{{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}</td>
                            <td v-if="canEdit" class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <Link :href="route('work-items.show', item.id)" class="p-1.5 rounded-lg hover:bg-blue-50 text-lg transition">🔍</Link>
                                    <button @click="openEditModal(item)" class="p-1.5 rounded-lg hover:bg-yellow-50 text-lg transition">✏️</button>
                                    <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-lg transition">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center bg-gray-50" v-if="items.links.length > 3">
                    <div class="flex flex-wrap gap-1">
                        <Link v-for="(link, key) in items.links" :key="key" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded-md text-sm transition-colors border"
                            :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 border-gray-300 hover:bg-purple-50'"
                            :preserve-scroll="true" />
                    </div>
                    <div class="text-xs text-gray-500">
                        แสดง {{ items.from }} ถึง {{ items.to }} จาก {{ items.total }} รายการ
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl h-[90vh] flex flex-col">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                    <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                    <button @click="showModal = false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">

                    <div ref="parentDropdownRef" class="relative">
                        <label class="block text-sm font-bold text-gray-700 mb-1">งานภายใต้ (สังกัด)</label>
                        <input type="text" v-model="parentSearch" @focus="showParentDropdown = true" placeholder="พิมพ์ชื่อเพื่อค้นหา..." class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                        <div v-if="showParentDropdown" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            <ul class="py-1 text-sm text-gray-700">
                                <li v-if="filteredParents.length === 0" class="px-4 py-2 text-gray-400 italic">ไม่พบข้อมูล</li>
                                <li v-for="parent in filteredParents" :key="parent.id" @click="selectParent(parent)" class="px-4 py-2 hover:bg-purple-50 cursor-pointer flex justify-between items-center group transition">
                                    <span>{{ parent.name }}</span>
                                    <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded group-hover:bg-purple-100 group-hover:text-purple-700">{{ parent.type_label }}</span>
                                </li>
                            </ul>
                        </div>
                        <input type="hidden" v-model="form.parent_id">
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-purple-50 p-3 rounded-lg border border-purple-100">
                        <div class="col-span-2 text-xs font-bold text-[#4A148C] uppercase">สังกัดหน่วยงาน</div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">กอง <span class="text-red-500">*</span></label>
                            <select v-model="form.division_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                                <option value="">-- เลือกกอง --</option>
                                <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label>
                            <select v-model="form.department_id" class="w-full rounded-lg border-gray-300 text-sm" :disabled="!form.division_id">
                                <option value="">-- ไม่ระบุ --</option>
                                <option v-for="dept in modalDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ผู้ดูแล (PM)</label><PmAutocomplete v-model="form.pm_name" /></div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">ประเภทงาน <span class="text-red-500">*</span></label>
                        <select v-model="form.type" class="w-full rounded-lg border-gray-300" required>
                            <option value="plan">แผนงาน</option><option value="project">โครงการ</option><option value="task">งานย่อย</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label><input v-model="form.name" class="w-full rounded-lg border-gray-300" required></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label><input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300"></div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ</label>
                            <select v-model="form.status" class="w-full rounded-lg border-gray-300">
                                <option value="pending">รอเริ่ม</option><option value="in_progress">กำลังทำ</option>
                                <option value="completed">เสร็จสิ้น</option><option value="delayed">ล่าช้า</option>
                                <option value="cancelled">ยกเลิก</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg border">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">น้ำหนักงาน</label>
                            <input v-model="form.weight" type="number" step="0.1" class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ความคืบหน้า (%)</label>
                            <input v-model="form.progress" type="number" min="0" max="100" class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label><input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label><input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                    </div>
                </form>
                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
                    <button @click="showModal = false" class="px-5 py-2.5 bg-white border rounded-lg font-bold">ยกเลิก</button>
                    <button @click="submit" class="px-5 py-2.5 bg-[#7A2F8F] text-white rounded-lg font-bold shadow-md" :disabled="form.processing">บันทึก</button>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center animate-fade-in">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">บันทึกสำเร็จ!</h3>
                    <p class="text-gray-500 mt-2">ข้อมูลถูกอัปเดตเรียบร้อยแล้ว</p>
                </div>
            </div>

            <div v-if="showQuickView" class="fixed inset-0 z-[100] flex items-center justify-center" @click.self="showQuickView = false">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden relative z-10 animate-fade-in mx-4">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-lg" :class="quickViewType === 'issue' ? 'text-red-700' : 'text-yellow-700'">{{ quickViewTitle }}</h3>
                        <button @click="showQuickView = false" class="text-gray-400 hover:text-gray-700 font-bold text-xl">&times;</button>
                    </div>
                    <div class="p-4 max-h-[60vh] overflow-y-auto space-y-3 bg-gray-50/50 custom-scrollbar">
                        <div v-for="item in quickViewItems" :key="item.id" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold text-gray-800 text-sm">{{ item.title }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wide"
                                      :class="item.severity === 'critical' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600'">
                                    {{ item.severity }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">{{ item.description }}</p>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100 bg-white text-right">
                        <Link v-if="quickViewItemId" :href="route('work-items.show', quickViewItemId)" class="text-sm font-bold text-[#7A2F8F] hover:underline flex items-center justify-end gap-1">
                            ไปจัดการที่หน้างาน <span class="text-lg">›</span>
                        </Link>
                    </div>
                </div>
            </div>
        </Teleport>

    </PeaSidebarLayout>
</template>
