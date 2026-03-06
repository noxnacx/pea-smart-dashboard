<script setup>
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
    divisions: Array,
    workItemTypes: { type: Array, default: () => [] }
});

const page = usePage();
const userRole = computed(() => page.props.auth.user.role);
const userId = computed(() => page.props.auth.user.id);

const canEdit = computed(() => ['admin', 'pm', 'project_manager'].includes(userRole.value));
const canManageItem = (item) => {
    if (userRole.value === 'admin') return true;
    if (['pm', 'project_manager'].includes(userRole.value)) return item.project_manager_id === userId.value;
    return false;
};

// 💡 ปรับชื่อ Title
const currentTypeName = computed(() => {
    const t = props.workItemTypes.find(wt => wt.key === props.type);
    return t ? t.name : 'รายการงานทั้งหมด';
});

const pageTitle = computed(() => {
    if (props.type === 'my-work') return 'งานของฉัน (My Works)';
    return `รายการ${currentTypeName.value}`;
});

const showSuccessModal = ref(false);

const filterForm = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    year: props.filters.year || '',
    division_id: props.filters.division_id || '',
    department_id: props.filters.department_id || '',
    sort_by: props.filters.sort_by || 'created_at',
    sort_dir: props.filters.sort_dir || 'desc',
});

// ดึงรายชื่อแผนกตามกองที่เลือก
const filterDepartments = computed(() => {
    if (!filterForm.value.division_id) return [];
    const div = props.divisions.find(d => d.id == filterForm.value.division_id);
    return div ? div.departments : [];
});

watch(filterForm, throttle(() => {
    if (!filterForm.value.division_id) filterForm.value.department_id = '';
    // ส่ง type ปัจจุบันกลับไปด้วย เพื่อไม่ให้หลุด Tab
    router.get(route('work-items.index', { type: props.type }), filterForm.value, { preserveState: true, replace: true });
}, 500), { deep: true });

const statusColor = (status) => ({ completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', in_active: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700', cancelled: 'bg-gray-200 text-gray-500' }[status] || 'bg-gray-100');

// ✅ ฟังก์ชันแปลสถานะเป็นภาษาไทย
const getStatusText = (status) => ({
    completed: 'เสร็จสมบูรณ์',
    delayed: 'ล่าช้า',
    in_active: 'รอเริ่มดำเนินการ',
    in_progress: 'กำลังดำเนินการ',
    cancelled: 'ยกเลิก'
}[status] || status);

const formatDate = (date) => date ? new Date(date).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';
const formatDateForInput = (dateString) => dateString ? String(dateString).split('T')[0].split(' ')[0] : '';
const formatFileSize = (bytes) => { if(bytes===0) return '0 B'; const k=1024, i=Math.floor(Math.log(bytes)/Math.log(k)); return parseFloat((bytes/Math.pow(k,i)).toFixed(2))+' '+['B','KB','MB','GB'][i]; };

const hasActiveIssues = (issues) => issues?.some(i => i.type === 'issue' && i.status !== 'resolved');
const hasActiveRisks = (issues) => issues?.some(i => i.type === 'risk' && i.status !== 'resolved');
const hasParentDateWarning = (item) => {
    if (!item.parent) return false;
    const pStart = item.parent.planned_start_date ? new Date(item.parent.planned_start_date).getTime() : null;
    const pEnd = item.parent.planned_end_date ? new Date(item.parent.planned_end_date).getTime() : null;
    const myStart = item.planned_start_date ? new Date(item.planned_start_date).getTime() : null;
    const myEnd = item.planned_end_date ? new Date(item.planned_end_date).getTime() : null;
    return (myStart && pStart && myStart < pStart) || (myEnd && pEnd && myEnd > pEnd);
};

