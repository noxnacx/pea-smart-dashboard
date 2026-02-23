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
    divisions: Array,
    workItemTypes: { type: Array, default: () => [] }
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
const formatDateForInput = (dateString) => dateString ? String(dateString).split('T')[0].split(' ')[0] : '';
const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024, sizes = ['B', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const statusColor = (status) => ({ completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', in_active: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700', cancelled: 'bg-gray-200 text-gray-500' }[status] || 'bg-gray-100');

// 🚀 --- Project Health Logic (คำนวณ Progress แบบแม่นยำ) ---
const projectHealth = computed(() => {
    const { status, planned_start_date, planned_end_date, progress } = props.item;
    const currentProgress = Number(progress) || 0;

    if (status === 'cancelled') return { color: 'bg-gray-400', bg: 'bg-gray-50', text: 'ยกเลิกโครงการ', icon: '⚪' };
    if (currentProgress >= 100 || status === 'completed') return { color: 'bg-green-500', bg: 'bg-green-50', text: 'เสร็จสมบูรณ์', icon: '🏆' };
    if (currentProgress > 0) return { color: 'bg-blue-500', bg: 'bg-blue-50', text: 'กำลังดำเนินการ', icon: '⏳' };

    if (!planned_start_date || !planned_end_date) return { color: 'bg-gray-400', bg: 'bg-gray-50', text: 'รอเริ่มดำเนินการ', icon: '📅' };

    const start = new Date(planned_start_date).getTime();
    const end = new Date(planned_end_date).getTime();
    const now = new Date().getTime();

    if (now > end) return { color: 'bg-red-600', bg: 'bg-red-50', text: 'Overdue (เกินกำหนด)', icon: '🔥' };
    if (now < start) return { color: 'bg-gray-400', bg: 'bg-gray-50', text: 'รอเริ่มดำเนินการ', icon: '⏳' };

    return { color: 'bg-yellow-500', bg: 'bg-yellow-50', text: 'ถึงกำหนดเริ่ม (แต่ยังไม่เริ่มงาน)', icon: '🟡' };
});

const dateValidationWarnings = computed(() => {
    const warnings = [], parent = props.item.parent;
    if (!parent) return warnings;
    const myStart = props.item.planned_start_date ? new Date(props.item.planned_start_date) : null, myEnd = props.item.planned_end_date ? new Date(props.item.planned_end_date) : null;
    const parentStart = parent.planned_start_date ? new Date(parent.planned_start_date) : null, parentEnd = parent.planned_end_date ? new Date(parent.planned_end_date) : null;
    if (myStart && parentStart && myStart < parentStart) warnings.push(`⚠️ วันเริ่ม (${formatDate(props.item.planned_start_date)}) ก่อนวันเริ่มของงานแม่ (${formatDate(parent.planned_start_date)})`);
    if (myEnd && parentEnd && myEnd > parentEnd) warnings.push(`⚠️ วันจบ (${formatDate(props.item.planned_end_date)}) เกินวันจบของงานแม่ (${formatDate(parent.planned_end_date)})`);
    return warnings;
});

const hasChildDateWarning = (child) => {
    const pStart = props.item.planned_start_date ? new Date(props.item.planned_start_date).getTime() : null, pEnd = props.item.planned_end_date ? new Date(props.item.planned_end_date).getTime() : null;
    const cStart = child.planned_start_date ? new Date(child.planned_start_date).getTime() : null, cEnd = child.planned_end_date ? new Date(child.planned_end_date).getTime() : null;
    return (cStart && pStart && cStart < pStart) || (cEnd && pEnd && cEnd > pEnd);
};

const getSeverityColor = (s) => ({ critical: 'bg-red-100 text-red-700 border-red-200', high: 'bg-orange-100 text-orange-700 border-orange-200', medium: 'bg-yellow-100 text-yellow-700 border-yellow-200', low: 'bg-green-100 text-green-700 border-green-200' }[s] || 'bg-gray-100');
const getSeverityHeaderClass = (s) => ({ critical: 'bg-red-500', high: 'bg-orange-500', medium: 'bg-yellow-500', low: 'bg-green-500' }[s] || 'bg-gray-500');
const getStatusBadge = (s) => ({ open: '🔴 รอแก้ไข', in_progress: '🟡 กำลังดำเนินการ', resolved: '🟢 แก้ไขแล้ว' }[s] || s);
const getCategoryBadge = (c) => ({ 'contract': 'bg-blue-100 text-blue-700', 'invoice': 'bg-green-100 text-green-700', 'report': 'bg-purple-100 text-purple-700', 'tor': 'bg-orange-100 text-orange-700', 'general': 'bg-gray-100 text-gray-700' }[c] || 'bg-gray-100 text-gray-700');
const getFileIcon = (m) => m?.includes('pdf')?'📄':m?.includes('word')||m?.includes('doc')?'📝':m?.includes('sheet')||m?.includes('excel')?'📊':'📎';
const isImage = (m) => m?.startsWith('image/');

const breadcrumbs = computed(() => {
    let crumbs = [], curr = props.item;
    crumbs.push({ name: curr.name, url: null });
    while (curr.parent) { curr = curr.parent; crumbs.push({ name: curr.name, url: route('work-items.show', curr.id) }); }
    crumbs.push({ name: 'Dashboard', url: route('dashboard') });
    return crumbs.reverse();
});

// 🚀 --- Auto & Manual Milestone Logic ---
const groupedMilestones = computed(() => {
    let groups = {};
    if (props.item.children) {
        const extractTasks = (children) => {
            children.forEach(child => {
                if (child.planned_end_date && child.status !== 'cancelled') {
                    const date = new Date(child.planned_end_date);
                    const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                    const label = date.toLocaleDateString('th-TH', { month: 'long', year: 'numeric' });

                    if (!groups[key]) {
                        groups[key] = { key: key, label: label, tasks: [], timestamp: new Date(date.getFullYear(), date.getMonth(), 1).getTime(), manual: null };
                    }
                    groups[key].tasks.push(child);
                }
                if (child.children && child.children.length > 0) extractTasks(child.children);
            });
        };
        extractTasks(props.item.children);
    }
    if (props.item.milestones && props.item.milestones.length > 0) {
        props.item.milestones.forEach(ms => {
            if(!ms.due_date) return;
            const d = new Date(ms.due_date);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const label = d.toLocaleDateString('th-TH', { month: 'long', year: 'numeric' });

            if (!groups[key]) {
                groups[key] = { key: key, label: label, tasks: [], timestamp: new Date(d.getFullYear(), d.getMonth(), 1).getTime(), manual: null };
            }
            groups[key].manual = ms;
        });
    }
    return Object.values(groups).sort((a, b) => a.timestamp - b.timestamp);
});

const getGroupStatusBg = (group) => {
    if (!group.tasks || group.tasks.length === 0) return 'bg-gray-400 shadow-sm border-white';
    const statuses = group.tasks.map(t => t.status);
    if (statuses.some(s => s === 'delayed')) return 'bg-red-500 shadow-md border-white';
    if (statuses.some(s => s === 'in_progress')) return 'bg-blue-500 shadow-md border-white';
    if (statuses.length > 0 && statuses.every(s => s === 'completed')) return 'bg-green-500 shadow-md border-white';
    return 'bg-gray-400 shadow-sm border-white';
};

const getTaskStatusBg = (task) => {
    if (task.status === 'completed' || task.progress === 100) return 'bg-green-500';
    if (task.status === 'delayed') return 'bg-red-500';
    if (task.status === 'in_progress' || task.progress > 0) return 'bg-blue-500';
    return 'bg-gray-400';
};

// 🚀 --- ตัวแปรจัดการ Checkbox (Bulk Action) ---
const selectedChildren = ref([]);

const toggleAllChildren = (e) => {
    if (e.target.checked && props.item.children) {
        selectedChildren.value = props.item.children.map(c => c.id);
    } else {
        selectedChildren.value = [];
    }
};

const canBulkUpdateProgress = computed(() => {
    if (selectedChildren.value.length === 0) return false;
    return selectedChildren.value.every(id => {
        const child = props.item.children.find(c => c.id === id);
        return child && (!child.children || child.children.length === 0);
    });
});

// UI States สำหรับ Bulk Manage
const showBulkManageModal = ref(false);
const showBulkEditModal = ref(false);
const showBulkProgressModal = ref(false);

const bulkForm = useForm({
    ids: [], action: '', description: '', division_id: '', department_id: '', project_manager_id: null, pm_name: '', type: '', weight: '', bulk_status_mode: 'no_change'
});
const bulkProgressForm = useForm({ ids: [], action: 'update_progress', progress: 0, comment: '' });

const submitBulkDeleteFromMenu = () => {
    showBulkManageModal.value = false;
    if (confirm(`🚨 ยืนยันลบข้อมูลที่เลือกจำนวน ${selectedChildren.value.length} รายการ รวมถึงงานย่อยของมันทั้งหมด ใช่หรือไม่?`)) {
        useForm({ ids: selectedChildren.value, action: 'delete' }).post(route('work-items.bulk'), {
            onSuccess: () => { selectedChildren.value = []; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); }
        });
    }
};

