<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import GanttChart from '@/Components/GanttChart.vue';
import SCurveChart from '@/Components/SCurveChart.vue';

// --- Props ---
const props = defineProps({
    item: Object,
    chartData: Object,
    historyLogs: Object
});

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

const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    return dateString.replace(' ', 'T').split('T')[0];
};

const getDuration = (start, end) => {
    if (!start || !end) return '-';
    const diffTime = Math.abs(new Date(end) - new Date(start));
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 + ' วัน';
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// --- S-Curve Logic ---
const timeRange = ref('all');
const rangeOptions = [
    { label: '1 เดือน', value: 1 },
    { label: '3 เดือน', value: 3 },
    { label: '6 เดือน', value: 6 },
    { label: '1 ปี', value: 12 },
    { label: '2 ปี', value: 24 },
    { label: '3 ปี', value: 36 },
    { label: 'ทั้งหมด', value: 'all' },
];

const filteredChartData = computed(() => {
    const { categories, planned, actual } = props.chartData;
    if (!categories || categories.length === 0) return { categories: [], planned: [], actual: [] };
    if (timeRange.value === 'all') return { categories, planned, actual };

    const endIndex = actual.length > 0 ? actual.length : 1;
    const startIndex = Math.max(0, endIndex - timeRange.value);
    const targetEndIndex = startIndex + timeRange.value;
    const finalEndIndex = Math.min(categories.length, targetEndIndex);

    return {
        categories: categories.slice(startIndex, finalEndIndex),
        planned: planned.slice(startIndex, finalEndIndex),
        actual: actual.slice(startIndex, endIndex)
    };
});

const getGrowth = () => {
    const data = filteredChartData.value.actual;
    if (!data || data.length < 2) return 0;
    return (data[data.length - 1] - data[0]).toFixed(2);
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

// --- Modals Logic ---
const showModal = ref(false), isEditing = ref(false), modalTitle = ref(''), showIssueModal = ref(false), showViewIssueModal = ref(false), selectedIssue = ref(null);
const form = useForm({ id: null, parent_id: null, name: '', type: 'task', budget: 0, progress: 0, status: 'pending', planned_start_date: '', planned_end_date: '' });
const issueForm = useForm({ id: null, title: '', type: 'issue', severity: 'medium', status: 'open', description: '', solution: '', start_date: '', end_date: '' });
const fileForm = useForm({ file: null, category: 'general' });
const commentForm = useForm({ body: '' });
const fileFilter = ref('all');
const filteredFiles = computed(() => fileFilter.value==='all' ? props.item.attachments||[] : (props.item.attachments||[]).filter(f => f.category === fileFilter.value));

// --- Actions ---
const openCreateModal = () => { isEditing.value=false; modalTitle.value=`สร้างรายการย่อย`; form.reset(); form.parent_id=props.item.id; showModal.value=true; };
const openEditModal = (t) => {
    isEditing.value=true;
    modalTitle.value=`แก้ไข: ${t.name}`;
    form.id=t.id; form.name=t.name; form.type=t.type; form.budget=t.budget; form.progress=t.progress; form.status=t.status;
    form.planned_start_date=formatDateForInput(t.planned_start_date);
    form.planned_end_date=formatDateForInput(t.planned_end_date);
    showModal.value=true;
};
const submit = () => { isEditing.value ? form.put(route('work-items.update', form.id), {onSuccess:()=>showModal.value=false}) : form.post(route('work-items.store'), {onSuccess:()=>showModal.value=false}); };
const deleteItem = (id) => { if(confirm('ยืนยันลบ?')) useForm({}).delete(route('work-items.destroy', id)); };

const openCreateIssue = () => { isEditing.value=false; issueForm.reset(); showIssueModal.value=true; };
const openEditIssue = (issue) => { showViewIssueModal.value=false; isEditing.value=true; issueForm.id=issue.id; issueForm.title=issue.title; issueForm.type=issue.type; issueForm.severity=issue.severity; issueForm.status=issue.status; issueForm.description=issue.description; issueForm.solution=issue.solution; issueForm.start_date=formatDateForInput(issue.start_date); issueForm.end_date=formatDateForInput(issue.end_date); showIssueModal.value=true; };
const openViewIssue = (issue) => { selectedIssue.value=issue; showViewIssueModal.value=true; };
const submitIssue = () => { isEditing.value ? issueForm.put(route('issues.update', issueForm.id), {onSuccess:()=>showIssueModal.value=false}) : issueForm.post(route('issues.store', props.item.id), {onSuccess:()=>showIssueModal.value=false}); };
const deleteIssue = (id) => { if(confirm('ยืนยันลบ?')) { showViewIssueModal.value=false; useForm({}).delete(route('issues.destroy', id)); } };

const uploadFile = () => { if(fileForm.file) fileForm.post(route('attachments.store', props.item.id), {onSuccess:()=>fileForm.reset()}); };
const deleteFile = (id) => { if(confirm('ลบไฟล์?')) useForm({}).delete(route('attachments.destroy', id)); };
const downloadFile = (id) => window.open(route('attachments.download', id), '_blank');
const submitComment = () => { commentForm.post(route('comments.store', props.item.id), {onSuccess:()=>commentForm.reset(), preserveScroll:true}); };
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
                    <div>
                        <span class="bg-[#7A2F8F] text-white text-xs px-2 py-1 rounded uppercase">{{ item.type }}</span>
                        <h1 class="text-3xl font-bold text-[#4A148C] mt-2">{{ item.name }}</h1>
                        <p class="text-sm text-gray-500 mt-2">⏱ {{ formatDate(item.planned_start_date) }} - {{ formatDate(item.planned_end_date) }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a :href="route('work-items.export-pdf', item.id)" target="_blank" class="bg-white border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded text-sm font-bold text-gray-700 flex items-center gap-2 transition">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export PDF
                        </a>
                        <button v-if="canEdit" @click="openEditModal(item)" class="bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded text-sm font-bold text-gray-600">แก้ไขข้อมูล</button>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs font-bold mb-1"><span>Progress</span><span>{{ item.progress }}%</span></div>
                    <div class="w-full bg-gray-100 h-3 rounded-full"><div class="bg-[#7A2F8F] h-3 rounded-full" :style="`width:${item.progress}%`"></div></div>
                </div>
            </div>

            <div class="border-b border-gray-200 flex space-x-8 overflow-x-auto">
                <button @click="activeTab='overview'" :class="activeTab==='overview'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">แผนงาน (Gantt)</button>
                <button @click="activeTab='scurve'" :class="activeTab==='scurve'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">📈 กราฟความก้าวหน้า</button>
                <button @click="activeTab='issues'" :class="activeTab==='issues'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap flex gap-2 items-center transition-colors"><span>⚠️ ปัญหา/ความเสี่ยง</span><span v-if="item.issues?.length" class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">{{ item.issues.length }}</span></button>
                <button @click="activeTab='files'" :class="activeTab==='files'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">เอกสาร ({{ item.attachments?.length || 0 }})</button>
                <button @click="activeTab='logs'" :class="activeTab==='logs'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap transition-colors">ประวัติ</button>
            </div>

            <div v-show="activeTab==='overview'" class="flex flex-col lg:flex-row gap-4 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm h-[700px] animate-fade-in">

                <div class="w-full lg:w-2/5 border-r border-gray-200 flex flex-col h-full bg-white overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b flex justify-between items-center h-[50px]">
                        <h3 class="text-xs font-bold text-gray-600">TASK LIST</h3>
                        <button v-if="canEdit" @click="openCreateModal" class="text-[#7A2F8F] hover:bg-purple-50 p-1 rounded font-bold text-lg" title="เพิ่มงานย่อย">+</button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] uppercase text-gray-500 font-bold sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-4 py-2">ชื่องาน</th>
                                    <th class="px-2 py-2 text-center w-28">ความคืบหน้า</th> <th class="px-2 py-2 text-center">เริ่ม</th>
                                    <th class="px-2 py-2 text-center">สิ้นสุด</th> <th v-if="canEdit" class="px-2 py-2 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                                <tr v-for="child in item.children" :key="child.id" class="hover:bg-purple-50 group transition">
                                    <td class="px-4 py-3 font-medium border-r border-dashed">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :class="child.type==='project'?'bg-[#7A2F8F]':'bg-[#FDB913]'"></div>
                                            <Link :href="route('work-items.show', child.id)" class="truncate max-w-[150px] hover:text-[#7A2F8F] font-bold text-gray-700">{{ child.name }}</Link>
                                        </div>
                                    </td>

                                    <td class="px-2 py-3 text-center border-r border-dashed">
                                        <div class="flex items-center gap-2 justify-center">
                                            <div class="w-12 bg-gray-200 rounded-full h-2">
                                                <div class="bg-[#7A2F8F] h-2 rounded-full transition-all duration-500" :style="{ width: (child.progress || 0) + '%' }"></div>
                                            </div>
                                            <span class="text-[10px] font-bold text-gray-600 w-6 text-right">{{ child.progress || 0 }}%</span>
                                        </div>
                                    </td>

                                    <td class="px-2 py-3 text-center text-gray-500 whitespace-nowrap">{{ formatDate(child.planned_start_date) }}</td>
                                    <td class="px-2 py-3 text-center text-gray-500 whitespace-nowrap">{{ formatDate(child.planned_end_date) }}</td> <td v-if="canEdit" class="px-1 py-3 text-center w-16">
                                        <div class="flex justify-center gap-2">
                                            <button @click="openEditModal(child)" class="text-blue-500 hover:scale-110" title="แก้ไข">✏️</button>
                                            <button @click="deleteItem(child.id)" class="text-red-500 hover:scale-110" title="ลบ">🗑</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!item.children || item.children.length === 0">
                                    <td colspan="5" class="text-center py-8 text-gray-400">ยังไม่มีงานย่อย</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="w-full lg:w-3/5 h-full">
                    <GanttChart :task-id="item.id" />
                </div>
            </div>

            <div v-show="activeTab==='scurve'" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-fade-in">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <div><h3 class="font-bold text-gray-700 text-lg">กราฟความก้าวหน้าโครงการ (S-Curve)</h3><p class="text-sm text-gray-500">แสดงเปรียบเทียบแผนงาน (Plan) vs ผลงานจริง (Actual)</p></div>
                    <div class="flex bg-gray-100 p-1 rounded-lg flex-wrap justify-center"><button v-for="range in rangeOptions" :key="range.value" @click="timeRange = range.value" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all whitespace-nowrap" :class="timeRange === range.value ? 'bg-white text-[#7A2F8F] shadow-sm' : 'text-gray-500 hover:text-gray-700'">{{ range.label }}</button></div>
                </div>
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 relative min-h-[400px]">
                    <div v-if="filteredChartData.categories.length > 0">
                        <SCurveChart :categories="filteredChartData.categories" :planned="filteredChartData.planned" :actual="filteredChartData.actual" />
                    </div>
                    <div v-else class="flex flex-col items-center justify-center h-[350px] text-gray-400"><span>ยังไม่มีข้อมูลแผนงานในช่วงนี้</span></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                    <div class="bg-purple-50 p-4 rounded-xl border border-purple-100"><div class="text-xs text-gray-500 font-bold uppercase">ความก้าวหน้าล่าสุด</div><div class="text-2xl font-black text-[#7A2F8F]">{{ filteredChartData.actual.length > 0 ? filteredChartData.actual[filteredChartData.actual.length - 1] : 0 }}%</div></div>
                    <div class="bg-green-50 p-4 rounded-xl border border-green-100"><div class="text-xs text-gray-500 font-bold uppercase">เติบโตในช่วงนี้</div><div class="text-2xl font-black text-green-600">+{{ getGrowth() }}%</div></div>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200"><div class="text-xs text-gray-500 font-bold uppercase">ระยะเวลา (เดือน)</div><div class="text-2xl font-black text-gray-600">{{ filteredChartData.categories.length }} เดือน</div></div>
                </div>
            </div>

            <div v-show="activeTab==='issues'" class="space-y-6 animate-fade-in">
                <div class="flex justify-between items-center"><h3 class="font-bold text-gray-700">รายการปัญหาและความเสี่ยง ({{ item.issues?.length || 0 }})</h3><button v-if="canEdit" @click="openCreateIssue" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow flex items-center gap-2"><span>+ แจ้งปัญหาใหม่</span></button></div>
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
                <div v-if="canEdit" class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl p-6 flex flex-col md:flex-row gap-4 items-center justify-center">
                    <select v-model="fileForm.category" class="rounded-lg border-gray-300 text-sm"><option value="general">ทั่วไป</option><option value="contract">สัญญา</option><option value="tor">TOR</option><option value="invoice">ใบแจ้งหนี้</option><option value="report">รายงาน</option></select>
                    <input type="file" @input="fileForm.file=$event.target.files[0]" class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-[#7A2F8F] hover:file:bg-purple-100 cursor-pointer">
                    <button v-if="fileForm.file" @click="uploadFile" class="bg-[#7A2F8F] text-white px-6 py-2 rounded-lg text-sm font-bold shadow-md">อัปโหลด</button>
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
                </div>
            </div>

            <div v-show="activeTab==='logs'" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-fade-in">
                <div class="mb-8 bg-purple-50 p-4 rounded-xl border border-purple-100 flex gap-4">
                    <div class="w-10 h-10 rounded-full bg-[#7A2F8F] text-white flex items-center justify-center font-bold">{{ $page.props.auth.user.name.charAt(0) }}</div>
                    <div class="flex-1"><textarea v-model="commentForm.body" class="w-full rounded-lg border-gray-300 text-sm" placeholder="เขียนความคิดเห็น..."></textarea><div class="flex justify-end mt-2"><button @click="submitComment" class="bg-[#7A2F8F] text-white px-4 py-2 rounded-lg text-sm font-bold">ส่ง</button></div></div>
                </div>
                <div class="space-y-6">
                    <div v-for="item in historyLogs.data" :key="item.id + item.timeline_type" class="flex gap-4 relative group">
                        <template v-if="item.timeline_type === 'comment'">
                            <div class="w-12 h-12 rounded-full bg-white border-4 border-gray-50 overflow-hidden shrink-0 z-10 shadow-sm flex items-center justify-center"><div class="w-full h-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white font-bold">{{ item.user?.name.charAt(0) }}</div></div>
                            <div class="flex-1">
                                <div class="flex justify-between items-baseline mb-1">
                                    <div><span class="font-bold text-gray-800 text-sm">{{ item.user?.name }}</span> <span v-if="item.target_name" class="text-xs text-gray-500 ml-2">(ที่: {{ item.target_name }})</span></div>
                                    <span class="text-xs text-gray-400">{{ new Date(item.created_at).toLocaleString('th-TH') }}</span>
                                </div>
                                <div class="bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-none shadow-sm text-sm text-gray-700 relative">{{ item.body }}</div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="w-12 h-12 flex justify-center items-start shrink-0 z-10"><div class="w-8 h-8 rounded-full flex items-center justify-center text-xs shadow-sm border-2 border-white" :class="{'bg-blue-100 text-blue-600': item.model_type === 'Attachment','bg-purple-100 text-purple-600': item.model_type === 'WorkItem','bg-red-100 text-red-600': item.model_type === 'Issue'}">{{ item.model_type === 'Attachment' ? '📎' : (item.model_type === 'Issue' ? '⚠️' : '📝') }}</div></div>
                            <div class="flex-1 pb-2 pt-1">
                                <div class="flex justify-between items-start">
                                    <p class="text-sm text-gray-600"><span class="font-bold text-gray-800">{{ item.user ? item.user.name : 'System' }}</span> {{ item.action }} {{ item.model_type }}</p>
                                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ new Date(item.created_at).toLocaleString('th-TH') }}</span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500 bg-gray-50 p-2 rounded border border-gray-100 inline-block w-full">
                                    <div v-if="item.target_name" class="font-bold text-[#7A2F8F] mb-1 border-b border-gray-200 pb-1">รายการ: {{ item.target_name }}</div>
                                    <div v-if="item.model_type==='Issue'"><div class="font-bold text-gray-700 mb-1">หัวข้อ: {{ item.changes?.title }}</div><div v-if="item.action==='UPDATE'" class="space-y-1"><div v-for="(val, key) in item.changes?.after" :key="key"><span class="text-gray-400">{{ key }}:</span> <span class="line-through text-red-300 mr-1">{{ item.changes?.before?.[key] }}</span> ➜ <span class="text-green-600 font-bold">{{ val }}</span></div></div></div>
                                    <span v-else-if="item.model_type==='Attachment'">{{ item.action==='CREATE' ? 'อัปโหลด:' : 'ลบไฟล์:' }} <b>{{ item.changes?.file_name || item.changes?.after?.file_name }}</b></span>
                                    <div v-else-if="item.model_type==='WorkItem' && item.action==='UPDATE'"><span v-for="(val, k) in item.changes?.after" :key="k" class="mr-2 block">{{ k }}: <span class="text-red-400 line-through">{{ item.changes?.before?.[k] }}</span> ➜ <span class="text-green-600 font-bold">{{ val }}</span></span></div>
                                    <span v-else>{{ item.model_type }} #{{ item.model_id }} ({{ item.action }})</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div v-if="historyLogs.links && historyLogs.links.length > 3" class="mt-6 flex justify-center gap-1">
                    <Link v-for="(link, k) in historyLogs.links" :key="k" :href="link.url || '#'" v-html="link.label" class="px-3 py-1 rounded text-sm border" :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 hover:bg-gray-50' + (!link.url ? ' opacity-50 cursor-not-allowed' : '')" />
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]"><h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3><button @click="showModal=false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button></div>
                    <form @submit.prevent="submit" class="p-6 space-y-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label><input v-model="form.name" class="w-full rounded-lg border-gray-300" required></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ประเภท</label><select v-model="form.type" class="w-full rounded-lg border-gray-300"><option value="strategy">Strategy</option><option value="plan">Plan</option><option value="project">Project</option><option value="task">Task</option></select></div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label><input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300"></div><div><label class="block text-sm font-bold text-gray-700 mb-1">Progress</label><input v-model="form.progress" type="number" class="w-full rounded-lg border-gray-300"></div></div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label><input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300"></div><div><label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label><input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300"></div></div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2"><button type="button" @click="showModal=false" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] text-white rounded-lg font-bold">บันทึก</button></div>
                    </form>
                </div>
            </div>

            <div v-if="showIssueModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                    <div class="bg-red-500 px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-bold text-white">⚠️ ปัญหา</h3><button @click="showIssueModal=false" class="text-white font-bold text-xl">&times;</button></div>
                    <form @submit.prevent="submitIssue" class="p-6 space-y-4">
                        <div><label class="block text-sm font-bold text-gray-700">หัวข้อ</label><input v-model="issueForm.title" class="w-full rounded-lg border-gray-300" required></div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-bold text-gray-700">ประเภท</label><select v-model="issueForm.type" class="w-full rounded-lg border-gray-300"><option value="issue">Issue</option><option value="risk">Risk</option></select></div><div><label class="block text-sm font-bold text-gray-700">ความรุนแรง</label><select v-model="issueForm.severity" class="w-full rounded-lg border-gray-300"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div></div>
                        <div><label class="block text-sm font-bold text-gray-700">รายละเอียด</label><textarea v-model="issueForm.description" class="w-full rounded-lg border-gray-300"></textarea></div>
                        <div><label class="block text-sm font-bold text-gray-700">ทางแก้</label><textarea v-model="issueForm.solution" class="w-full rounded-lg border-gray-300"></textarea></div>
                        <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-bold text-gray-700">สถานะ</label><select v-model="issueForm.status" class="w-full rounded-lg border-gray-300"><option value="open">Open</option><option value="in_progress">In Progress</option><option value="resolved">Resolved</option></select></div><div class="col-span-2 grid grid-cols-2 gap-4"><div><label class="block text-sm font-bold text-gray-700">เริ่ม</label><input v-model="issueForm.start_date" type="date" class="w-full rounded-lg border-gray-300"></div><div><label class="block text-sm font-bold text-gray-700">สิ้นสุด</label><input v-model="issueForm.end_date" type="date" class="w-full rounded-lg border-gray-300"></div></div></div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2"><button type="button" @click="showIssueModal=false" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg">ยกเลิก</button><button type="submit" class="px-5 py-2.5 bg-red-500 text-white rounded-lg font-bold">บันทึก</button></div>
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
        </Teleport>
    </PeaSidebarLayout>
</template>