const showParentDropdown = ref(false);
const parentSearch = ref('');
const parentDropdownRef = ref(null);
const filteredParents = computed(() => {
    if (!parentSearch.value) return props.parentOptions;
    const lowerSearch = parentSearch.value.toLowerCase();
    return props.parentOptions.filter(p => p.name.toLowerCase().includes(lowerSearch) || (p.type_label && p.type_label.toLowerCase().includes(lowerSearch)));
});
const selectParent = (parent) => { form.parent_id = parent.id; parentSearch.value = `[${parent.type_label}] ${parent.name}`; showParentDropdown.value = false; };
const clearParent = () => { parentSearch.value = ''; form.parent_id = null; showParentDropdown.value = false; };
const handleClickOutside = (e) => { if (parentDropdownRef.value && !parentDropdownRef.value.contains(e.target)) showParentDropdown.value = false; };
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');
const form = useForm({ id: null, name: '', description: '', type: props.type === 'my-work' ? 'project' : props.type, budget: 0, progress: 0, status: 'in_active', planned_start_date: '', planned_end_date: '', parent_id: null, division_id: '', department_id: '', pm_name: '', project_manager_id: null, weight: 1 });

const modalDepartments = computed(() => { if (!form.division_id) return []; const div = props.divisions.find(d => d.id == form.division_id); return div ? div.departments : []; });
const closeModalSafely = () => { if (form.isDirty) { if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดหน้าต่างนี้ใช่หรือไม่?')) { showModal.value = false; form.reset(); form.clearErrors(); } } else { showModal.value = false; form.reset(); form.clearErrors(); } };

const openCreateModal = () => {
    isEditing.value = false; form.reset(); form.clearErrors();
    form.id = null; form.type = props.type === 'my-work' ? 'project' : props.type;
    form.parent_id = null; parentSearch.value = ''; form.division_id = ''; form.department_id = '';
    if (['pm', 'project_manager'].includes(userRole.value)) { form.pm_name = page.props.auth.user.name; form.project_manager_id = userId.value; } else { form.pm_name = ''; form.project_manager_id = null; }
    form.status = 'in_active'; form.weight = 1; modalTitle.value = `✨ เพิ่มข้อมูลใหม่`; showModal.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true; form.clearErrors(); modalTitle.value = `✏️ แก้ไข: ${item.name}`;
    form.id = item.id; form.name = item.name; form.description = item.description || ''; form.type = item.type; form.budget = item.budget; form.progress = item.progress; form.status = item.status; form.planned_start_date = formatDateForInput(item.planned_start_date); form.planned_end_date = formatDateForInput(item.planned_end_date); form.parent_id = item.parent_id; form.division_id = item.division_id || ''; form.department_id = item.department_id || ''; form.pm_name = item.project_manager ? item.project_manager.name : ''; form.project_manager_id = item.project_manager_id || null; form.weight = item.weight || 1;
    if (item.parent) {
        const parentType = props.workItemTypes.find(t => t.key === item.parent.type);
        const parentTypeLabel = parentType ? parentType.name : item.parent.type;
        parentSearch.value = `[${parentTypeLabel}] ${item.parent.name}`;
    } else { parentSearch.value = ''; }
    showModal.value = true;
};

const handleStatusToggle = (e) => {
    if (e.target.checked) {
        form.status = 'cancelled';
        form.is_active = false;
    } else {
        form.is_active = true;
        if (form.progress >= 100) form.status = 'completed';
        else if (form.progress > 0) form.status = 'in_progress';
        else form.status = 'in_active';
    }
};

const submit = () => { const options = { onSuccess: () => { showModal.value = false; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); } }; if (form.id) form.put(route('work-items.update', form.id), options); else form.post(route('work-items.store'), options); };
const deleteItem = (id) => { if (confirm('ยืนยันลบข้อมูลนี้? (ย้ายไปถังขยะ)')) useForm({}).delete(route('work-items.destroy', id)); };

const selectedItems = ref([]);
const toggleSelectAll = (e) => { selectedItems.value = e.target.checked ? props.items.data.map(i => i.id) : []; };

const isBulkManageModalOpen = ref(false);
const bulkForm = useForm({ ids: [], action: '', progress: 0, comment: '', bulk_status_mode: 'no_change', type: '', pm_name: '', project_manager_id: null, division_id: '', department_id: '', description: '', weight: '' });

const openBulkManage = () => {
    bulkForm.reset(); bulkForm.clearErrors(); bulkForm.ids = selectedItems.value; isBulkManageModalOpen.value = true;
};

// ✅ แก้บัคปุ่มจัดการกลุ่ม ใส่ .ids = selectedItems.value
const submitBulkDelete = () => {
    if(confirm(`ยืนยันลบ ${selectedItems.value.length} รายการ?`)) {
        bulkForm.ids = selectedItems.value;
        bulkForm.action = 'delete';
        bulkForm.post(route('work-items.bulk'), { onSuccess: () => { isBulkManageModalOpen.value = false; selectedItems.value = []; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); } });
    }
};

