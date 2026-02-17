<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import GanttChart from '@/Components/GanttChart.vue';
import PmAutocomplete from '@/Components/PmAutocomplete.vue';

// --- Props ---
const props = defineProps({
    item: Object,
    chartData: Object,
    historyLogs: Object,
    divisions: Array
});

const activeTab = ref('overview');
const showSuccessModal = ref(false);

// --- Check Role & Permissions ---
const page = usePage();
const userRole = computed(() => page.props.auth.user.role);
const userId = computed(() => page.props.auth.user.id);

const canEdit = computed(() => {
    if (userRole.value === 'admin') return true;
    if (['pm', 'project_manager'].includes(userRole.value)) {
        return props.item.project_manager_id === userId.value;
    }
    return false;
});

const isParent = computed(() => props.item.children && props.item.children.length > 0);

// --- Helpers ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });
};

const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    return String(dateString).split('T')[0].split(' ')[0];
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// --- Project Health Logic ---
const projectHealth = computed(() => {
    const { status, planned_start_date, planned_end_date, progress } = props.item;

    if (status === 'completed') return { color: 'bg-green-500', bg: 'bg-green-50', text: 'เสร็จสมบูรณ์', icon: '🏆' };
    if (status === 'cancelled') return { color: 'bg-gray-400', bg: 'bg-gray-50', text: 'ยกเลิกโครงการ', icon: '⚪' };
    if (!planned_start_date || !planned_end_date) return { color: 'bg-gray-300', bg: 'bg-gray-50', text: 'ไม่ระบุวัน', icon: '📅' };

    const start = new Date(planned_start_date).getTime();
    const end = new Date(planned_end_date).getTime();
    const now = new Date().getTime();
    const totalDuration = end - start;

    if (now < start) return { color: 'bg-blue-400', bg: 'bg-blue-50', text: 'รอเริ่มดำเนินการ', icon: '⏳' };

    let timeProgress = 0;
    if (now > end) {
        timeProgress = 100;
    } else {
        timeProgress = ((now - start) / totalDuration) * 100;
    }

    const currentProgress = progress || 0;
    const diff = currentProgress - timeProgress;

    if (now > end && currentProgress < 100) {
        return { color: 'bg-red-600', bg: 'bg-red-50', text: 'Overdue (เกินกำหนด)', icon: '🔥' };
    }
    if (diff >= -5) {
        return { color: 'bg-green-500', bg: 'bg-green-50', text: 'On Track (ตามแผน)', icon: '🟢' };
    }
    if (diff >= -20) {
        return { color: 'bg-yellow-400', bg: 'bg-yellow-50', text: 'At Risk (ล่าช้าเล็กน้อย)', icon: '🟡' };
    }
    return { color: 'bg-red-500', bg: 'bg-red-50', text: 'Critical (ล่าช้ามาก)', icon: '🔴' };
});

// --- Date Validation ---
const dateValidationWarnings = computed(() => {
    const warnings = [];
    const parent = props.item.parent;

    if (!parent) return warnings;

    const myStart = props.item.planned_start_date ? new Date(props.item.planned_start_date) : null;
    const myEnd = props.item.planned_end_date ? new Date(props.item.planned_end_date) : null;
    const parentStart = parent.planned_start_date ? new Date(parent.planned_start_date) : null;
    const parentEnd = parent.planned_end_date ? new Date(parent.planned_end_date) : null;

    if (myStart && parentStart && myStart < parentStart) {
        warnings.push(`⚠️ วันเริ่ม (${formatDate(props.item.planned_start_date)}) ก่อนวันเริ่มของงานแม่ (${formatDate(parent.planned_start_date)})`);
    }

    if (myEnd && parentEnd && myEnd > parentEnd) {
        warnings.push(`⚠️ วันจบ (${formatDate(props.item.planned_end_date)}) เกินวันจบของงานแม่ (${formatDate(parent.planned_end_date)})`);
    }

    return warnings;
});

const hasChildDateWarning = (child) => {
    const pStart = props.item.planned_start_date ? new Date(props.item.planned_start_date).getTime() : null;
    const pEnd = props.item.planned_end_date ? new Date(props.item.planned_end_date).getTime() : null;
    const cStart = child.planned_start_date ? new Date(child.planned_start_date).getTime() : null;
    const cEnd = child.planned_end_date ? new Date(child.planned_end_date).getTime() : null;

    if (cStart && pStart && cStart < pStart) return true;
    if (cEnd && pEnd && cEnd > pEnd) return true;

    return false;
};

// --- Colors & Badges ---
const getSeverityColor = (s) => ({ critical: 'bg-red-100 text-red-700 border-red-200', high: 'bg-orange-100 text-orange-700 border-orange-200', medium: 'bg-yellow-100 text-yellow-700 border-yellow-200', low: 'bg-green-100 text-green-700 border-green-200' }[s] || 'bg-gray-100');
const getSeverityHeaderClass = (s) => ({ critical: 'bg-red-500', high: 'bg-orange-500', medium: 'bg-yellow-500', low: 'bg-green-500' }[s] || 'bg-gray-500');
const getStatusBadge = (s) => ({ open: '🔴 รอแก้ไข', in_progress: '🟡 กำลังดำเนินการ', resolved: '🟢 แก้ไขแล้ว' }[s] || s);
const getCategoryBadge = (c) => ({ 'contract': 'bg-blue-100 text-blue-700', 'invoice': 'bg-green-100 text-green-700', 'report': 'bg-purple-100 text-purple-700', 'tor': 'bg-orange-100 text-orange-700', 'general': 'bg-gray-100 text-gray-700' }[c] || 'bg-gray-100 text-gray-700');
const getFileIcon = (m) => m?.includes('pdf')?'📄':m?.includes('word')||m?.includes('doc')?'📝':m?.includes('sheet')||m?.includes('excel')?'📊':'📎';
const isImage = (m) => m?.startsWith('image/');

