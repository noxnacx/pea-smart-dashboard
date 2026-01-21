<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import GanttChartView from '@/Components/GanttChartView.vue';

// รับ issues ติดมากับ item (จาก Controller)
const props = defineProps({ item: Object, chartData: Object, historyLogs: Array });
const activeTab = ref('overview');

// --- Check Role ---
const page = usePage();
const userRole = computed(() => page.props.auth.user.role);
const canEdit = computed(() => userRole.value === 'admin' || userRole.value === 'pm');

// --- Helpers ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });
};
const formatDateForInput = (dateString) => dateString ? dateString.split('T')[0] : '';
const getDuration = (start, end) => {
    if (!start || !end) return '-';
    const diffTime = Math.abs(new Date(end) - new Date(start));
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    return `${diffDays} วัน`;
};
const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// --- Issue / Risk Helpers ---
const getSeverityColor = (s) => ({
    critical: 'bg-red-100 text-red-700 border-red-200',
    high: 'bg-orange-100 text-orange-700 border-orange-200',
    medium: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    low: 'bg-green-100 text-green-700 border-green-200'
}[s] || 'bg-gray-100');

const getStatusBadge = (s) => ({
    open: '🔴 รอแก้ไข',
    in_progress: '🟡 กำลังดำเนินการ',
    resolved: '🟢 แก้ไขแล้ว'
}[s] || s);

// --- Breadcrumbs Logic ---
const breadcrumbs = computed(() => {
    let crumbs = [];
    let current = props.item;
    crumbs.push({ name: current.name, url: null });
    while (current.parent) {
        current = current.parent;
        crumbs.push({ name: current.name, url: route('work-items.show', current.id) });
    }
    crumbs.push({ name: 'Dashboard', url: route('dashboard') });
    return crumbs.reverse();
});

// --- Modal Logic (Project) ---
const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');
const form = useForm({ id: null, parent_id: null, name: '', type: 'project', budget: 0, progress: 0, status: 'pending', planned_start_date: '', planned_end_date: '' });

// --- Modal Logic (Issues) ---
const showIssueModal = ref(false);
const issueForm = useForm({
    id: null, title: '', type: 'issue', severity: 'medium',
    status: 'open', description: '', solution: '',
    start_date: '', end_date: ''
});

// --- Actions: Project ---
const openCreateModal = () => {
    isEditing.value = false; modalTitle.value = `สร้างรายการย่อย`;
    form.reset(); form.parent_id = props.item.id;
    form.type = { 'strategy': 'plan', 'plan': 'project', 'project': 'task', 'task': 'task' }[props.item.type] || 'task';
    showModal.value = true;
};
const openEditModal = (t) => {
    isEditing.value = true; modalTitle.value = `แก้ไข: ${t.name}`;
    form.id = t.id; form.name = t.name; form.type = t.type; form.budget = t.budget;
    form.progress = t.progress; form.status = t.status;
    form.planned_start_date = formatDateForInput(t.planned_start_date);
    form.planned_end_date = formatDateForInput(t.planned_end_date);
    showModal.value = true;
};
const submit = () => {
    if(isEditing.value) form.put(route('work-items.update', form.id), {onSuccess:()=>showModal.value=false});
    else form.post(route('work-items.store'), {onSuccess:()=>showModal.value=false});
};
const deleteItem = (id) => { if(confirm('ยืนยันลบ?')) useForm({}).delete(route('work-items.destroy', id)); };

// --- Actions: Issues ---
const openCreateIssue = () => { isEditing.value=false; issueForm.reset(); showIssueModal.value=true; };
const openEditIssue = (issue) => {
    isEditing.value=true;
    issueForm.id = issue.id;
    issueForm.title = issue.title;
    issueForm.type = issue.type;
    issueForm.severity = issue.severity;
    issueForm.status = issue.status;
    issueForm.description = issue.description;
    issueForm.solution = issue.solution;
    issueForm.start_date = formatDateForInput(issue.start_date);
    issueForm.end_date = formatDateForInput(issue.end_date);
    showIssueModal.value = true;
};
const submitIssue = () => {
    if(isEditing.value) issueForm.put(route('issues.update', issueForm.id), {onSuccess:()=>showIssueModal.value=false});
    else issueForm.post(route('issues.store', props.item.id), {onSuccess:()=>showIssueModal.value=false});
};
const deleteIssue = (id) => { if(confirm('ยืนยันลบปัญหานี้?')) useForm({}).delete(route('issues.destroy', id)); };