const openBulkEditModal = () => {
    showBulkManageModal.value = false;
    bulkForm.reset();
    bulkForm.clearErrors();
    bulkForm.ids = selectedChildren.value;
    bulkForm.action = 'update_general';
    showBulkEditModal.value = true;
};

const submitBulkEdit = () => {
    bulkForm.post(route('work-items.bulk'), {
        onSuccess: () => { showBulkEditModal.value = false; selectedChildren.value = []; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); }
    });
};

const openBulkProgressModalFromMenu = () => {
    showBulkManageModal.value = false;
    bulkProgressForm.reset();
    bulkProgressForm.clearErrors();
    bulkProgressForm.ids = selectedChildren.value;
    showBulkProgressModal.value = true;
};

const submitBulkProgress = () => {
    bulkProgressForm.post(route('work-items.bulk'), {
        onSuccess: () => { showBulkProgressModal.value = false; selectedChildren.value = []; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); }
    });
};


// --- Modals Logic & Forms ---
const showModal = ref(false), isEditing = ref(false), modalTitle = ref('');
const showIssueModal = ref(false), showViewIssueModal = ref(false), selectedIssue = ref(null);
const showUpdateProgressModal = ref(false);
const parentNameDisplay = ref('');

const form = useForm({ id: null, parent_id: null, name: '', description: '', type: 'task', budget: 0, progress: 0, status: 'in_active', planned_start_date: '', planned_end_date: '', division_id: '', department_id: '', pm_name: '', project_manager_id: null, weight: 1 });
const updateProgressForm = useForm({ progress: 0, comment: '', attachments: [] });
const issueForm = useForm({ id: null, title: '', type: 'issue', severity: 'medium', status: 'open', description: '', solution: '', start_date: '', end_date: '' });
const fileForm = useForm({ file: null, category: 'general' });
const commentForm = useForm({ body: '' });

// 🚀 ฟอร์มสำหรับแก้ไข Milestone Manual
const showManualModal = ref(false);
const manualForm = useForm({ id: null, title: '', due_date: '', status: 'pending' });
const manualGroupLabel = ref('');

const openManualModal = (group) => {
    manualGroupLabel.value = group.label;
    manualForm.reset();
    manualForm.clearErrors();

    const isCompleted = group.tasks.length > 0 && group.tasks.every(t => t.status === 'completed');
    const autoStatus = isCompleted ? 'completed' : 'pending';

    if (group.manual) {
        manualForm.id = group.manual.id;
        manualForm.title = group.manual.title;
        manualForm.status = autoStatus;
        manualForm.due_date = group.manual.due_date.split('T')[0];
    } else {
        manualForm.id = null;
        manualForm.title = group.tasks.map(t => t.description || t.name).join('\n');
        manualForm.status = autoStatus;

        const [year, month] = group.key.split('-');
        const lastDay = new Date(year, month, 0);
        manualForm.due_date = `${year}-${month}-${String(lastDay.getDate()).padStart(2, '0')}`;
    }
    showManualModal.value = true;
};