// --- Breadcrumbs ---
const breadcrumbs = computed(() => {
    let crumbs = [], curr = props.item;
    crumbs.push({ name: curr.name, url: null });
    while (curr.parent) { curr = curr.parent; crumbs.push({ name: curr.name, url: route('work-items.show', curr.id) }); }
    crumbs.push({ name: 'Dashboard', url: route('dashboard') });
    return crumbs.reverse();
});

// --- Modals Logic & Forms ---
const showModal = ref(false), isEditing = ref(false), modalTitle = ref('');
const showIssueModal = ref(false), showViewIssueModal = ref(false), selectedIssue = ref(null);
const showUpdateProgressModal = ref(false);

const parentNameDisplay = ref('');

const form = useForm({
    id: null, parent_id: null, name: '', description: '', type: 'task', budget: 0, progress: 0,
    status: 'pending', planned_start_date: '', planned_end_date: '',
    division_id: '', department_id: '', pm_name: '',
    project_manager_id: null,
    weight: 1
});

const updateProgressForm = useForm({
    progress: 0,
    comment: '',
    attachments: []
});

const issueForm = useForm({
    id: null, title: '', type: 'issue', severity: 'medium', status: 'open',
    description: '', solution: '', start_date: '', end_date: ''
});

const fileForm = useForm({ file: null, category: 'general' });
const commentForm = useForm({ body: '' });
const fileFilter = ref('all');
const filteredFiles = computed(() => fileFilter.value==='all' ? props.item.attachments||[] : (props.item.attachments||[]).filter(f => f.category === fileFilter.value));

const modalDepartments = computed(() => {
    if (!form.division_id) return [];
    const div = props.divisions?.find(d => d.id == form.division_id);
    return div ? div.departments : [];
});

// ✅ --- ฟังก์ชันปิด Modal อย่างปลอดภัย (Unsaved Changes Warning) ---
const closeMainModalSafely = () => {
    if (form.isDirty) {
        if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดหน้าต่างนี้ใช่หรือไม่?')) {
            showModal.value = false;
            form.reset();
            form.clearErrors();
        }
    } else {
        showModal.value = false;
        form.reset();
        form.clearErrors();
    }
};

const closeProgressModalSafely = () => {
    if (updateProgressForm.isDirty) {
        if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดหน้าต่างนี้ใช่หรือไม่?')) {
            showUpdateProgressModal.value = false;
            updateProgressForm.reset();
            updateProgressForm.clearErrors();
        }
    } else {
        showUpdateProgressModal.value = false;
        updateProgressForm.reset();
        updateProgressForm.clearErrors();
    }
};

const closeIssueModalSafely = () => {
    if (issueForm.isDirty) {
        if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดหน้าต่างนี้ใช่หรือไม่?')) {
            showIssueModal.value = false;
            issueForm.reset();
            issueForm.clearErrors();
        }
    } else {
        showIssueModal.value = false;
        issueForm.reset();
        issueForm.clearErrors();
    }
};

// --- Actions ---
const openCreateModal = () => {
    isEditing.value = false;
    modalTitle.value = `สร้างรายการย่อย`;
    form.reset();
    form.clearErrors();

    form.parent_id = props.item.id;
    parentNameDisplay.value = props.item.name;
    form.type = 'task';

    form.division_id = props.item.division_id || '';
    form.department_id = props.item.department_id || '';

    if (props.item.project_manager) {
        form.pm_name = props.item.project_manager.name;
        form.project_manager_id = props.item.project_manager_id;
    } else if (['pm', 'project_manager'].includes(userRole.value)) {
        form.pm_name = page.props.auth.user.name;
        form.project_manager_id = userId.value;
    } else {
        form.pm_name = ''; form.project_manager_id = null;
    }

    form.weight = 1;
    form.description = '';
    showModal.value = true;
};

const openEditModal = (t) => {
    isEditing.value=true;
    modalTitle.value=`แก้ไข: ${t.name}`;
    form.clearErrors();

    form.id=t.id; form.name=t.name; form.description=t.description;
    form.type=t.type; form.budget=t.budget; form.progress=t.progress; form.status=t.status;
    form.planned_start_date=formatDateForInput(t.planned_start_date);
    form.planned_end_date=formatDateForInput(t.planned_end_date);
    form.parent_id = t.parent_id;

    if (t.id === props.item.id) {
        parentNameDisplay.value = props.item.parent ? props.item.parent.name : '-';
    } else {
        parentNameDisplay.value = props.item.name;
    }

    form.division_id = t.division_id || '';
    form.department_id = t.department_id || '';

    form.pm_name = t.project_manager ? t.project_manager.name : '';
    form.project_manager_id = t.project_manager_id || null;

    form.weight = t.weight !== undefined ? t.weight : 1;
    showModal.value=true;
};