// --- File Logic ---
const fileForm = useForm({ file: null, category: 'general' });
const uploadFile = () => { if(!fileForm.file) return; fileForm.post(route('attachments.store', props.item.id), { onSuccess:()=>{ fileForm.reset(); } }); };
const deleteFile = (id) => { if(confirm('ลบไฟล์?')) useForm({}).delete(route('attachments.destroy', id)); };
const downloadFile = (id) => window.open(route('attachments.download', id), '_blank');
const isImage = (m) => m && m.startsWith('image/');
const getFileIcon = (m) => m?.includes('pdf')?'📄':m?.includes('word')||m?.includes('document')?'📝':m?.includes('excel')||m?.includes('sheet')?'📊':'📎';
const getCategoryBadge = (cat) => ({ 'contract': 'bg-blue-100 text-blue-700', 'invoice': 'bg-green-100 text-green-700', 'report': 'bg-purple-100 text-purple-700', 'tor': 'bg-orange-100 text-orange-700', 'general': 'bg-gray-100 text-gray-700' }[cat] || 'bg-gray-100');
const fileFilter = ref('all');
const filteredFiles = computed(() => fileFilter.value==='all' ? props.item.attachments||[] : (props.item.attachments||[]).filter(f => f.category === fileFilter.value));

// --- Comment Logic ---
const commentForm = useForm({ body: '' });
const submitComment = () => { commentForm.post(route('comments.store', props.item.id), { onSuccess: () => commentForm.reset(), preserveScroll: true }); };
</script>