const submitManualMilestone = () => {
    const options = { onSuccess: () => { showManualModal.value = false; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); } };
    if (manualForm.id) manualForm.put(route('milestones.update', manualForm.id), options);
    else manualForm.post(route('milestones.store', props.item.id), options);
};

const deleteManualMilestone = () => {
    if(confirm('ยืนยันลบการแก้ไขนี้ และกลับไปใช้ระบบดึงข้อมูลอัตโนมัติ?')) {
        useForm({}).delete(route('milestones.destroy', manualForm.id), { onSuccess: () => showManualModal.value = false });
    }
};

// 🚀 Progress Update (ดึง Comment ล่าสุด และ บังคับแก้ข้อความ)
const lastProgressComment = computed(() => {
    const comments = props.historyLogs?.data?.filter(log => log.timeline_type === 'comment') || [];
    if (comments.length > 0) return comments[0].body.replace(/\n?\(ปรับความคืบหน้า:.*?\)/g, '').trim();
    return '';
});

const openUpdateProgressModal = () => {
    updateProgressForm.reset();
    updateProgressForm.clearErrors();
    updateProgressForm.progress = props.item.progress;
    updateProgressForm.comment = lastProgressComment.value;
    showUpdateProgressModal.value = true;
};

const submitProgressUpdate = () => {
    if (lastProgressComment.value && updateProgressForm.comment.trim() === lastProgressComment.value) {
        updateProgressForm.setError('comment', '⚠️ ไม่อนุญาตให้ใช้ข้อความเดิม! กรุณาเพิ่มหรือแก้ไขรายละเอียดการทำงาน (อย่างน้อย 1 ตัวอักษร)');
        return;
    }
    updateProgressForm.post(route('work-items.update-progress', props.item.id), {
        onSuccess: () => { showUpdateProgressModal.value = false; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); }
    });
};

const fileFilter = ref('all');
const filteredFiles = computed(() => fileFilter.value==='all' ? props.item.attachments||[] : (props.item.attachments||[]).filter(f => f.category === fileFilter.value));
const modalDepartments = computed(() => { if (!form.division_id) return []; const div = props.divisions?.find(d => d.id == form.division_id); return div ? div.departments : []; });
const bulkEditDepartments = computed(() => { if (!bulkForm.division_id) return []; const div = props.divisions?.find(d => d.id == bulkForm.division_id); return div ? div.departments : []; });

