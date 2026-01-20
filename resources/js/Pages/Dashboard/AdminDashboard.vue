<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import WorkItemRow from '@/Components/WorkItemRow.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    hierarchy: Array,
    chartData: Object
});

// --- Chart Config ---
const chartOptions = computed(() => ({
    chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
    colors: ['#FDB913', '#7A2F8F'],
    stroke: { curve: 'smooth', width: 3 },
    xaxis: { categories: props.chartData?.categories || [], labels: { style: { fontSize: '12px', fontFamily: 'Inherit' } } },
    yaxis: { labels: { formatter: (val) => val >= 1000 ? (val/1000).toFixed(0)+'k' : val, style: { colors: '#64748b' } } },
    legend: { position: 'top', horizontalAlign: 'right' },
    tooltip: { y: { formatter: (val) => Number(val).toLocaleString() + ' THB' } },
    grid: { borderColor: '#f1f5f9' }
}));
const chartSeries = computed(() => [
    { name: 'แผนงานสะสม (PV)', data: props.chartData?.planned || [] },
    { name: 'ผลงานจริง (EV)', data: props.chartData?.actual || [] }
]);

// --- Stats ---
const stats = computed(() => {
    let total = 0, completed = 0, delayed = 0, budget = 0;
    const traverse = (nodes) => {
        nodes.forEach(node => {
            if (node.type === 'project') {
                total++;
                if (node.progress >= 100) completed++;
                if (node.status === 'delayed') delayed++;
                budget += Number(node.budget || 0);
            }
            if (node.children) traverse(node.children);
        });
    };
    if (props.hierarchy) traverse(props.hierarchy);
    return { total, completed, delayed, budget };
});

// --- Logic อื่นๆ ---
const expandedItems = ref({});
const toggle = (id) => expandedItems.value[id] = !expandedItems.value[id];
if (props.hierarchy) props.hierarchy.forEach(h => expandedItems.value[h.id] = true);

const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');
const form = useForm({ id: null, parent_id: null, name: '', type: 'project', budget: 0, progress: 0, status: 'pending', planned_start_date: '', planned_end_date: '' });

const openCreateModal = (parentId, typeSuggestion = 'plan') => {
    isEditing.value = false; modalTitle.value = 'สร้างรายการใหม่';
    form.reset(); form.parent_id = parentId; form.type = typeSuggestion || 'task'; if(!parentId) form.type='strategy';
    showModal.value = true;
};
const openEditModal = (item) => {
    isEditing.value = true; modalTitle.value = `แก้ไข: ${item.name}`;
    form.id = item.id; form.name = item.name; form.type = item.type; form.budget = item.budget; form.progress = item.progress; form.status = item.status; form.planned_start_date = item.planned_start_date; form.planned_end_date = item.planned_end_date;
    showModal.value = true;
};
const submit = () => {
    const routeName = isEditing.value ? 'work-items.update' : 'work-items.store';
    const options = { onSuccess: () => showModal.value = false };
    if (isEditing.value) form.put(route(routeName, form.id), options); else form.post(route(routeName), options);
};
const deleteItem = (id) => { if(confirm('ยืนยันลบ?')) useForm({}).delete(route('work-items.destroy', id)); };
</script>

<template>
    <Head title="PEA Admin Dashboard" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-[1600px] mx-auto space-y-8">
            <div class="flex justify-between items-end mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">Dashboard ภาพรวม</h2>
                    <p class="text-gray-500 mt-1">ติดตามสถานะโครงการทั้งหมด (Admin Mode)</p>
                </div>
                <button @click="openCreateModal(null, 'strategy')" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-5 py-2.5 rounded-lg font-bold shadow-md transition flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                    สร้างยุทธศาสตร์ใหม่
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-[#7A2F8F]"><p class="text-xs font-bold text-gray-400">TOTAL</p><p class="text-4xl font-black text-[#7A2F8F]">{{ stats.total }}</p></div>
                <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-green-500"><p class="text-xs font-bold text-gray-400">COMPLETED</p><p class="text-4xl font-black text-green-600">{{ stats.completed }}</p></div>
                <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-red-500"><p class="text-xs font-bold text-gray-400">DELAYED</p><p class="text-4xl font-black text-red-600">{{ stats.delayed }}</p></div>
                <div class="bg-white rounded-xl p-6 shadow-sm border-l-4 border-[#FDB913]"><p class="text-xs font-bold text-gray-400">BUDGET</p><p class="text-2xl font-black text-gray-800">{{ stats.budget.toLocaleString() }}</p></div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-bold text-[#4A148C] text-lg mb-4 flex items-center gap-2">📈 S-Curve Analysis</h3>
                <div class="h-[350px]"><VueApexCharts type="line" height="100%" :options="chartOptions" :series="chartSeries" /></div>
            </div>

            <div class="bg-white shadow-lg rounded-2xl overflow-hidden border border-gray-100">
                <div class="grid grid-cols-12 gap-4 bg-[#7A2F8F] px-8 py-4 text-xs font-bold text-white uppercase tracking-wider">
                    <div class="col-span-6">Work Structure</div>
                    <div class="col-span-2 text-center">Status</div>
                    <div class="col-span-2 text-center">Progress</div>
                    <div class="col-span-2 text-right">Action</div>
                </div>
                <div class="divide-y divide-gray-100 bg-white min-h-[300px]">
                    <div v-if="hierarchy.length === 0" class="text-center py-10 text-gray-400">ไม่มีข้อมูล</div>
                    <WorkItemRow
                        v-for="item in hierarchy"
                        :key="item.id"
                        :item="item"
                        :level="0"
                        :expandedItems="expandedItems"
                        @toggle="toggle"
                        @create="openCreateModal"
                        @edit="openEditModal"
                        @delete="deleteItem"
                    />
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center text-white font-bold text-lg border-b-4 border-[#FDB913]">
                    <span>{{ modalTitle }}</span><button @click="showModal=false">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <input v-model="form.name" class="w-full rounded border-gray-300" placeholder="ชื่อรายการ" required>
                    <div class="grid grid-cols-2 gap-4">
                        <select v-model="form.type" class="w-full rounded border-gray-300"><option value="strategy">Strategy</option><option value="plan">Plan</option><option value="project">Project</option><option value="task">Task</option></select>
                        <input v-model="form.budget" type="number" class="w-full rounded border-gray-300" placeholder="งบประมาณ">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <input v-model="form.planned_start_date" type="date" class="w-full rounded border-gray-300">
                        <input v-model="form.planned_end_date" type="date" class="w-full rounded border-gray-300">
                    </div>
                    <input v-model="form.progress" type="range" class="w-full accent-[#7A2F8F]">
                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" @click="showModal=false" class="px-4 py-2 border rounded">ยกเลิก</button>
                        <button type="submit" class="px-4 py-2 bg-[#7A2F8F] text-white rounded">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
