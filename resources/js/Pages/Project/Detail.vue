<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import GanttChartView from '@/Components/GanttChartView.vue';

const props = defineProps({ item: Object, chartData: Object });
const activeTab = ref('overview');

// --- Helper Functions ---
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });
};

const formatDateForInput = (dateString) => dateString ? dateString.split('T')[0] : '';

const getDuration = (start, end) => {
    if (!start || !end) return '-';
    const startDate = new Date(start);
    const endDate = new Date(end);
    const diffTime = Math.abs(endDate - startDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    return `${diffDays} วัน`;
};

// --- Modal & Logic ---
const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');
const form = useForm({ id: null, parent_id: null, name: '', type: 'project', budget: 0, progress: 0, status: 'pending', planned_start_date: '', planned_end_date: '' });

const openCreateModal = () => {
    isEditing.value = false;
    modalTitle.value = `สร้างรายการย่อยภายใต้: ${props.item.name}`;
    form.reset();
    form.parent_id = props.item.id;

    // ระบบเดาประเภทถัดไปให้อัตโนมัติ (แต่เลือกเปลี่ยนได้)
    const typeMap = { 'strategy': 'plan', 'plan': 'project', 'project': 'task', 'task': 'task' };
    form.type = typeMap[props.item.type] || 'task';

    showModal.value = true;
};

const openEditModal = (target) => {
    isEditing.value = true;
    modalTitle.value = `แก้ไขข้อมูล: ${target.name}`;
    form.id = target.id;
    form.name = target.name;
    form.type = target.type;
    form.budget = target.budget;
    form.progress = target.progress;
    form.status = target.status;
    form.planned_start_date = formatDateForInput(target.planned_start_date);
    form.planned_end_date = formatDateForInput(target.planned_end_date);
    showModal.value = true;
};

const submit = () => { if(isEditing.value) form.put(route('work-items.update', form.id), {onSuccess:()=>showModal.value=false}); else form.post(route('work-items.store'), {onSuccess:()=>showModal.value=false}); };
const deleteItem = (id) => { if(confirm('ยืนยันลบ? ข้อมูลทั้งหมดจะหายไป')) useForm({}).delete(route('work-items.destroy', id)); };

// File Logic
const fileForm = useForm({ file: null });
const uploadFile = () => { if(!fileForm.file) return; fileForm.post(route('attachments.store', props.item.id), { onSuccess:()=>{fileForm.reset();} }); };
const deleteFile = (id) => { if(confirm('ลบไฟล์?')) useForm({}).delete(route('attachments.destroy', id)); };
const downloadFile = (id) => window.open(route('attachments.download', id), '_blank');
</script>

<template>
    <Head :title="item.name" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1920px] mx-auto space-y-6">

            <nav class="text-sm text-gray-500 flex items-center gap-2">
                <Link :href="route('dashboard')" class="hover:text-[#7A2F8F]">Dashboard</Link> /
                <span class="text-[#7A2F8F] font-bold truncate max-w-[300px]">{{ item.name }}</span>
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
                    <button @click="openEditModal(item)" class="bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded text-sm font-bold text-gray-600 shrink-0">แก้ไขข้อมูล</button>
                </div>
                <div class="mt-4"><div class="flex justify-between text-xs font-bold mb-1"><span>Progress</span><span>{{ item.progress }}%</span></div><div class="w-full bg-gray-100 h-3 rounded-full"><div class="bg-[#7A2F8F] h-3 rounded-full" :style="`width:${item.progress}%`"></div></div></div>
            </div>

            <div class="border-b border-gray-200 flex space-x-8 overflow-x-auto">
                <button @click="activeTab='overview'" :class="activeTab==='overview'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap">แผนงาน (Gantt View)</button>
                <button @click="activeTab='files'" :class="activeTab==='files'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap">เอกสาร ({{ item.attachments?.length || 0 }})</button>
                <button @click="activeTab='logs'" :class="activeTab==='logs'?'border-[#7A2F8F] text-[#7A2F8F]':'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm whitespace-nowrap">ประวัติ</button>
            </div>

            <div v-show="activeTab==='overview'" class="flex flex-col lg:flex-row gap-0 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm h-[600px] animate-fade-in">

                <div class="w-full lg:w-2/5 border-r border-gray-200 flex flex-col h-full bg-white overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center shrink-0 h-[50px]">
                        <h3 class="text-xs font-bold text-gray-600 uppercase tracking-wider">TASK LIST</h3>
                        <button @click="openCreateModal" class="text-[#7A2F8F] hover:bg-purple-50 p-1 rounded"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button>
                    </div>

                    <div class="overflow-y-auto flex-1">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 sticky top-0 z-10 text-[10px] uppercase text-gray-500 font-semibold">
                                <tr>
                                    <th class="px-4 py-2 border-b">ชื่องาน (Task Name)</th>
                                    <th class="px-2 py-2 border-b w-20 text-center">เริ่ม</th>
                                    <th class="px-2 py-2 border-b w-14 text-center">เวลา</th>
                                    <th class="px-1 py-2 border-b w-16 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                                <tr v-if="!item.children?.length"><td colspan="4" class="p-4 text-center text-gray-400">ไม่มีรายการ</td></tr>
                                <tr v-for="child in item.children" :key="child.id" class="hover:bg-purple-50 group transition-colors">
                                    <td class="px-4 py-3 font-medium border-r border-dashed border-gray-100">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full shrink-0" :class="child.type==='project'?'bg-[#7A2F8F]':'bg-[#FDB913]'"></div>
                                            <Link :href="route('work-items.show', child.id)" class="truncate max-w-[180px] hover:text-[#7A2F8F] hover:underline cursor-pointer font-bold text-gray-700" :title="child.name">{{ child.name }}</Link>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3 text-center text-gray-500">{{ formatDate(child.planned_start_date) }}</td>
                                    <td class="px-2 py-3 text-center bg-gray-50/50 font-mono text-gray-500">{{ getDuration(child.planned_start_date, child.planned_end_date) }}</td>
                                    <td class="px-1 py-3 text-center">
                                        <div class="flex justify-center gap-1">
                                            <button @click="openEditModal(child)" class="p-1.5 text-gray-500 hover:text-blue-600 rounded bg-gray-100 hover:bg-blue-50 transition" title="แก้ไข"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                                            <button @click="deleteItem(child.id)" class="p-1.5 text-gray-500 hover:text-red-600 rounded bg-gray-100 hover:bg-red-50 transition" title="ลบ"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
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
                    <div class="flex-1 p-2 overflow-hidden">
                        <GanttChartView :items="item.children||[]" />
                    </div>
                </div>
            </div>

            <div v-show="activeTab==='files'" class="space-y-6 animate-fade-in">
                <div class="border-2 border-dashed p-8 text-center rounded-xl bg-gray-50">
                    <input type="file" @input="fileForm.file=$event.target.files[0]" class="mb-2"><br>
                    <button v-if="fileForm.file" @click="uploadFile" :disabled="fileForm.processing" class="bg-[#7A2F8F] text-white px-4 py-1 rounded text-sm">อัปโหลด</button>
                </div>
                <div class="bg-white rounded-xl border divide-y">
                    <div v-if="!item.attachments?.length" class="p-8 text-center text-gray-400">ไม่มีไฟล์แนบ</div>
                    <div v-for="file in item.attachments" :key="file.id" class="p-4 flex justify-between hover:bg-gray-50">
                        <span class="text-sm font-bold">{{ file.file_name }}</span>
                        <div class="flex gap-2"><button @click="downloadFile(file.id)" class="text-blue-600 text-xs">Load</button><button @click="deleteFile(file.id)" class="text-red-600 text-xs">Del</button></div>
                    </div>
                </div>
            </div>

            <div v-show="activeTab==='logs'" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 animate-fade-in">
                <h3 class="font-bold text-gray-700 mb-6">ประวัติการดำเนินการ</h3>
                <div class="space-y-6">
                    <div v-if="!item.logs?.length" class="text-gray-400 text-sm italic text-center">ยังไม่มีประวัติ</div>
                    <div v-for="log in item.logs" :key="log.id" class="flex gap-4 relative">
                        <div class="flex flex-col items-center"><div class="w-3 h-3 bg-[#7A2F8F] rounded-full z-10"></div><div class="w-0.5 h-full bg-gray-200 absolute top-3"></div></div>
                        <div class="pb-2"><p class="text-sm text-gray-800">อัปเดตเป็น {{ log.progress }}%</p><p class="text-xs text-gray-400">{{ log.uploader?.name }} • {{ formatDate(log.created_at) }}</p></div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl transform transition-all scale-100">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                    <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                    <button @click="showModal = false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                </div>

                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label>
                        <input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" placeholder="ระบุชื่อโครงการ/งาน..." required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">ประเภทรายการ <span class="text-red-500">*</span></label>
                        <select v-model="form.type" class="w-full rounded-lg border-gray-300 bg-white focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                            <option value="strategy">Strategy (ยุทธศาสตร์)</option>
                            <option value="plan">Plan (แผนงาน)</option>
                            <option value="project">Project (โครงการ)</option>
                            <option value="task">Task (กิจกรรม/งานย่อย)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ (บาท)</label>
                            <div class="relative">
                                <input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300 pr-8 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" placeholder="0.00">
                                <span class="absolute right-3 top-2.5 text-gray-400 text-xs font-bold">THB</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ความคืบหน้า (%)</label>
                            <div class="relative">
                                <input v-model="form.progress" type="number" min="0" max="100" class="w-full rounded-lg border-gray-300 pr-6 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" placeholder="0">
                                <span class="absolute right-3 top-2.5 text-gray-400 text-xs font-bold">%</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">วันที่เริ่มต้น</label>
                            <input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">วันที่สิ้นสุด</label>
                            <input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-2">
                        <button type="button" @click="showModal=false" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg font-bold transition-colors">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] hover:bg-purple-800 text-white rounded-lg font-bold shadow-md transition-colors">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>

    </PeaSidebarLayout>
</template>