const openUpdateProgressModal = () => {
    updateProgressForm.reset();
    updateProgressForm.clearErrors();
    updateProgressForm.progress = props.item.progress;
    showUpdateProgressModal.value = true;
};

const submitProgressUpdate = () => {
    updateProgressForm.post(route('work-items.update-progress', props.item.id), {
        onSuccess: () => {
            showUpdateProgressModal.value = false;
            showSuccessModal.value = true;
            setTimeout(() => showSuccessModal.value = false, 2000);
        }
    });
};

const submit = () => {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            showSuccessModal.value = true;
            setTimeout(() => showSuccessModal.value = false, 2000);
        }
    };
    if (isEditing.value) {
        form.put(route('work-items.update', form.id), options);
    } else {
        form.post(route('work-items.store'), options);
    }
};

const deleteItem = (id) => { if(confirm('ยืนยันลบ?')) useForm({}).delete(route('work-items.destroy', id)); };

const openCreateIssue = () => {
    isEditing.value=false;
    issueForm.reset();
    issueForm.clearErrors();
    showIssueModal.value=true;
};
const openEditIssue = (issue) => {
    showViewIssueModal.value=false;
    isEditing.value=true;
    issueForm.clearErrors();
    issueForm.id=issue.id;
    issueForm.title=issue.title;
    issueForm.type=issue.type;
    issueForm.severity=issue.severity;
    issueForm.status=issue.status;
    issueForm.description=issue.description;
    issueForm.solution=issue.solution;
    issueForm.start_date=formatDateForInput(issue.start_date);
    issueForm.end_date=formatDateForInput(issue.end_date);
    showIssueModal.value=true;
};
const openViewIssue = (issue) => { selectedIssue.value=issue; showViewIssueModal.value=true; };
const submitIssue = () => { isEditing.value ? issueForm.put(route('issues.update', issueForm.id), {onSuccess:()=>showIssueModal.value=false}) : issueForm.post(route('issues.store', props.item.id), {onSuccess:()=>showIssueModal.value=false}); };
const deleteIssue = (id) => { if(confirm('ยืนยันลบ?')) { showViewIssueModal.value=false; useForm({}).delete(route('issues.destroy', id)); } };

const uploadFile = () => {
    if(fileForm.file) {
        fileForm.post(route('attachments.store', props.item.id), {
            onSuccess: () => {
                fileForm.reset();
            }
        });
    }
};
const deleteFile = (id) => { if(confirm('ลบไฟล์?')) useForm({}).delete(route('attachments.destroy', id)); };
const downloadFile = (id) => window.open(route('attachments.download', id), '_blank');
const submitComment = () => {
    if(!commentForm.body.trim()) return;
    commentForm.post(route('comments.store', props.item.id), {
        onSuccess: () => commentForm.reset(),
        preserveScroll: true
    });
};
</script>