const closeMainModalSafely = () => { if (form.isDirty) { if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดใช่หรือไม่?')) { showModal.value = false; form.reset(); form.clearErrors(); } } else { showModal.value = false; form.reset(); form.clearErrors(); } };
const closeProgressModalSafely = () => { if (updateProgressForm.isDirty) { if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดใช่หรือไม่?')) { showUpdateProgressModal.value = false; updateProgressForm.reset(); updateProgressForm.clearErrors(); } } else { showUpdateProgressModal.value = false; updateProgressForm.reset(); updateProgressForm.clearErrors(); } };
const closeIssueModalSafely = () => { if (issueForm.isDirty) { if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดใช่หรือไม่?')) { showIssueModal.value = false; issueForm.reset(); issueForm.clearErrors(); } } else { showIssueModal.value = false; issueForm.reset(); issueForm.clearErrors(); } };

const openCreateModal = () => { isEditing.value = false; modalTitle.value = `สร้างรายการย่อย`; form.reset(); form.clearErrors(); form.parent_id = props.item.id; parentNameDisplay.value = props.item.name; form.type = 'task'; form.division_id = props.item.division_id || ''; form.department_id = props.item.department_id || ''; if (props.item.project_manager) { form.pm_name = props.item.project_manager.name; form.project_manager_id = props.item.project_manager_id; } else if (['pm', 'project_manager'].includes(userRole.value)) { form.pm_name = page.props.auth.user.name; form.project_manager_id = userId.value; } else { form.pm_name = ''; form.project_manager_id = null; } form.status = 'in_active'; form.weight = 1; form.description = ''; showModal.value = true; };
const openEditModal = (t) => { isEditing.value=true; modalTitle.value=`แก้ไข: ${t.name}`; form.clearErrors(); Object.assign(form, t); form.planned_start_date = formatDateForInput(t.planned_start_date); form.planned_end_date = formatDateForInput(t.planned_end_date); form.pm_name = t.project_manager?.name || ''; showModal.value=true; };
const submit = () => { const options = { onSuccess: () => { showModal.value = false; showSuccessModal.value = true; setTimeout(() => showSuccessModal.value = false, 2000); } }; if (isEditing.value) { form.put(route('work-items.update', form.id), options); } else { form.post(route('work-items.store'), options); } };
const deleteItem = (id) => { if(confirm('ยืนยันลบ?')) useForm({}).delete(route('work-items.destroy', id)); };
const openCreateIssue = () => { isEditing.value=false; issueForm.reset(); issueForm.clearErrors(); showIssueModal.value=true; };
const openEditIssue = (issue) => { showViewIssueModal.value=false; isEditing.value=true; issueForm.clearErrors(); issueForm.id=issue.id; issueForm.title=issue.title; issueForm.type=issue.type; issueForm.severity=issue.severity; issueForm.status=issue.status; issueForm.description=issue.description; issueForm.solution=issue.solution; issueForm.start_date=formatDateForInput(issue.start_date); issueForm.end_date=formatDateForInput(issue.end_date); showIssueModal.value=true; };
const openViewIssue = (issue) => { selectedIssue.value=issue; showViewIssueModal.value=true; };
const submitIssue = () => { isEditing.value ? issueForm.put(route('issues.update', issueForm.id), {onSuccess:()=>showIssueModal.value=false}) : issueForm.post(route('issues.store', props.item.id), {onSuccess:()=>showIssueModal.value=false}); };
const deleteIssue = (id) => { if(confirm('ยืนยันลบ?')) { showViewIssueModal.value=false; useForm({}).delete(route('issues.destroy', id)); } };
const uploadFile = () => { if(fileForm.file) { fileForm.post(route('attachments.store', props.item.id), { onSuccess: () => fileForm.reset() }); } };
const deleteFile = (id) => { if(confirm('ลบไฟล์?')) useForm({}).delete(route('attachments.destroy', id)); };
const downloadFile = (id) => window.open(route('attachments.download', id), '_blank');
const submitComment = () => { if(!commentForm.body.trim()) return; commentForm.post(route('comments.store', props.item.id), { onSuccess: () => commentForm.reset(), preserveScroll: true }); };
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
                        <span class="bg-[#7A2F8F] text-white text-xs px-2 py-1 rounded uppercase">{{ item.workType ? item.workType.name : item.type }}</span>
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
                                <div><div class="text-[10px] text-gray-400 uppercase font-bold">กอง</div><div class="text-sm font-bold text-gray-700">{{ item.division.name }}</div></div>
                            </div>
                            <div v-if="item.department" class="flex items-center gap-2 border-l pl-4 border-gray-200">
                                <span class="text-lg">🏷️</span>
                                <div><div class="text-[10px] text-gray-400 uppercase font-bold">แผนก</div><div class="text-sm font-bold text-gray-700">{{ item.department.name }}</div></div>
                            </div>
                            <div v-if="item.project_manager" class="flex items-center gap-2 border-l pl-4 border-gray-200">
                                <span class="text-lg">👤</span>
                                <div><div class="text-[10px] text-gray-400 uppercase font-bold">ผู้ดูแล (PM)</div><div class="text-sm font-bold text-[#4A148C]">{{ item.project_manager.name }}</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 items-end">
                        <div class="flex items-center gap-2">
                            <a :href="route('work-items.export-pdf', item.id)" target="_blank" class="bg-white border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded text-sm font-bold text-gray-700 flex items-center gap-2 transition">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Export PDF
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
                            <span class="flex items-center gap-2 text-xs font-bold text-gray-700">Progress<span v-if="isParent" class="bg-purple-200 text-purple-800 px-2 py-0.5 rounded-full text-[10px] border border-purple-300">Auto-Calculated</span></span>
                            <div class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold text-white shadow-sm" :class="projectHealth.color">
                                <span>{{ projectHealth.icon }}</span><span>{{ projectHealth.text }}</span>
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
                <button @click="activeTab='milestones'" :class="activeTab==='milestones'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap flex items-center gap-1 transition-colors">📍 เป้าหมาย (Timeline)</button>
                <button @click="activeTab='issues'" :class="activeTab==='issues'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap flex gap-2 items-center transition-colors"><span>⚠️ ปัญหา/ความเสี่ยง</span><span v-if="item.issues?.length" class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{{ item.issues.length }}</span></button>
                <button @click="activeTab='files'" :class="activeTab==='files'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">เอกสาร ({{ item.attachments?.length || 0 }})</button>
                <button @click="activeTab='logs'" :class="activeTab==='logs'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">ประวัติ</button>
            </div>

            <div v-show="activeTab==='overview'" class="flex flex-col lg:flex-row gap-4 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm h-[700px] animate-fade-in">
                <div class="w-full lg:w-2/5 border-r border-gray-200 flex flex-col h-full bg-white overflow-hidden relative">
                    <div class="p-3 bg-gray-50 border-b flex justify-between items-center h-[50px]">
                        <div class="flex items-center gap-3">
                            <h3 class="text-xs font-bold text-gray-600">รายการงานย่อย</h3>
                            <button v-if="selectedChildren.length > 0" @click="showBulkManageModal = true" class="bg-purple-100 hover:bg-purple-200 text-purple-700 px-3 py-1 rounded-md text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <span>จัดการแบบกลุ่ม</span>
                                <span class="bg-purple-600 text-white rounded-full px-1.5 py-0.5 text-[9px]">{{ selectedChildren.length }}</span>
                            </button>
                        </div>
                        <button v-if="canEdit" @click="openCreateModal" class="text-[#7A2F8F] hover:bg-purple-50 p-1 rounded font-bold text-lg" title="เพิ่มงานย่อย">+</button>
                    </div>
                    <div class="overflow-y-auto flex-1 pb-24">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] uppercase text-gray-500 font-bold sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th v-if="canEdit" class="px-4 py-2 w-10 text-center">
                                        <input type="checkbox" @change="toggleAllChildren" :checked="item.children && selectedChildren.length === item.children.length && item.children.length > 0" class="rounded border-gray-300 text-[#7A2F8F] focus:ring-[#7A2F8F] cursor-pointer">
                                    </th>
                                    <th class="px-4 py-2">ชื่องาน</th>
                                    <th class="px-2 py-2 text-center w-28">ความคืบหน้า</th>
                                    <th class="px-2 py-2 text-center w-16">Weight</th>
                                    <th class="px-2 py-2 text-center">เริ่ม</th>
                                    <th class="px-2 py-2 text-center">สิ้นสุด</th>
                                    <th v-if="canEdit" class="px-2 py-2 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                                <tr v-for="child in item.children" :key="child.id" class="hover:bg-purple-50 group transition" :class="{'opacity-60 bg-gray-50 grayscale': !child.is_active, 'bg-purple-50/50': selectedChildren.includes(child.id)}">

                                    <td v-if="canEdit" class="px-4 py-3 text-center border-r border-dashed">
                                        <input type="checkbox" v-model="selectedChildren" :value="child.id" class="rounded border-gray-300 text-[#7A2F8F] focus:ring-[#7A2F8F] cursor-pointer">
                                    </td>

                                    <td class="px-4 py-3 font-medium border-r border-dashed">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="!child.is_active ? 'bg-gray-400' : (child.type==='project'?'bg-[#7A2F8F]':'bg-[#FDB913]')"></div>
                                            <Link :href="route('work-items.show', child.id)" class="truncate max-w-[150px] hover:text-[#7A2F8F] font-bold text-gray-700 inline-flex items-center gap-1 align-bottom">
                                                <span class="truncate">{{ child.name }}</span> <span v-if="!child.is_active" class="text-[9px] text-gray-400 font-normal shrink-0">(ยกเลิก)</span>
                                                <span v-if="hasChildDateWarning(child)" class="text-sm shrink-0 cursor-help" title="⚠️ ระยะเวลาไม่อยู่ในช่วงของงานหลัก">⚠️</span>
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-center border-r border-dashed">
                                        <div class="flex items-center gap-2 justify-center">
                                            <div class="w-12 bg-gray-200 rounded-full h-2"><div class="h-2 rounded-full transition-all duration-500" :class="!child.is_active ? 'bg-gray-400' : 'bg-[#7A2F8F]'" :style="{ width: (child.progress || 0) + '%' }"></div></div>
                                            <span class="text-[10px] font-bold text-gray-600 w-6 text-right">{{ child.progress || 0 }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-center text-gray-600 border-r border-dashed bg-gray-50/50 font-mono">{{ child.weight }}</td>
                                    <td class="px-2 py-3 text-center text-gray-500 whitespace-nowrap">{{ formatDate(child.planned_start_date) }}</td>
                                    <td class="px-2 py-3 text-center text-gray-500 whitespace-nowrap">{{ formatDate(child.planned_end_date) }}</td>
                                    <td v-if="canEdit" class="px-1 py-3 text-center w-16">
                                        <div class="flex justify-center gap-2">
                                            <button @click="openEditModal(child)" class="text-blue-500 hover:scale-110" title="แก้ไข">✏️</button>
                                            <button @click="deleteItem(child.id)" class="text-red-500 hover:scale-110" title="ลบ">🗑</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!item.children || item.children.length === 0"><td :colspan="canEdit ? 7 : 6" class="text-center py-8 text-gray-400">ยังไม่มีงานย่อย</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="w-full lg:w-3/5 h-full"><GanttChart :task-id="item.id" :task-name="item.name" /></div>
            </div>

            <div v-show="activeTab==='milestones'" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-fade-in relative">

                <div class="flex justify-between items-center mb-6">
                    <div class="text-left">
                        <h3 class="font-bold text-[#4A148C] text-lg">📍 ลำดับเวลาเป้าหมาย (Delivery Timeline)</h3>
                        <p class="text-xs text-gray-500 mt-1">สรุปไทม์ไลน์เป้าหมายอัตโนมัติจากวันสิ้นสุดของงานย่อยทั้งหมด</p>
                    </div>

                    <a :href="route('work-items.export-milestone', item.id)" target="_blank"
                       class="bg-white border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-bold text-[#4A148C] flex items-center gap-2 shadow-sm transition hover:shadow">
                        <svg class="w-4 h-4 text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Milestone
                    </a>
                </div>

                <div v-if="groupedMilestones.length === 0" class="p-12 text-center text-gray-400 border border-dashed rounded-xl bg-gray-50/50 my-4">
                    <div class="text-4xl mb-3">🎯</div>
                    <p>ยังไม่มีเป้าหมายที่จะเกิดขึ้นในขณะนี้</p>
                    <p class="text-xs mt-1">ระบบจะดึงข้อมูลอัตโนมัติจาก "วันสิ้นสุด (End Date)" ของงานย่อย</p>
                </div>

                <div v-else class="relative w-full overflow-x-auto overflow-y-hidden custom-scrollbar py-56 px-4 min-h-[500px]">
                    <div class="absolute top-[50%] left-0 right-0 min-w-full w-[200%] h-[4px] bg-gray-200 -translate-y-1/2 z-0"></div>

                    <div class="flex items-center min-w-max w-full px-8 gap-16 relative z-10">

                        <div v-for="(group, index) in groupedMilestones" :key="index"
                             class="relative flex-1 min-w-[280px] max-w-[400px] flex flex-col items-center justify-center">

                            <div class="w-6 h-6 rounded-full border-4 shadow-sm z-20 shrink-0 absolute top-[50%] -translate-y-1/2"
                                 :class="getGroupStatusBg(group)"></div>

                            <div class="absolute w-full flex flex-col items-center"
                                 :class="index % 2 === 0 ? 'bottom-6' : 'top-6'">

                                <svg v-if="index % 2 === 0" class="w-6 h-8 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22V4m-4 4l4-4 4 4"></path></svg>

                                <div class="w-full bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:border-[#7A2F8F] transition hover:shadow-md relative"
                                     :class="group.manual ? 'ring-2 ring-purple-400/50 border-purple-300' : ''">

                                    <div class="flex justify-between items-center mb-3 bg-purple-50 rounded py-1 px-3 border border-purple-100">
                                        <h4 class="text-sm font-bold text-[#4A148C] text-center w-full relative">
                                            {{ group.label }}
                                            <button v-if="canEdit" @click="openManualModal(group)" class="absolute right-0 top-0 text-gray-400 hover:text-[#4A148C] transition" title="ปรับแต่งแบบ Manual">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        </h4>
                                    </div>

                                    <div v-if="group.manual" class="space-y-3">
                                        <div class="flex items-start gap-2">
                                            <div class="w-3 h-3 rounded-full mt-1 shrink-0 shadow-sm" :class="getGroupStatusBg(group).split(' ')[0]"></div>
                                            <div class="flex-1">
                                                <p class="text-xs font-bold leading-relaxed text-[#4A148C] whitespace-pre-line">
                                                    {{ group.manual.title }}
                                                </p>
                                                <span class="text-[9px] bg-purple-100 text-purple-600 px-1.5 rounded mt-1 inline-block border border-purple-200">แก้ไข Manual</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="space-y-3">
                                        <div v-for="task in group.tasks" :key="task.id" class="flex items-start gap-2">
                                            <div class="w-3 h-3 rounded-full mt-0.5 shrink-0 shadow-sm" :class="getTaskStatusBg(task)"></div>
                                            <div class="flex-1">
                                                <p class="text-xs font-bold leading-tight text-gray-700">
                                                    {{ task.description ? task.description : task.name }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <svg v-if="index % 2 !== 0" class="w-6 h-8 text-gray-300 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v18m-4-4l4 4 4-4"></path></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="groupedMilestones.length > 0" class="flex justify-center gap-6 mt-6 pt-4 border-t border-gray-100 text-xs text-gray-500">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-green-500"></span> เสร็จสิ้น</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-500"></span> กำลังทำ</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-500"></span> ล่าช้า</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-gray-400"></span> รอเริ่ม</div>
                    <div class="flex items-center gap-2 ml-4 pl-4 border-l border-gray-300"><span class="w-3 h-3 rounded-full bg-white border border-purple-400"></span> แก้ไขแบบ Manual</div>
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
                            <input type="file" @change="fileForm.file = $event.target.files[0]" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-[#7A2F8F] hover:file:bg-purple-100 transition">
                        </div>
                        <div class="w-40">
                            <label class="block text-xs font-bold text-gray-500 mb-1">หมวดหมู่</label>
                            <select v-model="fileForm.category" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                                <option value="general">ทั่วไป</option><option value="contract">สัญญา</option><option value="invoice">ใบแจ้งหนี้/เบิกจ่าย</option><option value="report">รายงาน</option><option value="tor">TOR</option>
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
                <div class="mb-6"><div class="flex gap-3"><div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center font-bold text-gray-600 shrink-0">{{ page.props.auth.user.name.charAt(0) }}</div><div class="flex-1"><textarea v-model="commentForm.body" placeholder="เขียนความคิดเห็น..." class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] text-sm resize-none" rows="3"></textarea><div class="flex justify-end mt-2"><button @click="submitComment" :disabled="commentForm.processing || !commentForm.body" class="bg-[#7A2F8F] hover:bg-[#5e2270] text-white px-4 py-2 rounded-lg text-sm font-bold shadow disabled:opacity-50 disabled:cursor-not-allowed">{{ commentForm.processing ? 'กำลังส่ง...' : 'ส่งข้อความ' }}</button></div></div></div></div>
                <div class="space-y-6">
                    <div v-for="item in historyLogs.data" :key="item.id + item.timeline_type" class="flex gap-4 relative group">
                        <div class="w-10 flex flex-col items-center"><div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" :class="item.timeline_type === 'comment' ? 'bg-blue-500' : (item.action === 'DELETE' ? 'bg-red-500' : 'bg-gray-400')">{{ item.user ? item.user.name.charAt(0) : 'S' }}</div><div class="w-0.5 bg-gray-200 h-full mt-2 group-last:hidden"></div></div>
                        <div class="flex-1 pb-2 pt-1"><div class="flex justify-between items-start"><div><span class="font-bold text-gray-800 text-sm">{{ item.user ? item.user.name : 'System' }}</span><span class="text-xs text-gray-500 ml-2">{{ item.timeline_type === 'comment' ? 'แสดงความคิดเห็น' : `${item.action} ${item.model_type}` }}</span></div><span class="text-[10px] text-gray-400 whitespace-nowrap">{{ new Date(item.created_at).toLocaleString('th-TH') }}</span></div><div v-if="item.timeline_type === 'comment'" class="mt-1 text-sm text-gray-700 bg-blue-50 p-3 rounded-lg border border-blue-100 whitespace-pre-line">{{ item.body }}</div><div v-else class="mt-1 text-xs text-gray-500 bg-gray-50 p-2 rounded border border-gray-100 inline-block w-full"><span v-if="item.model_type==='Attachment'">{{ item.action==='CREATE' ? 'อัปโหลด:' : 'ลบไฟล์:' }} <b>{{ item.changes?.file_name || item.changes?.after?.file_name }}</b></span><div v-else-if="item.model_type==='WorkItem' && item.action==='UPDATE'"><span v-for="(val, k) in item.changes?.after" :key="k" class="mr-2 block">{{ k }}: <span class="text-red-400 line-through">{{ item.changes?.before?.[k] }}</span> ➜ <span class="text-green-600 font-bold">{{ val }}</span></span></div><span v-else>{{ item.model_type }} #{{ item.model_id }} ({{ item.action }})</span></div></div>
                    </div>
                </div>
                <div class="mt-6 flex justify-between items-center" v-if="historyLogs.links.length > 3">
                    <div class="flex flex-wrap gap-1">
                        <Link v-for="(link, key) in historyLogs.links" :key="key" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded-md text-sm transition-colors border" :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 border-gray-300 hover:bg-purple-50'" :preserve-scroll="true" />
                    </div>
                    <div class="text-xs text-gray-500">แสดง {{ historyLogs.from }} ถึง {{ historyLogs.to }} จาก {{ historyLogs.total }} รายการ</div>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <transition enter-active-class="transition ease-out duration-300 transform" enter-from-class="translate-y-full opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition ease-in duration-200 transform" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-full opacity-0">
                <div v-if="selectedChildren.length > 0" class="fixed bottom-10 left-1/2 transform -translate-x-1/2 bg-[#380d6b] text-white px-8 py-3 rounded-full shadow-2xl flex items-center gap-6 z-50 border border-purple-500">
                    <div class="font-bold text-sm">เลือกอยู่ <span class="bg-[#FDB913] text-[#4A148C] px-2 py-0.5 rounded-full mx-1">{{ selectedChildren.length }}</span> รายการ</div>
                    <div class="flex gap-3 border-l border-purple-800 pl-6">
                        <button @click="showBulkManageModal = true" class="px-4 py-1.5 bg-white text-[#4A148C] rounded-full text-xs font-bold hover:bg-gray-100 transition shadow-sm">จัดการ...</button>
                        <button @click="selectedChildren = []" class="text-gray-400 hover:text-white ml-2 text-xl leading-none">&times;</button>
                    </div>
                </div>
            </transition>

            <div v-if="showBulkManageModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showBulkManageModal = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-2xl relative z-10 animate-fade-in">
                    <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100"><h3 class="text-lg font-bold text-gray-800">จัดการงานแบบกลุ่ม</h3><button @click="showBulkManageModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button></div>
                    <div class="p-2 space-y-1">
                        <button @click="openBulkEditModal" class="w-full text-left flex items-center p-3 hover:bg-gray-50 transition border-b border-gray-100 group">
                            <div class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center shrink-0 mr-4"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></div>
                            <div class="flex-1"><h4 class="font-bold text-gray-800 text-sm group-hover:text-yellow-600">แก้ไขข้อมูล / สถานะ</h4><p class="text-[10px] text-gray-500">เปลี่ยนรายละเอียด กอง แผนก ประเภท ฯลฯ</p></div>
                        </button>
                        <button v-if="canBulkUpdateProgress" @click="openBulkProgressModal" class="w-full text-left flex items-center p-3 hover:bg-gray-50 transition border-b border-gray-100 group">
                            <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center shrink-0 mr-4"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                            <div class="flex-1"><h4 class="font-bold text-gray-800 text-sm group-hover:text-green-600">อัปเดตความคืบหน้า</h4><p class="text-[10px] text-gray-500">รายงาน % ผลการดำเนินงาน</p></div>
                        </button>
                        <button @click="submitBulkDeleteFromMenu" class="w-full text-left flex items-center p-3 hover:bg-red-50 transition group rounded-b-xl">
                            <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 mr-4"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></div>
                            <div class="flex-1"><h4 class="font-bold text-gray-800 text-sm group-hover:text-red-600">ลบรายการ</h4><p class="text-[10px] text-gray-500">ย้ายข้อมูลลงถังขยะ</p></div>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showBulkEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showBulkEditModal = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl relative z-10 animate-fade-in flex flex-col max-h-[90vh]">
                    <div class="bg-yellow-500 px-6 py-4 flex justify-between items-center border-b-4 border-yellow-600">
                        <h3 class="text-lg font-bold text-white">⚡ แก้ไขข้อมูลแบบกลุ่ม ({{ selectedChildren.length }})</h3>
                        <button @click="showBulkEditModal = false" class="text-white hover:text-yellow-100 font-bold text-xl">&times;</button>
                    </div>
                    <div class="p-6 overflow-y-auto space-y-4 flex-1 custom-scrollbar">
                         <div class="bg-gray-50 p-3 rounded border border-gray-200 text-xs text-gray-500 mb-4">💡 กรอกเฉพาะช่องที่ต้องการแก้ไข (ช่องว่าง = ไม่เปลี่ยนค่าเดิม)</div>
                         <div><label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด (Description)</label><textarea v-model="bulkForm.description" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-yellow-500 focus:ring-yellow-500"></textarea></div>
                         <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">กอง</label><select v-model="bulkForm.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-yellow-500 focus:ring-yellow-500"><option value="">(ไม่เปลี่ยน)</option><option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label><select v-model="bulkForm.department_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-yellow-500 focus:ring-yellow-500" :disabled="!bulkForm.division_id"><option value="">(ไม่เปลี่ยน)</option><option v-for="d in bulkEditDepartments" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
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
                                    <option value="no_change">คงเดิม (ตามระบบคำนวณ)</option>
                                    <option value="cancelled">🔴 ระงับ / ยกเลิกรายการนี้</option>
                                    <option value="active">🟢 เปิดใช้งาน (Active)</option>
                                </select>
                            </div>
                         </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                        <button @click="showBulkEditModal = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 font-bold text-sm hover:bg-gray-100">ยกเลิก</button>
                        <button @click="submitBulkEdit" :disabled="bulkForm.processing" class="px-4 py-2 bg-yellow-500 text-white rounded-lg font-bold text-sm shadow hover:bg-yellow-600 disabled:opacity-50">บันทึกการแก้ไข</button>
                    </div>
                </div>
            </div>

            <div v-if="showBulkProgressModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showBulkProgressModal = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl relative z-10 animate-fade-in">
                    <div class="bg-green-500 px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-bold text-white">✅ อัปเดตความคืบหน้า (กลุ่ม)</h3><button @click="showBulkProgressModal = false" class="text-white hover:text-green-100 font-bold text-xl">&times;</button></div>
                    <div class="p-6 space-y-5">
                        <div><label class="block text-sm font-bold text-gray-700 mb-2">อัปเดต % ทั้งหมดเป็น:</label><div class="flex items-center gap-3"><input type="range" v-model="bulkProgressForm.progress" min="0" max="100" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-green-500"><input type="number" v-model="bulkProgressForm.progress" min="0" max="100" class="w-20 text-center rounded-lg border-gray-300 font-bold text-lg text-green-600"></div></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด (ระบุรวมกัน) <span class="text-red-500">*</span></label><textarea v-model="bulkProgressForm.comment" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" required placeholder="ระบุผลการดำเนินงาน..."></textarea></div>
                        <div class="pt-2 border-t border-gray-100 flex justify-end gap-3"><button @click="showBulkProgressModal = false" class="px-4 py-2 bg-gray-100 rounded-lg text-gray-700 font-bold text-sm hover:bg-gray-200">ยกเลิก</button><button @click="submitBulkProgress" :disabled="bulkProgressForm.processing || !bulkProgressForm.comment" class="px-4 py-2 bg-green-500 text-white rounded-lg font-bold text-sm shadow hover:bg-green-600 disabled:opacity-50">ส่งรายงาน</button></div>
                    </div>
                </div>
            </div>

            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeMainModalSafely"></div>
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl h-[90vh] flex flex-col relative z-10">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913] shrink-0">
                        <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                        <button @click="closeMainModalSafely" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submit" class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">งานภายใต้</label><div class="w-full rounded-lg border border-gray-200 bg-gray-100 p-2 text-sm text-gray-600">📂 {{ parentNameDisplay }}</div><input type="hidden" v-model="form.parent_id"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label><input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F]" :class="{'border-red-500': form.errors.name}"><div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด</label><textarea v-model="form.description" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] text-sm" :class="{'border-red-500': form.errors.description}" rows="3"></textarea></div>
                        <div class="grid grid-cols-2 gap-4 bg-purple-50 p-3 rounded-lg border border-purple-100">
                            <div class="col-span-2 text-xs font-bold text-[#4A148C] uppercase">สังกัดหน่วยงาน</div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">กอง</label><select v-model="form.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F]"><option value="">-- เลือกกอง --</option><option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label><select v-model="form.department_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F]" :disabled="!form.division_id"><option value="">-- ไม่ระบุ --</option><option v-for="d in modalDepartments" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                        </div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ผู้ดูแล (PM)</label><PmAutocomplete v-model="form.pm_name" @update:id="(id) => form.project_manager_id = id" placeholder="ค้นหา..." /></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">ประเภท</label><select v-model="form.type" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F]"><option v-for="t in workItemTypes" :key="t.id" :value="t.key">{{ t.name }}</option></select></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label><input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F]"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">น้ำหนัก</label><input v-model="form.weight" type="number" step="0.01" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F]"></div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ</label>
                                <div class="flex items-center gap-2 p-2 bg-white border border-gray-200 rounded-lg">
                                    <div class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-[10px] font-bold border border-purple-200 uppercase">AUTO</div>
                                    <span class="text-xs font-bold px-2 py-1 rounded uppercase flex-1 text-center" :class="statusColor(form.status)">{{ form.status === 'in_active' ? 'IN ACTIVE' : form.status }}</span>
                                </div>
                                <div v-if="isEditing" class="mt-2"><label class="inline-flex items-center cursor-pointer"><input type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500" :checked="form.status === 'cancelled'" @change="form.status = $event.target.checked ? 'cancelled' : 'in_active'"><span class="ml-2 text-xs font-bold text-gray-500">ระงับ / ยกเลิกรายการนี้</span></label></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label><input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                            <div><label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label><input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                        </div>
                    </form>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0 bg-gray-50">
                        <button @click="closeMainModalSafely" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg font-bold text-gray-600 hover:bg-gray-100">ยกเลิก</button>
                        <button @click="submit" class="px-5 py-2.5 bg-[#7A2F8F] text-white rounded-lg font-bold shadow-md hover:bg-purple-800" :disabled="form.processing">บันทึก</button>
                    </div>
                </div>
            </div>

            <div v-if="showManualModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showManualModal = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl relative z-10 animate-fade-in">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-purple-400">
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">✏️ แก้ไขเป้าหมายเดือน {{ manualGroupLabel }}</h3>
                        <button @click="showManualModal = false" class="text-white font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submitManual" class="p-6 space-y-4 bg-gray-50/50">
                        <div class="bg-blue-50 text-blue-800 p-3 rounded-lg text-xs border border-blue-100 mb-4">💡 ข้อมูลที่แก้ตรงนี้ จะถูกบันทึกทับข้อมูลอัตโนมัติของเดือน <b>{{ manualGroupLabel }}</b> (สถานะอิงตามงานย่อย)</div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ผลผลิต / เป้าหมายที่ต้องการแสดง <span class="text-red-500">*</span></label><textarea v-model="manualForm.title" rows="4" required class="w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F]" placeholder="พิมพ์เป้าหมาย..."></textarea></div>
                        <div class="flex justify-between items-center pt-4 border-t border-gray-200 mt-6">
                            <button v-if="manualForm.id" type="button" @click="deleteManual" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 hover:bg-red-500 hover:text-white rounded-lg text-xs font-bold transition">🗑️ ลบกลับไปใช้ออโต้</button>
                            <div v-else></div><div class="flex gap-2"><button type="button" @click="showManualModal = false" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-bold hover:bg-gray-100">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-[#4A148C] hover:bg-purple-800 text-white rounded-lg text-sm font-bold shadow-md transition" :disabled="manualForm.processing">บันทึก</button></div>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="showUpdateProgressModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeProgressModalSafely"></div>
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
                                <input type="number" v-model="updateProgressForm.progress" min="0" max="100" class="w-20 text-center rounded-lg border-gray-300 font-bold text-lg text-[#7A2F8F]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียดการทำงาน <span class="text-red-500">*</span></label>
                            <textarea v-model="updateProgressForm.comment" rows="4" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': updateProgressForm.errors.comment}" placeholder="ระบุสิ่งที่ทำเสร็จ หรือสาเหตุที่ล่าช้า..."></textarea>
                            <div v-if="updateProgressForm.errors.comment" class="text-red-500 text-xs mt-1 font-bold">{{ updateProgressForm.errors.comment }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">แนบไฟล์/รูปภาพ (ถ้ามี)</label>
                            <input type="file" multiple @change="updateProgressForm.attachments = $event.target.files" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-[#7A2F8F] hover:file:bg-purple-100 transition">
                            <span class="text-[10px] text-gray-400 ml-2 mt-1 block">*รองรับเฉพาะ Word, Excel, PowerPoint, PDF, PNG, JPG</span>
                        </div>
                        <div class="pt-2 border-t border-gray-100 flex justify-end gap-3">
                            <button @click="closeProgressModalSafely" class="px-4 py-2 bg-gray-100 rounded-lg text-gray-700 font-bold text-sm hover:bg-gray-200">ยกเลิก</button>
                            <button @click="submitProgressUpdate" :disabled="updateProgressForm.processing || !updateProgressForm.comment" class="px-4 py-2 bg-[#7A2F8F] text-white rounded-lg font-bold text-sm shadow disabled:opacity-50 hover:bg-purple-800">ส่งรายงาน</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showIssueModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                 <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeIssueModalSafely"></div>

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
                                    <option value="issue">Issue</option><option value="risk">Risk</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700">ความรุนแรง</label>
                                <select v-model="issueForm.severity" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.severity}">
                                    <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700">รายละเอียด</label>
                            <textarea v-model="issueForm.description" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.description}"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700">ทางแก้</label>
                            <textarea v-model="issueForm.solution" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.solution}"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700">สถานะ</label>
                                <select v-model="issueForm.status" class="w-full rounded-lg border-gray-300" :class="{'border-red-500': issueForm.errors.status}">
                                    <option value="open">Open</option><option value="in_progress">In Progress</option><option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="col-span-2 grid grid-cols-2 gap-4">
                                <div><label class="block text-sm font-bold text-gray-700">เริ่ม</label><input v-model="issueForm.start_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                                <div><label class="block text-sm font-bold text-gray-700">สิ้นสุด</label><input v-model="issueForm.end_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2">
                            <button type="button" @click="closeIssueModalSafely" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg">ยกเลิก</button>
                            <button type="submit" class="px-5 py-2.5 bg-red-500 text-white rounded-lg font-bold" :disabled="issueForm.processing">บันทึก</button>
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

            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/30 backdrop-blur-sm animate-fade-in"><div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center transform scale-100 transition-transform"><div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4"><svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div><h3 class="text-xl font-bold text-gray-800">บันทึกสำเร็จ!</h3><p class="text-gray-500 mt-2">ข้อมูลของคุณถูกอัปเดตเรียบร้อยแล้ว</p></div></div>
        </Teleport>
    </PeaSidebarLayout>
</template>