const submitBulkUpdate = () => {
    bulkForm.ids = selectedItems.value;
    bulkForm.action = 'update_general';
    bulkForm.post(route('work-items.bulk'), { onSuccess: () => { isBulkManageModalOpen.value = false; selectedItems.value = []; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); } });
};

const bulkDepartments = computed(() => { if(!bulkForm.division_id) return []; const div = props.divisions.find(d => d.id == bulkForm.division_id); return div ? div.departments : []; });

const showQuickView = ref(false), quickViewTitle = ref(''), quickViewItems = ref([]), quickViewType = ref(''), quickViewItemId = ref(null);
const openQuickView = (item, type) => { const activeItems = item.issues?.filter(i => i.type === type && i.status !== 'resolved') || []; if (!activeItems.length) return; quickViewType.value = type; quickViewItemId.value = item.id; quickViewTitle.value = type === 'issue' ? `🔥 ปัญหา (${activeItems.length})` : `⚠️ ความเสี่ยง (${activeItems.length})`; quickViewItems.value = activeItems; showQuickView.value = true; };
</script>

<template>
    <Head :title="pageTitle" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1600px] mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">{{ pageTitle }}</h2>
                    <p class="text-gray-500 mt-1">ค้นหา จัดการ และติดตามสถานะงานในระบบ</p>
                </div>
                <button v-if="canEdit" @click="openCreateModal" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-5 py-2.5 rounded-xl font-bold shadow-md transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่มข้อมูล
                </button>
            </div>

            <div v-if="type !== 'my-work'" class="flex space-x-6 border-b border-gray-200 overflow-x-auto custom-scrollbar">
                <Link v-for="wt in workItemTypes.filter(t => t.level_order > 1)"
                      :key="wt.id"
                      :href="route('work-items.index', { type: wt.key })"
                      class="py-3 px-2 border-b-2 font-bold text-sm whitespace-nowrap transition-colors flex items-center gap-2"
                      :class="type === wt.key ? 'border-[#7A2F8F] text-[#7A2F8F]' : 'border-transparent text-gray-500 hover:text-gray-800'">
                    <span class="text-lg">{{ wt.icon || '📄' }}</span> {{ wt.name }}
                </Link>
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

                    <select v-model="filterForm.department_id" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] w-full md:w-40" :disabled="!filterForm.division_id">
                        <option value="">ทุกแผนก</option>
                        <option v-for="dept in filterDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                </div>

                <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
                    <select v-model="filterForm.status" class="rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F]">
                        <option value="">ทุกสถานะ</option>
                        <option value="in_active">รอเริ่ม (In Active)</option>
                        <option value="in_progress">กำลังดำเนินการ</option>
                        <option value="completed">เสร็จสิ้น</option>
                        <option value="delayed">ล่าช้า</option>
                        <option value="cancelled">ยกเลิก</option>
                    </select>
                </div>
            </div>

            <div v-if="selectedItems.length > 0" class="bg-yellow-50 border border-yellow-200 p-3 rounded-lg flex justify-between items-center animate-fade-in">
                <span class="text-sm font-bold text-yellow-800">เลือกไว้ {{ selectedItems.length }} รายการ</span>
                <button @click="openBulkManage" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-1.5 rounded-lg text-sm font-bold shadow-sm">จัดการข้อมูลที่เลือก</button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th v-if="canEdit" class="px-6 py-4 w-10 text-center"><input type="checkbox" @change="toggleSelectAll" :checked="items.data.length > 0 && selectedItems.length === items.data.length" class="rounded border-gray-300 text-[#7A2F8F] focus:ring-[#7A2F8F]"></th>
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
                        <tr v-if="items.data.length === 0"><td :colspan="canEdit ? 8 : 7" class="px-6 py-8 text-center text-gray-400">ไม่พบข้อมูล</td></tr>
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-purple-50 transition group" :class="{'opacity-50 bg-gray-50 grayscale': item.status === 'cancelled', 'bg-purple-50/50': selectedItems.includes(item.id)}">
                            <td v-if="canEdit" class="px-6 py-4 text-center">
                                <input type="checkbox" v-model="selectedItems" :value="item.id" class="rounded border-gray-300 text-[#7A2F8F] focus:ring-[#7A2F8F] cursor-pointer">
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center text-[#7A2F8F] font-bold text-lg shrink-0"
                                         :class="item.status === 'cancelled' ? 'bg-gray-200 text-gray-500' : ''"
                                         :style="item.workType ? `color: ${item.workType.color_code};` : ''">
                                        {{ item.workType ? item.workType.icon : '📄' }}
                                    </div>
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
                            <td class="px-6 py-4 text-center"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase whitespace-nowrap" :class="statusColor(item.status)">{{ getStatusText(item.status) }}</span></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 justify-center">
                                    <div class="w-full max-w-[100px] bg-gray-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full"
                                             :class="item.status === 'cancelled' ? 'bg-gray-400' : ''"
                                             :style="`width: ${item.progress}%; background-color: ${item.status !== 'cancelled' ? (item.workType?.color_code || '#7A2F8F') : ''}`">
                                        </div>
                                    </div>
                                    <span class="text-xs font-medium w-8 text-right">{{ item.progress }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-gray-700">{{ Number(item.budget).toLocaleString() }}</td>
                            <td class="px-6 py-4 text-center text-xs text-gray-500 whitespace-nowrap flex items-center justify-center gap-1">
                                {{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}
                                <span v-if="hasParentDateWarning(item)" class="text-sm cursor-help animate-pulse" title="⚠️ ระยะเวลาไม่อยู่ในช่วงของงานหลัก (Parent)">⚠️</span>
                            </td>
                            <td v-if="canEdit" class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <Link :href="route('work-items.show', item.id)" class="p-1.5 rounded-lg hover:bg-blue-50 text-lg transition">🔍</Link>

                                    <template v-if="canManageItem(item)">
                                        <button @click="openEditModal(item)" class="p-1.5 rounded-lg hover:bg-yellow-50 text-lg transition">✏️</button>
                                        <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-lg transition">🗑️</button>
                                    </template>

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

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeModalSafely"></div>

                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl h-[90vh] flex flex-col relative z-10">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913] shrink-0">
                        <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                        <button @click="closeModalSafely" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submit" class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">

                        <div ref="parentDropdownRef" class="relative">
                            <label class="block text-sm font-bold text-gray-700 mb-1">งานภายใต้ (สังกัด)</label>

                            <div v-if="form.parent_id" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 flex items-center justify-between">
                                <div class="flex items-center gap-2 truncate">
                                    <span>📁</span>
                                    <span class="truncate font-medium">{{ parentSearch.replace(/^\[.*?\]\s*/, '') }}</span>
                                </div>
                                <button @click.prevent="clearParent" class="text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full p-1 transition-colors z-10 shrink-0" title="เปลี่ยนสังกัด">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div v-else class="relative flex items-center">
                                <input
                                    type="text"
                                    v-model="parentSearch"
                                    @focus="showParentDropdown = true"
                                    placeholder="พิมพ์ชื่อเพื่อค้นหา... (เว้นว่างไว้หากต้องการให้อยู่ระดับบนสุด)"
                                    class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]"
                                    :class="{'border-red-500': form.errors.parent_id}"
                                >
                            </div>

                            <div v-if="form.errors.parent_id" class="text-red-500 text-xs mt-1">{{ form.errors.parent_id }}</div>

                            <div v-if="showParentDropdown && !form.parent_id" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
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

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500 focus:ring-red-500': form.errors.name}" required>
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด (Description)</label>
                            <textarea v-model="form.description" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] text-sm" :class="{'border-red-500': form.errors.description}" rows="3" placeholder="ระบุรายละเอียด..."></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-purple-50 p-3 rounded-lg border border-purple-100">
                            <div class="col-span-2 text-xs font-bold text-[#4A148C] uppercase">สังกัดหน่วยงาน</div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">กอง <span class="text-red-500">*</span></label>
                                <select v-model="form.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.division_id}" required>
                                    <option value="">-- เลือกกอง --</option>
                                    <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                                </select>
                                <div v-if="form.errors.division_id" class="text-red-500 text-xs mt-1">{{ form.errors.division_id }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label>
                                <select v-model="form.department_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.department_id}" :disabled="!form.division_id">
                                    <option value="">-- ไม่ระบุ --</option>
                                    <option v-for="dept in modalDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                </select>
                                <div v-if="form.errors.department_id" class="text-red-500 text-xs mt-1">{{ form.errors.department_id }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ผู้ดูแล (PM)</label>
                            <PmAutocomplete
                                v-model="form.pm_name"
                                @update:id="(id) => form.project_manager_id = id"
                                placeholder="ค้นหาจากชื่อ User..."
                            />
                            <div v-if="form.errors.project_manager_id" class="text-red-500 text-xs mt-1">{{ form.errors.project_manager_id }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">ประเภทงาน <span class="text-red-500">*</span></label>
                                <select v-model="form.type" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.type}" required>
                                    <option v-if="!workItemTypes || workItemTypes.length === 0" value="project">โครงการ (Default)</option>
                                    <option v-for="type in workItemTypes" :key="type.id" :value="type.key">{{ type.name }}</option>
                                </select>
                                <div v-if="form.errors.type" class="text-red-500 text-xs mt-1">{{ form.errors.type }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label>
                                <input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.budget}">
                                <div v-if="form.errors.budget" class="text-red-500 text-xs mt-1">{{ form.errors.budget }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">น้ำหนักงาน (Weight)</label>
                                <input v-model="form.weight" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.weight}">
                                <span class="text-[10px] text-gray-500 block mt-1">ใช้คำนวณความสำคัญของงาน</span>
                                <div v-if="form.errors.weight" class="text-red-500 text-xs mt-1">{{ form.errors.weight }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ (Status)</label>
                                <div class="w-full rounded-lg border border-gray-200 bg-white p-2 h-[42px] flex items-center gap-2 cursor-not-allowed opacity-80" title="ระบบคำนวณสถานะให้อัตโนมัติ">
                                    <span class="text-[10px] text-purple-700 font-bold bg-purple-100 border border-purple-200 px-2 py-0.5 rounded uppercase">AUTO</span>
                                    <span class="text-xs font-bold text-gray-600 flex-1 whitespace-nowrap overflow-hidden text-ellipsis">
                                        {{ getStatusText(form.status) }}
                                    </span>
                                </div>

                                <div v-if="isEditing" class="mt-2 text-right">
                                    <label class="inline-flex items-center cursor-pointer group">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-red-600 shadow-sm focus:ring-red-500 cursor-pointer"
                                               :checked="form.status === 'cancelled'"
                                               @change="handleStatusToggle">
                                        <span class="ml-2 text-xs font-bold text-gray-500 group-hover:text-red-600 transition-colors">ระงับ / ยกเลิกรายการนี้</span>
                                    </label>
                                </div>
                                <div v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label>
                                <input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.planned_start_date}">
                                <div v-if="form.errors.planned_start_date" class="text-red-500 text-xs mt-1">{{ form.errors.planned_start_date }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label>
                                <input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.planned_end_date}">
                                <div v-if="form.errors.planned_end_date" class="text-red-500 text-xs mt-1">{{ form.errors.planned_end_date }}</div>
                            </div>
                        </div>

                    </form>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0 bg-gray-50">
                        <button type="button" @click="closeModalSafely" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 rounded-lg font-bold transition">ยกเลิก</button>
                        <button type="submit" @click="submit" class="px-5 py-2.5 bg-[#7A2F8F] hover:bg-[#5e2270] text-white rounded-lg font-bold shadow-md transition" :disabled="form.processing">
                            <span v-if="form.processing">กำลังบันทึก...</span>
                            <span v-else>บันทึกข้อมูล</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="isBulkManageModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="isBulkManageModalOpen = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl relative z-10 animate-fade-in flex flex-col max-h-[90vh]">
                    <div class="bg-yellow-500 px-6 py-4 flex justify-between items-center border-b-4 border-yellow-600">
                        <h3 class="text-lg font-bold text-white">⚡ จัดการข้อมูลแบบกลุ่ม ({{ selectedItems.length }} รายการ)</h3>
                        <button @click="isBulkManageModalOpen = false" class="text-white hover:text-yellow-100 font-bold text-xl">&times;</button>
                    </div>
                    <div class="p-6 overflow-y-auto space-y-4 flex-1 custom-scrollbar">
                         <div class="bg-gray-50 p-3 rounded border border-gray-200 text-xs text-gray-500 mb-4">💡 กรอกเฉพาะช่องที่ต้องการแก้ไข (ช่องว่าง = ไม่เปลี่ยนค่าเดิม)</div>

                         <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">กอง</label><select v-model="bulkForm.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-yellow-500 focus:ring-yellow-500"><option value="">(ไม่เปลี่ยน)</option><option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label><select v-model="bulkForm.department_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-yellow-500 focus:ring-yellow-500" :disabled="!bulkForm.division_id"><option value="">(ไม่เปลี่ยน)</option><option v-for="d in bulkDepartments" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                         </div>
                         <div><label class="block text-sm font-bold text-gray-700 mb-1">ผู้ดูแล (PM)</label><PmAutocomplete v-model="bulkForm.pm_name" @update:id="(id) => bulkForm.project_manager_id = id" placeholder="ค้นหาเพื่อเปลี่ยน PM..." /></div>
                         <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">ประเภทงาน</label><select v-model="bulkForm.type" class="w-full rounded-lg border-gray-300 focus:border-yellow-500 focus:ring-yellow-500"><option value="">(ไม่เปลี่ยน)</option><option v-for="t in workItemTypes" :key="t.id" :value="t.key">{{ t.name }}</option></select></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">น้ำหนักงาน</label><input v-model="bulkForm.weight" type="number" step="0.01" class="w-full rounded-lg border-gray-300 focus:border-yellow-500 focus:ring-yellow-500" placeholder="(ไม่เปลี่ยน)"></div>
                         </div>
                         <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ</label>
                            <div class="flex items-center gap-2 p-2 border border-gray-200 rounded-lg bg-gray-50">
                                <div class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-[10px] font-bold border border-purple-200 uppercase">AUTO</div>
                                <select v-model="bulkForm.bulk_status_mode" class="flex-1 rounded-md border-gray-300 text-sm ml-2 focus:border-yellow-500 focus:ring-yellow-500">
                                    <option value="no_change">คงเดิม (ไม่เปลี่ยนแปลง)</option>
                                    <option value="active">🟢 เปิดใช้งาน (ดึงกลับมาทำต่อ)</option>
                                    <option value="cancelled">🔴 ระงับ / ยกเลิกรายการนี้</option>
                                </select>
                            </div>
                         </div>
                         <div class="border-t border-gray-200 pt-4 mt-4">
                            <button @click="submitBulkDelete" type="button" class="w-full py-2 bg-red-50 text-red-600 rounded-lg font-bold hover:bg-red-100 transition">🗑️ ลบข้อมูลทั้งหมดที่เลือก</button>
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                        <button @click="isBulkManageModalOpen = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-sm hover:bg-gray-100 transition">ยกเลิก</button>
                        <button @click="submitBulkUpdate" :disabled="bulkForm.processing" class="px-4 py-2 bg-yellow-500 text-white rounded-lg font-bold text-sm shadow hover:bg-yellow-600 transition disabled:opacity-50">บันทึกการแก้ไข</button>
                    </div>
                </div>
            </div>

            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/30 backdrop-blur-sm animate-fade-in">
                <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">สำเร็จ!</h3>
                </div>
            </div>

            <div v-if="showQuickView" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @click.self="showQuickView = false">
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-fade-in">
                    <div class="px-4 py-3 bg-red-500 text-white flex justify-between items-center">
                        <h3 class="font-bold flex items-center gap-2">🔥 รายการปัญหา/ความเสี่ยง</h3>
                        <button @click="showQuickView = false" class="text-2xl leading-none hover:text-red-200">&times;</button>
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