<template>
    <Head :title="item.name" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1920px] mx-auto space-y-6">

            <nav class="text-sm text-gray-500 flex items-center flex-wrap gap-2">
                <template v-for="(crumb, index) in breadcrumbs" :key="index">
                    <Link v-if="crumb.url" :href="crumb.url" class="hover:text-[#7A2F8F] hover:underline transition-colors duration-200">{{ crumb.name }}</Link>
                    <span v-else class="text-[#7A2F8F] font-bold truncate max-w-[300px]" :title="crumb.name">{{ crumb.name }}</span>
                    <svg v-if="index < breadcrumbs.length - 1" class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </template>
            </nav>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 relative overflow-hidden">
                <div class="flex flex-col md:flex-row justify-between items-start z-10 relative gap-4">
                    <div>
                        <span class="bg-[#7A2F8F] text-white text-xs px-2 py-1 rounded uppercase">{{ item.type }}</span>
                        <h1 class="text-3xl font-bold text-[#4A148C] mt-2 leading-tight">{{ item.name }}</h1>
                        <p class="text-sm text-gray-500 mt-2 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}
                        </p>
                    </div>
                    <button v-if="canEdit" @click="openEditModal(item)" class="bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded text-sm font-bold text-gray-600 shrink-0">แก้ไขข้อมูล</button>
                </div>
                <div class="mt-4"><div class="flex justify-between text-xs font-bold mb-1"><span>Progress</span><span>{{ item.progress }}%</span></div><div class="w-full bg-gray-100 h-3 rounded-full"><div class="bg-[#7A2F8F] h-3 rounded-full" :style="`width:${item.progress}%`"></div></div></div>
            </div>

            <div class="border-b border-gray-200 flex space-x-8 overflow-x-auto">
                <button @click="activeTab='overview'" :class="activeTab==='overview'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">แผนงาน (Gantt View)</button>
                <button @click="activeTab='issues'" :class="activeTab==='issues'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap flex gap-2 items-center transition-colors">
                    <span>⚠️ ปัญหา/ความเสี่ยง</span>
                    <span v-if="item.issues?.length" class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{{ item.issues.length }}</span>
                </button>
                <button @click="activeTab='files'" :class="activeTab==='files'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">เอกสาร ({{ item.attachments?.length || 0 }})</button>
                <button @click="activeTab='logs'" :class="activeTab==='logs'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">ประวัติ / พูดคุย</button>
            </div>

            <div v-show="activeTab==='overview'" class="flex flex-col lg:flex-row gap-0 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm h-[600px] animate-fade-in">
                <div class="w-full lg:w-2/5 border-r border-gray-200 flex flex-col h-full bg-white overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center shrink-0 h-[50px]">
                        <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">TASK LIST</h3>
                        <button v-if="canEdit" @click="openCreateModal" class="text-[#7A2F8F] hover:bg-purple-50 p-1 rounded"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 sticky top-0 z-10 text-[10px] uppercase text-gray-500 font-semibold">
                                <tr>
                                    <th class="px-4 py-2 border-b">ชื่องาน</th>
                                    <th class="px-2 py-2 border-b w-20 text-center">เริ่ม</th>
                                    <th class="px-2 py-2 border-b w-14 text-center">เวลา</th>
                                    <th v-if="canEdit" class="px-1 py-2 border-b w-16 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                                <tr v-if="!item.children?.length"><td :colspan="canEdit?4:3" class="p-4 text-center text-gray-400">ไม่มีรายการ</td></tr>
                                <tr v-for="child in item.children" :key="child.id" class="hover:bg-purple-50 group transition-colors">
                                    <td class="px-4 py-3 font-medium border-r border-dashed border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shrink-0" :class="child.type==='project'?'bg-[#7A2F8F]':'bg-[#FDB913]'"></div>
                                            <Link :href="route('work-items.show', child.id)" class="truncate max-w-[180px] hover:text-[#7A2F8F] hover:underline cursor-pointer font-bold text-gray-700" :title="child.name">{{ child.name }}</Link>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-center text-gray-500">{{ formatDate(child.planned_start_date) }}</td>
                                    <td class="px-2 py-3 text-center bg-gray-50/50 font-mono text-gray-500">{{ getDuration(child.planned_start_date, child.planned_end_date) }}</td>
                                    <td v-if="canEdit" class="px-1 py-3 text-center">
                                        <div class="flex justify-center gap-1">
                                            <button @click="openEditModal(child)" class="p-1.5 text-gray-500 hover:text-blue-600 rounded bg-gray-100 hover:bg-blue-50 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                                            <button @click="deleteItem(child.id)" class="p-1.5 text-gray-500 hover:text-red-600 rounded bg-gray-100 hover:bg-red-50 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="w-full lg:w-3/5 h-full flex flex-col bg-white">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 shrink-0 h-[50px] flex items-center">
                        <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">TIMELINE ({{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }})</h3>
                    </div>
                    <div class="flex-1 p-2 overflow-hidden"><GanttChartView :items="item.children||[]" /></div>
                </div>
            </div>

            <div v-show="activeTab==='issues'" class="space-y-6 animate-fade-in">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-gray-700">รายการปัญหาและความเสี่ยง ({{ item.issues?.length || 0 }})</h3>
                    <button v-if="canEdit" @click="openCreateIssue" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow flex items-center gap-2 transition-colors">
                        <span>+ แจ้งปัญหาใหม่</span>
                    </button>
                </div>

                <div v-if="!item.issues?.length" class="p-12 text-center text-gray-400 border rounded-xl bg-white border-dashed">
                    <div class="text-4xl mb-2">🎉</div>
                    ยังไม่มีปัญหาหรือความเสี่ยงที่รายงาน
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="issue in item.issues" :key="issue.id" class="bg-white p-5 rounded-xl shadow-sm border border-l-4 transition hover:shadow-md relative group"
                         :class="issue.type==='issue'?'border-l-red-500':'border-l-orange-400'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border" :class="getSeverityColor(issue.severity)">
                                {{ issue.severity }} priority
                            </span>
                            <span class="text-xs font-medium">{{ getStatusBadge(issue.status) }}</span>
                        </div>

                        <h4 class="font-bold text-gray-800 text-lg mb-1">{{ issue.title }}</h4>
                        <p class="text-xs text-gray-500 mb-3 line-clamp-2 min-h-[2.5em]">{{ issue.description || 'ไม่มีรายละเอียดเพิ่มเติม' }}</p>

                        <div class="bg-gray-50 p-2 rounded text-xs text-gray-600 border border-gray-100 mb-3">
                            <span class="font-bold text-gray-700">💡 ทางแก้:</span> {{ issue.solution || 'ยังไม่ระบุ' }}
                        </div>

                        <div class="flex justify-between items-center text-[10px] text-gray-400 border-t border-gray-100 pt-2 mt-2">
                            <span>ผู้แจ้ง: {{ issue.user?.name }}</span>
                            <div v-if="issue.start_date || issue.end_date" class="flex items-center gap-1 text-xs">
                                <span class="text-gray-500">📅</span>
                                <span :class="issue.type === 'risk' ? 'text-orange-600 font-bold' : 'text-red-600 font-bold'">
                                    {{ issue.start_date ? formatDate(issue.start_date) : '...' }} - {{ issue.end_date ? formatDate(issue.end_date) : '...' }}
                                </span>
                            </div>
                        </div>

                        <div v-if="canEdit" class="absolute top-2 right-2 hidden group-hover:flex gap-1">
                            <button @click="openEditIssue(issue)" class="bg-white p-1.5 rounded shadow text-blue-500 hover:text-blue-700 border border-gray-200 transition-colors">✏️</button>
                            <button @click="deleteIssue(issue.id)" class="bg-white p-1.5 rounded shadow text-red-500 hover:text-red-700 border border-gray-200 transition-colors">🗑</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab==='files'" class="space-y-6 animate-fade-in">
                <div v-if="canEdit" class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl p-6 transition-all hover:border-[#7A2F8F] group">
                    <div class="flex flex-col md:flex-row gap-4 items-center justify-center">
                        <div class="flex-1 w-full md:w-auto">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">ประเภทเอกสาร</label>
                            <select v-model="fileForm.category" class="w-full rounded-lg border-gray-300 text-sm focus:ring-[#7A2F8F] focus:border-[#7A2F8F]">
                                <option value="general">ทั่วไป</option><option value="contract">สัญญา</option><option value="tor">TOR</option><option value="invoice">ใบแจ้งหนี้</option><option value="report">รายงาน</option>
                            </select>
                        </div>
                        <div class="flex-1 w-full md:w-auto">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">เลือกไฟล์</label>
                            <input type="file" @input="fileForm.file=$event.target.files[0]" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-[#7A2F8F] hover:file:bg-purple-100 cursor-pointer">
                        </div>
                        <button v-if="fileForm.file" @click="uploadFile" :disabled="fileForm.processing" class="bg-[#7A2F8F] text-white px-6 py-2 rounded-lg text-sm font-bold shadow-md hover:bg-purple-800 transition-colors mt-4 md:mt-0">อัปโหลดไฟล์</button>
                    </div>
                </div>
                <div class="flex justify-between items-center border-b pb-2 mb-4">
                    <h3 class="font-bold text-gray-700">รายการเอกสารแนบ ({{ filteredFiles.length }})</h3>
                    <div class="flex items-center gap-2"><span class="text-xs text-gray-500">กรองตามประเภท:</span><select v-model="fileFilter" class="text-xs rounded-lg border-gray-300 py-1 pl-2 pr-8"><option value="all">ทั้งหมด</option><option value="general">ทั่วไป</option><option value="contract">สัญญา</option><option value="tor">TOR</option><option value="invoice">ใบแจ้งหนี้</option><option value="report">รายงาน</option></select></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-if="filteredFiles.length === 0" class="col-span-full p-12 text-center text-gray-400 border rounded-xl bg-white border-dashed">ไม่พบเอกสารในหมวดหมู่นี้</div>
                    <div v-for="file in filteredFiles" :key="file.id" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex gap-4 hover:shadow-md transition relative group overflow-hidden">
                        <div class="w-16 h-16 shrink-0 rounded-lg bg-gray-100 flex items-center justify-center text-2xl overflow-hidden border border-gray-100">
                            <img v-if="isImage(file.file_type)" :src="`/storage/${file.file_path}`" class="w-full h-full object-cover cursor-pointer" @click="downloadFile(file.id)"><span v-else>{{ getFileIcon(file.file_type) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase mb-1 inline-block" :class="getCategoryBadge(file.category || 'general')">{{ file.category || 'GENERAL' }}</span>
                                <span class="text-[10px] text-gray-400">{{ formatFileSize(file.file_size || 0) }}</span>
                            </div>
                            <h4 class="text-sm font-bold text-gray-700 truncate cursor-pointer hover:text-[#7A2F8F]" :title="file.file_name" @click="downloadFile(file.id)">{{ file.file_name }}</h4>
                            <p class="text-xs text-gray-400 mt-0.5">โดย {{ file.uploader?.name }} • {{ formatDate(file.created_at) }}</p>
                            <div class="flex gap-3 mt-2">
                                <button @click="downloadFile(file.id)" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">⬇ ดาวน์โหลด</button>
                                <button v-if="canEdit" @click="deleteFile(file.id)" class="text-xs font-bold text-red-500 hover:text-red-700 flex items-center gap-1">🗑 ลบ</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab==='logs'" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-fade-in">
                <div class="mb-8 bg-purple-50 p-4 rounded-xl border border-purple-100 flex gap-4">
                    <div class="w-10 h-10 rounded-full bg-[#7A2F8F] text-white flex items-center justify-center font-bold text-lg shadow-sm shrink-0">{{ $page.props.auth.user.name.charAt(0) }}</div>
                    <div class="flex-1">
                        <textarea v-model="commentForm.body" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] text-sm min-h-[80px]" placeholder="เขียนความคิดเห็น..."></textarea>
                        <div class="flex justify-end mt-2"><button @click="submitComment" :disabled="commentForm.processing || !commentForm.body.trim()" class="bg-[#7A2F8F] text-white px-4 py-2 rounded-lg text-sm font-bold shadow hover:bg-purple-800 disabled:opacity-50 transition-colors">ส่งความคิดเห็น ➤</button></div>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-100"></div>
                    <h3 class="font-bold text-gray-700 mb-6 flex items-center gap-2 relative pl-12">⏱️ ไทม์ไลน์กิจกรรม</h3>
                    <div class="space-y-6">
                        <div v-if="historyLogs.length === 0" class="text-gray-400 text-sm italic text-center py-4 pl-12">ยังไม่มีความเคลื่อนไหว</div>
                        <div v-for="item in historyLogs" :key="item.id + item.timeline_type" class="flex gap-4 relative group">

                            <template v-if="item.timeline_type === 'comment'">
                                <div class="w-12 h-12 rounded-full bg-white border-4 border-gray-50 overflow-hidden shrink-0 z-10 shadow-sm flex items-center justify-center">
                                     <div class="w-full h-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white font-bold">{{ item.user?.name.charAt(0) }}</div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-baseline mb-1"><span class="font-bold text-gray-800 text-sm">{{ item.user?.name }}</span><span class="text-xs text-gray-400">{{ new Date(item.created_at).toLocaleString('th-TH') }}</span></div>
                                    <div class="bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-none shadow-sm text-sm text-gray-700 relative">{{ item.body }}</div>
                                </div>
                            </template>

                            <template v-else>
                                <div class="w-12 h-12 flex justify-center items-start shrink-0 z-10">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs shadow-sm border-2 border-white"
                                        :class="{
                                            'bg-blue-100 text-blue-600': item.model_type === 'Attachment',
                                            'bg-purple-100 text-purple-600': item.model_type === 'WorkItem',
                                            'bg-red-100 text-red-600': item.model_type === 'Issue'
                                        }">
                                        {{ item.model_type === 'Attachment' ? '📎' : (item.model_type === 'Issue' ? '⚠️' : '📝') }}
                                    </div>
                                </div>
                                <div class="flex-1 pb-2 pt-1">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm text-gray-600">
                                            <span class="font-bold text-gray-800">{{ item.user ? item.user.name : 'System' }}</span>
                                            {{ item.action === 'CREATE' ? 'สร้าง' : (item.action === 'UPDATE' ? 'แก้ไข' : 'ลบ') }}
                                            {{ item.model_type === 'Issue' ? 'ปัญหา/ความเสี่ยง' : (item.model_type === 'Attachment' ? 'เอกสาร' : 'ข้อมูลโครงการ') }}
                                        </p>
                                        <span class="text-xs text-gray-400 whitespace-nowrap">{{ new Date(item.created_at).toLocaleString('th-TH') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 bg-gray-50 p-2 rounded border border-gray-100 inline-block w-full">

                                        <div v-if="item.model_type==='Issue'">
                                            <div class="font-bold text-gray-700 mb-1">หัวข้อ: {{ item.changes?.title }}</div>
                                            <div v-if="item.action==='UPDATE'" class="space-y-1">
                                                <div v-for="(val, key) in item.changes?.after" :key="key">
                                                    <span class="text-gray-400">{{ key }}:</span>
                                                    <span class="line-through text-red-300 mr-1">{{ item.changes?.before?.[key] }}</span>
                                                    ➜ <span class="text-green-600 font-bold">{{ val }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <span v-else-if="item.model_type==='Attachment'">
                                            {{ item.action==='CREATE' ? 'อัปโหลด:' : 'ลบไฟล์:' }} <b>{{ item.changes?.file_name || item.changes?.after?.file_name }}</b>
                                        </span>

                                        <div v-else-if="item.model_type==='WorkItem' && item.action==='UPDATE'">
                                            <span v-for="(val, k) in item.changes?.after" :key="k" class="mr-2">
                                                {{ k }}: <span class="text-red-400 line-through">{{ item.changes?.before?.[k] }}</span> ➜ <span class="text-green-600 font-bold">{{ val }}</span>
                                            </span>
                                        </div>

                                        <span v-else>{{ item.model_type }} #{{ item.model_id }} ({{ item.action }})</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]"><h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3><button @click="showModal=false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button></div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label><input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" required></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ประเภท</label><select v-model="form.type" class="w-full rounded-lg border-gray-300"><option value="strategy">Strategy</option><option value="plan">Plan</option><option value="project">Project</option><option value="task">Task</option></select></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label><input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">Progress (%)</label><input v-model="form.progress" type="number" min="0" max="100" class="w-full rounded-lg border-gray-300"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label><input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label><input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300"></div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2"><button type="button" @click="showModal=false" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] text-white rounded-lg font-bold shadow-md">บันทึก</button></div>
                </form>
            </div>
        </div>

        <div v-if="showIssueModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="bg-red-500 px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-bold text-white">⚠️ แจ้งปัญหา/ความเสี่ยง</h3><button @click="showIssueModal=false" class="text-white hover:text-red-100 font-bold text-xl">&times;</button></div>
                <form @submit.prevent="submitIssue" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">หัวข้อปัญหา <span class="text-red-500">*</span></label>
                        <input v-model="issueForm.title" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring-red-500" required placeholder="ระบุหัวข้อปัญหา...">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ประเภท</label>
                            <select v-model="issueForm.type" class="w-full rounded-lg border-gray-300 focus:ring-red-500">
                                <option value="issue">🔥 Issue (ปัญหาที่เกิดแล้ว)</option>
                                <option value="risk">☁️ Risk (ความเสี่ยง)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ความรุนแรง</label>
                            <select v-model="issueForm.severity" class="w-full rounded-lg border-gray-300 focus:ring-red-500">
                                <option value="low">🟢 Low (ต่ำ)</option>
                                <option value="medium">🟡 Medium (กลาง)</option>
                                <option value="high">🟠 High (สูง)</option>
                                <option value="critical">🔴 Critical (วิกฤต)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด</label>
                        <textarea v-model="issueForm.description" class="w-full rounded-lg border-gray-300 focus:ring-red-500" rows="3" placeholder="อธิบายรายละเอียด..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">แนวทางแก้ไข (Solution)</label>
                        <textarea v-model="issueForm.solution" class="w-full rounded-lg border-gray-300 focus:ring-red-500" rows="2" placeholder="จะจัดการอย่างไร..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">สถานะปัจจุบัน</label>
                            <select v-model="issueForm.status" class="w-full rounded-lg border-gray-300 focus:ring-red-500">
                                <option value="open">🔴 Open (รอแก้ไข)</option>
                                <option value="in_progress">🟡 In Progress (กำลังแก้)</option>
                                <option value="resolved">🟢 Resolved (เสร็จสิ้น)</option>
                            </select>
                        </div>

                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">วันเริ่ม (ระวังภัย)</label>
                                <input v-model="issueForm.start_date" type="date" class="w-full rounded-lg border-gray-300 focus:ring-red-500">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">วันสิ้นสุด / กำหนดเสร็จ</label>
                                <input v-model="issueForm.end_date" type="date" class="w-full rounded-lg border-gray-300 focus:ring-red-500">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2">
                        <button type="button" @click="showIssueModal=false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-bold transition-colors">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold shadow-md transition-colors flex items-center gap-2">
                            <span v-if="issueForm.processing">⏳</span> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </PeaSidebarLayout>
</template>