<template>
    <Head :title="item.name" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1920px] mx-auto space-y-6">
            <nav class="text-sm text-gray-500 flex items-center flex-wrap gap-2">
                <template v-for="(crumb, i) in breadcrumbs" :key="i">
                    <Link v-if="crumb.url" :href="crumb.url" class="hover:text-[#7A2F8F] hover:underline">{{ crumb.name }}</Link>
                    <span v-else class="text-[#7A2F8F] font-bold truncate max-w-[300px]">{{ crumb.name }}</span>
                    <svg v-if="i < breadcrumbs.length - 1" class="w-4 h-4 text-gray-300" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </template>
            </nav>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 relative overflow-hidden">
                <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                    <div class="flex-1">
                        <span class="bg-[#7A2F8F] text-white text-xs px-2 py-1 rounded uppercase">{{ item.type }}</span>
                        <h1 class="text-3xl font-bold text-[#4A148C] mt-2">{{ item.name }}</h1>

                        <div v-if="dateValidationWarnings.length > 0" class="mt-2 space-y-1">
                            <div v-for="(warning, idx) in dateValidationWarnings" :key="idx" class="text-xs bg-red-100 text-red-700 px-3 py-1.5 rounded-lg font-bold flex items-center gap-2 border border-red-200 w-fit">
                                {{ warning }}
                            </div>
                        </div>

                        <p class="text-sm text-gray-500 mt-2">⏱ {{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}</p>

                        <div v-if="item.description" class="mt-4 p-4 bg-purple-50 rounded-lg border border-purple-100 text-gray-700 text-sm leading-relaxed whitespace-pre-line">
                            {{ item.description }}
                        </div>
                        <div v-else class="mt-4 text-sm text-gray-400 italic">
                            ยังไม่มีรายละเอียดโครงการ (Description)
                        </div>

                        <div class="flex flex-wrap gap-4 mt-4 p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <div v-if="item.division" class="flex items-center gap-2">
                                <span class="text-lg">🏢</span>
                                <div>
                                    <div class="text-[10px] text-gray-400 uppercase font-bold">กอง</div>
                                    <div class="text-sm font-bold text-gray-700">{{ item.division.name }}</div>
                                </div>
                            </div>
                            <div v-if="item.department" class="flex items-center gap-2 border-l pl-4 border-gray-200">
                                <span class="text-lg">🏷️</span>
                                <div>
                                    <div class="text-[10px] text-gray-400 uppercase font-bold">แผนก</div>
                                    <div class="text-sm font-bold text-gray-700">{{ item.department.name }}</div>
                                </div>
                            </div>
                            <div v-if="item.project_manager" class="flex items-center gap-2 border-l pl-4 border-gray-200">
                                <span class="text-lg">👤</span>
                                <div>
                                    <div class="text-[10px] text-gray-400 uppercase font-bold">ผู้ดูแล (PM)</div>
                                    <div class="text-sm font-bold text-[#4A148C]">{{ item.project_manager.name }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 items-end">
                        <div class="flex items-center gap-2">
                            <a :href="route('work-items.export-pdf', item.id)" target="_blank" class="bg-white border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded text-sm font-bold text-gray-700 flex items-center gap-2 transition">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Export PDF
                            </a>
                            <button v-if="canEdit" @click="openEditModal(item)" class="bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded text-sm font-bold text-gray-600">แก้ไขข้อมูล</button>
                        </div>

                        <button v-if="canEdit && !isParent" @click="openUpdateProgressModal" class="mt-2 bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-4 py-2 rounded-lg text-sm font-bold shadow flex items-center gap-2 transform hover:-translate-y-0.5 transition">
                            <span>📢 อัปเดตความคืบหน้า</span>
                        </button>
                    </div>
                </div>

                <div class="mt-6 p-4 rounded-xl border border-gray-200 relative overflow-hidden" :class="projectHealth.bg">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-4">
                            <span class="flex items-center gap-2 text-xs font-bold text-gray-700">
                                Progress
                                <span v-if="isParent" class="bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full text-[10px] border border-purple-300">Auto-Calculated</span>
                            </span>

                            <div class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold text-white shadow-sm" :class="projectHealth.color">
                                <span>{{ projectHealth.icon }}</span>
                                <span>{{ projectHealth.text }}</span>
                            </div>
                        </div>
                        <span class="text-2xl font-black text-[#4A148C]">{{ item.progress }}%</span>
                    </div>

                    <div class="w-full bg-white h-4 rounded-full overflow-hidden shadow-inner border border-gray-200">
                        <div class="bg-gradient-to-r from-[#7A2F8F] to-[#9C27B0] h-4 rounded-full transition-all duration-1000 ease-out flex items-center justify-end pr-2 text-[9px] text-white font-bold" :style="`width:${item.progress}%`"></div>
                    </div>

                    <p class="text-[10px] text-gray-400 mt-2 text-right" v-if="isParent">* คำนวณอัตโนมัติจากงานย่อยตามน้ำหนักงาน (Weight)</p>
                </div>
            </div>

            <div class="border-b border-gray-200 flex space-x-8 overflow-x-auto">
                <button @click="activeTab='overview'" :class="activeTab==='overview'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">แผนงาน (Gantt)</button>
                <button @click="activeTab='issues'" :class="activeTab==='issues'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap flex gap-2 items-center transition-colors"><span>⚠️ ปัญหา/ความเสี่ยง</span><span v-if="item.issues?.length" class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{{ item.issues.length }}</span></button>
                <button @click="activeTab='files'" :class="activeTab==='files'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">เอกสาร ({{ item.attachments?.length || 0 }})</button>
                <button @click="activeTab='logs'" :class="activeTab==='logs'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">ประวัติ</button>
            </div>

            <div v-show="activeTab==='overview'" class="flex flex-col lg:flex-row gap-4 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm h-[700px] animate-fade-in">
                <div class="w-full lg:w-2/5 border-r border-gray-200 flex flex-col h-full bg-white overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b flex justify-between items-center h-[50px]">
                        <h3 class="text-xs font-bold text-gray-600">รายการงานย่อย</h3>
                        <button v-if="canEdit" @click="openCreateModal" class="text-[#7A2F8F] hover:bg-purple-50 p-1 rounded font-bold text-lg" title="เพิ่มงานย่อย">+</button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] uppercase text-gray-500 font-bold sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-4 py-2">ชื่องาน</th>
                                    <th class="px-2 py-2 text-center w-28">ความคืบหน้า</th>
                                    <th class="px-2 py-2 text-center w-16">Weight</th>
                                    <th class="px-2 py-2 text-center">เริ่ม</th>
                                    <th class="px-2 py-2 text-center">สิ้นสุด</th>
                                    <th v-if="canEdit" class="px-2 py-2 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                                <tr v-for="child in item.children" :key="child.id" class="hover:bg-purple-50 group transition" :class="{'opacity-60 bg-gray-50 grayscale': !child.is_active}">
                                    <td class="px-4 py-3 font-medium border-r border-dashed">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="!child.is_active ? 'bg-gray-400' : (child.type==='project'?'bg-[#7A2F8F]':'bg-[#FDB913]')"></div>
                                            <Link :href="route('work-items.show', child.id)" class="truncate max-w-[150px] hover:text-[#7A2F8F] font-bold text-gray-700 inline-flex items-center gap-1 align-bottom">
                                                <span class="truncate">{{ child.name }}</span>
                                                <span v-if="!child.is_active" class="text-[9px] text-gray-400 font-normal shrink-0">(ยกเลิก)</span>
                                                <span v-if="hasChildDateWarning(child)" class="text-sm shrink-0 cursor-help" title="⚠️ ระยะเวลาไม่อยู่ในช่วงของงานหลัก">⚠️</span>
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-center border-r border-dashed">
                                        <div class="flex items-center gap-2 justify-center">
                                            <div class="w-12 bg-gray-200 rounded-full h-2">
                                                <div class="h-2 rounded-full transition-all duration-500" :class="!child.is_active ? 'bg-gray-400' : 'bg-[#7A2F8F]'" :style="{ width: (child.progress || 0) + '%' }"></div>
                                            </div>
                                            <span class="text-[10px] font-bold text-gray-600 w-6 text-right">{{ child.progress || 0 }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-center text-gray-600 border-r border-dashed bg-gray-50/50 font-mono">
                                        {{ child.weight }}
                                    </td>
                                    <td class="px-2 py-3 text-center text-gray-500 whitespace-nowrap">{{ formatDate(child.planned_start_date) }}</td>
                                    <td class="px-2 py-3 text-center text-gray-500 whitespace-nowrap">{{ formatDate(child.planned_end_date) }}</td>
                                    <td v-if="canEdit" class="px-1 py-3 text-center w-16">
                                        <div class="flex justify-center gap-2">
                                            <button @click="openEditModal(child)" class="text-blue-500 hover:scale-110" title="แก้ไข">✏️</button>
                                            <button @click="deleteItem(child.id)" class="text-red-500 hover:scale-110" title="ลบ">🗑</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!item.children || item.children.length === 0">
                                    <td colspan="6" class="text-center py-8 text-gray-400">ยังไม่มีงานย่อย</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="w-full lg:w-3/5 h-full">
                    <GanttChart :task-id="item.id" :task-name="item.name" />
                </div>
            </div>

            <div v-show="activeTab==='issues'" class="space-y-6 animate-fade-in">
                <div class="flex justify-between items-center"><h3 class="font-bold text-gray-700">รายการปัญหาและความเสี่ยง ({{ item.issues?.length || 0 }})</h3>
                <button v-if="canEdit" @click="openCreateIssue" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow flex items-center gap-2"><span>+ แจ้งปัญหาใหม่</span></button></div>
                <div v-if="!item.issues?.length" class="p-12 text-center text-gray-400 border rounded-xl bg-white border-dashed"><div class="text-4xl mb-2">🎉</div>ยังไม่มีปัญหา</div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="issue in item.issues" :key="issue.id" @click="openViewIssue(issue)" class="bg-white p-5 rounded-xl shadow-sm border border-l-4 transition hover:shadow-md relative group cursor-pointer hover:bg-gray-50" :class="issue.type==='issue'?'border-l-red-500':'border-l-orange-400'">
                        <div class="flex justify-between items-start mb-2"><span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border" :class="getSeverityColor(issue.severity)">{{ issue.severity }} priority</span><span class="text-xs font-medium">{{ getStatusBadge(issue.status) }}</span></div>
                        <h4 class="font-bold text-gray-800 text-lg mb-1">{{ issue.title }}</h4>
                        <p class="text-xs text-gray-500 mb-3 line-clamp-2">{{ issue.description }}</p>
                        <div class="bg-gray-50 p-2 rounded text-xs text-gray-600 border border-gray-100 mb-3 line-clamp-1"><span class="font-bold text-gray-700">💡 ทางแก้:</span> {{ issue.solution || 'ยังไม่ระบุ' }}</div>
                        <div class="flex justify-between items-center text-[10px] text-gray-400 border-t border-gray-100 pt-2 mt-2"><span>{{ issue.user?.name }}</span><div v-if="issue.start_date" class="text-gray-500">📅 {{ formatDate(issue.start_date) }}</div></div>
                        <div v-if="canEdit" class="absolute top-2 right-2 hidden group-hover:flex gap-1"><button @click.stop="openEditIssue(issue)" class="bg-white p-1 rounded border">✏️</button><button @click.stop="deleteIssue(issue.id)" class="bg-white p-1 rounded border text-red-500">🗑</button></div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab==='files'" class="space-y-6 animate-fade-in">
                <div v-if="canEdit" class="bg-gray-50 p-4 rounded-xl border border-dashed border-gray-300">
                    <div class="flex gap-4 items-end flex-wrap">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-bold text-gray-500 mb-1">เลือกไฟล์</label>
                            <input type="file" @change="fileForm.file = $event.target.files[0]" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-[#7A2F8F] hover:file:bg-purple-100 transition">
                        </div>
                        <div class="w-40">
                            <label class="block text-xs font-bold text-gray-500 mb-1">หมวดหมู่</label>
                            <select v-model="fileForm.category" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                                <option value="general">ทั่วไป</option>
                                <option value="contract">สัญญา</option>
                                <option value="invoice">ใบแจ้งหนี้/เบิกจ่าย</option>
                                <option value="report">รายงาน</option>
                                <option value="tor">TOR</option>
                            </select>
                        </div>
                        <button @click="uploadFile" :disabled="fileForm.processing || !fileForm.file" class="bg-[#7A2F8F] hover:bg-[#5e2270] text-white px-4 py-2 rounded-lg text-sm font-bold shadow disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ fileForm.processing ? 'กำลังอัปโหลด...' : 'อัปโหลด' }}
                        </button>
                    </div>
                    <div v-if="fileForm.errors.file" class="text-red-500 text-xs mt-1">{{ fileForm.errors.file }}</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div v-for="file in filteredFiles" :key="file.id" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex gap-4 hover:shadow-md transition">
                        <div class="w-16 h-16 shrink-0 rounded-lg bg-gray-100 flex items-center justify-center text-2xl overflow-hidden">
                            <img v-if="isImage(file.file_type)" :src="`/storage/${file.file_path}`" class="w-full h-full object-cover cursor-pointer" @click="downloadFile(file.id)">
                            <span v-else>{{ getFileIcon(file.file_type) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase mb-1 inline-block" :class="getCategoryBadge(file.category||'general')">{{ file.category||'GENERAL' }}</span><span class="text-[10px] text-gray-400">{{ formatFileSize(file.file_size||0) }}</span></div>
                            <h4 class="text-sm font-bold text-gray-700 truncate cursor-pointer hover:text-[#7A2F8F]" @click="downloadFile(file.id)">{{ file.file_name }}</h4>
                            <div class="flex gap-3 mt-2"><button @click="downloadFile(file.id)" class="text-xs font-bold text-blue-600">⬇ ดาวน์โหลด</button><button v-if="canEdit" @click="deleteFile(file.id)" class="text-xs font-bold text-red-500">🗑 ลบ</button></div>
                        </div>
                    </div>
                    <div v-if="!filteredFiles.length" class="col-span-3 text-center text-gray-400 py-8">ยังไม่มีเอกสาร</div>
                </div>
            </div>

            <div v-show="activeTab==='logs'" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-fade-in">
                <div class="mb-6">
                    <div class="flex gap-3">
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold text-gray-600 shrink-0">
                            {{ page.props.auth.user.name.charAt(0) }}
                        </div>
                        <div class="flex-1">
                            <textarea v-model="commentForm.body" placeholder="เขียนความคิดเห็นหรือบันทึกช่วยจำ..." class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] text-sm resize-none" rows="3"></textarea>
                            <div class="flex justify-end mt-2">
                                <button @click="submitComment" :disabled="commentForm.processing || !commentForm.body" class="bg-[#7A2F8F] hover:bg-[#5e2270] text-white px-4 py-2 rounded-lg text-sm font-bold shadow disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ commentForm.processing ? 'กำลังส่ง...' : 'ส่งข้อความ' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div v-for="item in historyLogs.data" :key="item.id + item.timeline_type" class="flex gap-4 relative group">
                        <div class="w-10 flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                 :class="item.timeline_type === 'comment' ? 'bg-blue-500' : (item.action === 'DELETE' ? 'bg-red-500' : 'bg-gray-400')">
                                {{ item.user ? item.user.name.charAt(0) : 'S' }}
                            </div>
                            <div class="w-0.5 bg-gray-200 h-full mt-2 group-last:hidden"></div>
                        </div>

                        <div class="flex-1 pb-2 pt-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="font-bold text-gray-800 text-sm">{{ item.user ? item.user.name : 'System' }}</span>
                                    <span class="text-xs text-gray-500 ml-2">
                                        {{ item.timeline_type === 'comment' ? 'แสดงความคิดเห็น' : `${item.action} ${item.model_type}` }}
                                    </span>
                                </div>
                                <span class="text-[10px] text-gray-400 whitespace-nowrap">{{ new Date(item.created_at).toLocaleString('th-TH') }}</span>
                            </div>

                            <div v-if="item.timeline_type === 'comment'" class="mt-1 text-sm text-gray-700 bg-blue-50 p-3 rounded-lg border border-blue-100 whitespace-pre-line">
                                {{ item.body }}
                            </div>

                            <div v-else class="mt-1 text-xs text-gray-500 bg-gray-50 p-2 rounded border border-gray-100 inline-block w-full">
                                <span v-if="item.model_type==='Attachment'">{{ item.action==='CREATE' ? 'อัปโหลด:' : 'ลบไฟล์:' }} <b>{{ item.changes?.file_name || item.changes?.after?.file_name }}</b></span>
                                <div v-else-if="item.model_type==='WorkItem' && item.action==='UPDATE'">
                                    <span v-for="(val, k) in item.changes?.after" :key="k" class="mr-2 block">
                                        {{ k }}: <span class="text-red-400 line-through">{{ item.changes?.before?.[k] }}</span> ➜ <span class="text-green-600 font-bold">{{ val }}</span>
                                    </span>
                                </div>
                                <span v-else>{{ item.model_type }} #{{ item.model_id }} ({{ item.action }})</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-between items-center" v-if="historyLogs.links.length > 3">
                    <div class="flex flex-wrap gap-1">
                        <Link v-for="(link, key) in historyLogs.links" :key="key" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded-md text-sm transition-colors border"
                            :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 border-gray-300 hover:bg-purple-50'"
                            :preserve-scroll="true" />
                    </div>
                    <div class="text-xs text-gray-500">
                        แสดง {{ historyLogs.from }} ถึง {{ historyLogs.to }} จาก {{ historyLogs.total }} รายการ
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                <div class="absolute inset-0" @click="closeMainModalSafely"></div>

                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl h-[90vh] flex flex-col relative z-10">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913] shrink-0">
                        <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                        <button @click="closeMainModalSafely" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submit" class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">งานภายใต้ (สังกัด)</label>
                            <div class="w-full rounded-lg border border-gray-200 bg-gray-100 p-2 text-sm text-gray-600">
                                📂 {{ parentNameDisplay }}
                            </div>
                            <input type="hidden" v-model="form.parent_id">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500 focus:ring-red-500': form.errors.name}">
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด (Description)</label>
                            <textarea v-model="form.description" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] text-sm" :class="{'border-red-500 focus:ring-red-500': form.errors.description}" rows="3" placeholder="ระบุรายละเอียด..."></textarea>
                            <div v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-purple-50 p-3 rounded-lg border border-purple-100">
                            <div class="col-span-2 text-xs font-bold text-[#4A148C] uppercase">สังกัดหน่วยงาน</div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">กอง <span class="text-red-500">*</span></label>
                                <select v-model="form.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.division_id}">
                                    <option value="">-- เลือกกอง --</option>
                                    <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                                </select>
                                <div v-if="form.errors.division_id" class="text-red-500 text-xs mt-1">{{ form.errors.division_id }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label>
                                <select v-model="form.department_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.department_id}" :disabled="!form.division_id">
                                    <option value="">-- ไม่ระบุ (สังกัดกอง) --</option>
                                    <option v-for="dept in modalDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                </select>
                                <div v-if="form.errors.department_id" class="text-red-500 text-xs mt-1">{{ form.errors.department_id }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ผู้ดูแลโปรเจค (PM)</label>
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
                                <select v-model="form.type" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.type}">
                                    <option value="plan">แผนงาน (Plan)</option>
                                    <option value="project">โครงการ (Project)</option>
                                    <option value="task">งานย่อย (Task)</option>
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
                                <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ</label>
                                <select v-model="form.status" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.status}">
                                    <option value="pending">รอเริ่ม (Pending)</option>
                                    <option value="in_progress">กำลังดำเนินการ (In Progress)</option>
                                    <option value="completed">เสร็จสิ้น (Completed)</option>
                                    <option value="delayed">ล่าช้า (Delayed)</option>
                                    <option value="cancelled">ยกเลิก (Cancelled)</option>
                                </select>
                                <div v-if="form.errors.status" class="text-red-500 text-xs mt-1">{{ form.errors.status }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label>
                                <input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': form.errors.planned_start_date}">
                                <div v-if="form.errors.planned_start_date" class="text-red-500 text-xs mt-1">{{ form.errors.planned_start_date }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label>
                                <input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': form.errors.planned_end_date}">
                                <div v-if="form.errors.planned_end_date" class="text-red-500 text-xs mt-1">{{ form.errors.planned_end_date }}</div>
                            </div>
                        </div>
                    </form>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
                        <button type="button" @click="closeMainModalSafely" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-bold">ยกเลิก</button>
                        <button type="submit" @click="submit" class="px-5 py-2.5 bg-[#7A2F8F] hover:bg-[#5e2270] text-white rounded-lg font-bold shadow-md" :disabled="form.processing">
                            <span v-if="form.processing">กำลังบันทึก...</span>
                            <span v-else>บันทึก</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showUpdateProgressModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                <div class="absolute inset-0" @click="closeProgressModalSafely"></div>

                <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl relative z-10">
                    <div class="bg-gradient-to-r from-[#FDB913] to-[#ffcc4d] px-6 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-[#4A148C]">📢 รายงานความคืบหน้า</h3>
                        <button @click="closeProgressModalSafely" class="text-[#4A148C] font-bold text-xl hover:bg-white/20 rounded w-8 h-8 flex items-center justify-center">&times;</button>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">ความคืบหน้า (%)</label>
                            <div class="flex items-center gap-3">
                                <input type="range" v-model="updateProgressForm.progress" min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#7A2F8F]">
                                <input type="number" v-model="updateProgressForm.progress" min="0" max="100" class="w-20 text-center rounded-lg border-gray-300 focus:ring-[#7A2F8F] font-bold text-lg text-[#7A2F8F]" :class="{'border-red-500': updateProgressForm.errors.progress}">
                            </div>
                            <div v-if="updateProgressForm.errors.progress" class="text-red-500 text-xs mt-1">{{ updateProgressForm.errors.progress }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียดการทำงาน <span class="text-red-500">*</span></label>
                            <textarea v-model="updateProgressForm.comment" rows="3" class="w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] text-sm" :class="{'border-red-500': updateProgressForm.errors.comment}" placeholder="ระบุสิ่งที่ทำเสร็จ หรือสาเหตุที่ล่าช้า..."></textarea>
                            <div v-if="updateProgressForm.errors.comment" class="text-red-500 text-xs mt-1">{{ updateProgressForm.errors.comment }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">แนบไฟล์/รูปภาพ (ถ้ามี)</label>
                            <input type="file" multiple @change="updateProgressForm.attachments = $event.target.files" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-[#7A2F8F] hover:file:bg-purple-100 transition">
                            <div v-if="updateProgressForm.errors.attachments" class="text-red-500 text-xs mt-1">{{ updateProgressForm.errors.attachments }}</div>
                        </div>

                        <div class="pt-2 border-t border-gray-100 flex justify-end gap-3">
                            <button @click="closeProgressModalSafely" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700 font-bold text-sm">ยกเลิก</button>
                            <button @click="submitProgressUpdate" :disabled="updateProgressForm.processing || !updateProgressForm.comment" class="px-4 py-2 bg-[#7A2F8F] hover:bg-purple-800 text-white rounded-lg font-bold text-sm shadow disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ updateProgressForm.processing ? 'กำลังบันทึก...' : 'ส่งรายงาน' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showIssueModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                 <div class="absolute inset-0" @click="closeIssueModalSafely"></div>

                 <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl relative z-10">
                    <div class="bg-red-500 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-white">⚠️ ปัญหา</h3>
                        <button @click="closeIssueModalSafely" class="text-white font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submitIssue" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700">หัวข้อ <span class="text-red-500">*</span></label>
                            <input v-model="issueForm.title" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.title}">
                            <div v-if="issueForm.errors.title" class="text-red-500 text-xs mt-1">{{ issueForm.errors.title }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">ประเภท</label>
                                <select v-model="issueForm.type" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.type}">
                                    <option value="issue">Issue</option>
                                    <option value="risk">Risk</option>
                                </select>
                                <div v-if="issueForm.errors.type" class="text-red-500 text-xs mt-1">{{ issueForm.errors.type }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">ความรุนแรง</label>
                                <select v-model="issueForm.severity" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.severity}">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                                <div v-if="issueForm.errors.severity" class="text-red-500 text-xs mt-1">{{ issueForm.errors.severity }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700">รายละเอียด</label>
                            <textarea v-model="issueForm.description" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.description}"></textarea>
                            <div v-if="issueForm.errors.description" class="text-red-500 text-xs mt-1">{{ issueForm.errors.description }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700">ทางแก้</label>
                            <textarea v-model="issueForm.solution" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.solution}"></textarea>
                            <div v-if="issueForm.errors.solution" class="text-red-500 text-xs mt-1">{{ issueForm.errors.solution }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">สถานะ</label>
                                <select v-model="issueForm.status" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.status}">
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                                <div v-if="issueForm.errors.status" class="text-red-500 text-xs mt-1">{{ issueForm.errors.status }}</div>
                            </div>
                            <div class="col-span-2 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">เริ่ม</label>
                                    <input v-model="issueForm.start_date" type="date" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.start_date}">
                                    <div v-if="issueForm.errors.start_date" class="text-red-500 text-xs mt-1">{{ issueForm.errors.start_date }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700">สิ้นสุด</label>
                                    <input v-model="issueForm.end_date" type="date" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.end_date}">
                                    <div v-if="issueForm.errors.end_date" class="text-red-500 text-xs mt-1">{{ issueForm.errors.end_date }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2">
                            <button type="button" @click="closeIssueModalSafely" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg">ยกเลิก</button>
                            <button type="submit" class="px-5 py-2.5 bg-red-500 text-white rounded-lg font-bold" :disabled="issueForm.processing">
                                <span v-if="issueForm.processing">กำลังบันทึก...</span>
                                <span v-else>บันทึก</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="showViewIssueModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showViewIssueModal=false">
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                    <div class="px-6 py-4 text-white flex justify-between" :class="getSeverityHeaderClass(selectedIssue.severity)"><div class="flex items-center gap-2"><span class="text-2xl">{{ selectedIssue.type==='issue'?'🔥':'⚠️' }}</span><h3 class="text-lg font-bold">{{ selectedIssue.title }}</h3></div><button @click="showViewIssueModal=false" class="text-xl">&times;</button></div>
                    <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="flex justify-between"><span>สถานะ: {{ getStatusBadge(selectedIssue.status) }}</span><span>ความรุนแรง: {{ selectedIssue.severity }}</span></div>
                        <div class="bg-gray-50 p-4 rounded border"><h4 class="font-bold text-gray-500">รายละเอียด</h4><p>{{ selectedIssue.description }}</p></div>
                        <div class="bg-blue-50 p-4 rounded border"><h4 class="font-bold text-blue-500">ทางแก้</h4><p>{{ selectedIssue.solution }}</p></div>
                        <div class="text-sm text-gray-500">ช่วงเวลา: {{ formatDate(selectedIssue.start_date) }} - {{ formatDate(selectedIssue.end_date) }}</div>
                    </div>
                    <div v-if="canEdit" class="bg-gray-50 px-6 py-4 flex justify-between border-t"><button @click="deleteIssue(selectedIssue.id)" class="text-red-500">ลบ</button><button @click="openEditIssue(selectedIssue)" class="bg-purple-600 text-white px-4 py-2 rounded">แก้ไข</button></div>
                </div>
            </div>

            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/30 backdrop-blur-sm animate-fade-in">
                <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center transform scale-100 transition-transform">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">บันทึกสำเร็จ!</h3>
                    <p class="text-gray-500 mt-2">ข้อมูลของคุณถูกอัปเดตเรียบร้อยแล้ว</p>
                </div>
            </div>
        </Teleport>
    </PeaSidebarLayout>
</template>
