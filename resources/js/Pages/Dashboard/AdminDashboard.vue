<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import WorkItemNode from '@/Components/WorkItemNode.vue';
import MoveWorkItemModal from '@/Components/MoveWorkItemModal.vue';

const props = defineProps({
    hierarchy: Array,
    stats: Object,
    projectChart: Object,
    watchProjects: Array,
    activeIssues: Array
});

const page = usePage();
const userName = computed(() => page.props.auth.user.name);

// ✅ ตัวแปรและฟังก์ชันสำหรับระบบย้อนเวลา (Time Machine)
const timeMachineFilter = ref(new URLSearchParams(window.location.search).get('time') || 'now');
const specificDate = ref('');

// ถ้าเปิดหน้าเว็บมาแล้วใน URL มีวันที่แบบเจาะจง ให้เซ็ตค่าใส่ปฏิทินด้วย
if (timeMachineFilter.value && timeMachineFilter.value.match(/^\d{4}-\d{2}-\d{2}$/)) {
    specificDate.value = timeMachineFilter.value;
}

const applyTimeMachine = (timeCode) => {
    if (['now', '1m', '3m', '1y'].includes(timeCode)) {
        specificDate.value = ''; // เคลียร์ปฏิทินทิ้งถ้ากดปุ่มลัด
    }
    timeMachineFilter.value = timeCode;
    router.get(route('dashboard'), { time: timeCode }, { preserveState: true, preserveScroll: true });
};

// เมื่อกดเลือกวันที่ในปฏิทิน
const applySpecificDate = () => {
    if (specificDate.value) {
        applyTimeMachine(specificDate.value);
    }
};

const clearSpecificDate = () => {
    specificDate.value = '';
    applyTimeMachine('now');
};

// --- Helpers ---
const formatCurrency = (value) => new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB', maximumFractionDigits: 0 }).format(value);
const formatDate = (d) => d ? new Date(d).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';

const getStatusColor = (s) => ({
    completed: 'bg-green-100 text-green-700',
    in_progress: 'bg-blue-100 text-blue-700',
    delayed: 'bg-red-100 text-red-700',
    in_active: 'bg-gray-100 text-gray-600',
    cancelled: 'bg-gray-200 text-gray-500'
}[s] || 'bg-gray-100');

// ✅ ฟังก์ชันแปลข้อความให้เป็นไทย
const getStatusText = (s) => ({
    completed: 'เสร็จสิ้น', in_progress: 'กำลังทำ', delayed: 'ล่าช้า', in_active: 'รอเริ่ม', cancelled: 'ยกเลิก'
}[s] || s);

// --- ApexCharts Options ---
const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'Sarabun, sans-serif',
        events: {
            dataPointSelection: (event, chartContext, config) => {
                const index = config.dataPointIndex;
                const statusKeys = ['completed', 'in_progress', 'delayed', 'in_active', 'cancelled'];
                const selectedStatus = statusKeys[index];
                if (selectedStatus) {
                    router.get(route('projects.index'), { status: selectedStatus });
                }
            }
        }
    },
    labels: props.projectChart.labels ? props.projectChart.labels.map(l => getStatusText(l)) : [],
    colors: props.projectChart.colors,
    plotOptions: {
        pie: {
            donut: {
                size: '75%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'โครงการ',
                        fontSize: '16px',
                        fontWeight: 600,
                        color: '#374151',
                        formatter: function (w) {
                            return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                        }
                    }
                }
            }
        }
    },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontSize: '12px' },
    stroke: { show: false },
    tooltip: { enabled: true, followCursor: true }
}));

const safeRoute = (name, params = {}) => {
    try {
        return route().has(name) ? route(name, params) : '#';
    } catch (e) {
        return '#';
    }
};

const showMoveModal = ref(false);
const itemToMove = ref(null);

const openMoveModal = (item) => {
    itemToMove.value = item;
    showMoveModal.value = true;
};
</script>

<template>
    <Head title="ภาพรวมระบบ" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-8 bg-gray-50/50 min-h-screen">

            <div class="flex flex-col xl:flex-row justify-between xl:items-end border-b border-gray-200 pb-6 gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-3xl">👋</span>
                        <h2 class="text-3xl font-black text-[#4A148C]">สวัสดี, {{ userName }}</h2>
                    </div>
                    <p class="text-gray-500 font-medium">ยินดีต้อนรับสู่ <span class="text-[#FDB913] font-bold">PEA Smart Dashboard</span> ระบบติดตามโครงการและแผนงาน</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 bg-white p-1.5 rounded-xl border border-gray-200 shadow-sm">
                    <span class="text-xs font-bold text-gray-400 pl-3 pr-2 uppercase tracking-widest hidden sm:inline-block">⏳ มุมมองข้อมูล:</span>

                    <div class="flex items-center gap-2 bg-purple-50 px-3 py-1 rounded-lg border border-purple-100 transition focus-within:ring-2 focus-within:ring-purple-300">
                        <span class="text-xs font-bold text-purple-800">วันที่:</span>
                        <input type="date" v-model="specificDate" @change="applySpecificDate" class="text-xs rounded border-none bg-transparent focus:ring-0 p-0 m-0 h-6 cursor-pointer text-purple-900 font-bold">
                        <button v-if="specificDate" @click="clearSpecificDate" class="text-red-400 hover:text-red-600 text-lg leading-none transition" title="ล้างวันที่">&times;</button>
                    </div>

                    <div class="h-6 w-px bg-gray-200 mx-1 hidden md:block"></div>

                    <button @click="applyTimeMachine('now')" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='now' && !specificDate ?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">ปัจจุบัน</button>
                    <button @click="applyTimeMachine('1m')" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='1m'?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">1 เดือน</button>
                    <button @click="applyTimeMachine('3m')" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='3m'?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">3 เดือน</button>
                    <button @click="applyTimeMachine('1y')" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='1y'?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">1 ปี</button>
                </div>
            </div>

            <div v-if="timeMachineFilter !== 'now'" class="bg-yellow-50 border border-yellow-200 p-4 rounded-xl flex items-center justify-between shadow-sm animate-fade-in -mt-2">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🕒</span>
                    <div>
                        <h4 class="text-yellow-800 font-bold text-sm">
                            คุณกำลังดูข้อมูลย้อนหลัง
                            <span v-if="specificDate" class="text-[#4A148C] bg-white px-2 py-0.5 rounded border border-yellow-300 ml-1">(ณ วันที่ {{ new Date(specificDate).toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' }) }})</span>
                            <span v-else-if="timeMachineFilter === '1m'" class="text-[#4A148C] bg-white px-2 py-0.5 rounded border border-yellow-300 ml-1">(1 เดือนที่แล้ว)</span>
                            <span v-else-if="timeMachineFilter === '3m'" class="text-[#4A148C] bg-white px-2 py-0.5 rounded border border-yellow-300 ml-1">(3 เดือนที่แล้ว)</span>
                            <span v-else-if="timeMachineFilter === '1y'" class="text-[#4A148C] bg-white px-2 py-0.5 rounded border border-yellow-300 ml-1">(1 ปีที่แล้ว)</span>
                        </h4>
                        <p class="text-xs text-yellow-600 mt-1">ตัวเลขความก้าวหน้า สถานะโครงการ และกราฟ จะถูกแสดงเป็นข้อมูลจำลองตามช่วงเวลาที่คุณเลือก</p>
                    </div>
                </div>
                <button @click="clearSpecificDate" class="bg-white px-4 py-2 rounded-lg border border-yellow-300 text-yellow-700 text-xs font-bold shadow-sm hover:bg-yellow-100 transition flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    กลับสู่ปัจจุบัน
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-[#4A148C] to-[#7B1FA2] rounded-2xl p-6 text-white shadow-lg relative overflow-hidden hover:scale-[1.02] transition duration-300">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10"></div>
                    <div class="relative z-10">
                        <p class="text-purple-200 text-xs font-bold uppercase tracking-wider mb-1">โครงการทั้งหมด</p>
                        <h3 class="text-4xl font-black">{{ stats.total_projects }}</h3>
                        <p class="text-purple-200 text-sm mt-2"><span class="bg-white/20 px-1.5 rounded text-xs">Active</span> ในระบบ</p>
                    </div>
                </div>

                <Link :href="safeRoute('plans.index')" class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm relative overflow-hidden hover:shadow-md transition block">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">แผนงานทั้งหมด</p>
                            <h3 class="text-4xl font-black text-[#1E88E5]">{{ stats.total_plans }}</h3>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-xl text-[#1E88E5]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">คลิกเพื่อดูรายการแผนงาน</p>
                </Link>

                <Link :href="safeRoute('issues.index')" class="bg-gradient-to-br from-[#FDB913] to-[#F57F17] rounded-2xl p-6 text-white shadow-lg relative overflow-hidden hover:scale-[1.02] transition duration-300 block">
                    <div class="absolute right-0 bottom-0 w-24 h-24 bg-black opacity-10 rounded-full -mr-5 -mb-5"></div>
                    <div class="relative z-10">
                        <p class="text-yellow-100 text-xs font-bold uppercase tracking-wider mb-1">ปัญหาที่รอแก้ไข</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-4xl font-black">{{ stats.open_issues }}</h3>
                            <span class="text-sm font-medium opacity-80" v-if="stats.critical_items > 0">({{ stats.critical_items }} Critical)</span>
                        </div>
                        <p class="text-yellow-100 text-sm mt-2">คลิกเพื่อจัดการปัญหาและความเสี่ยง</p>
                    </div>
                </Link>

                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm relative overflow-hidden hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">ความก้าวหน้าเฉลี่ย</p>
                            <h3 class="text-3xl font-black text-[#4A148C]">{{ stats.avg_progress }}%</h3>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-xl text-[#4A148C]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">จากทุกโครงการที่ Active</p>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">

                <div class="col-span-12 lg:col-span-8 space-y-8">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden min-h-[600px]">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2"><span>📑</span> โครงสร้างยุทธศาสตร์ (Infinite Tree)</h3>
                        </div>

                        <div v-if="!hierarchy.length" class="p-10 text-center text-gray-400">ยังไม่มีข้อมูลยุทธศาสตร์</div>

                        <div v-else class="p-4">
                            <WorkItemNode
                                v-for="strategy in hierarchy"
                                :key="strategy.id"
                                :item="strategy"
                                :level="0"
                                @request-move="openMoveModal"
                            />
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 space-y-8">

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 h-[380px] flex flex-col items-center justify-center relative hover:shadow-md transition">
                        <h4 class="font-bold text-gray-700 mb-4 text-sm text-center w-full">สัดส่วนสถานะโครงการ</h4>
                        <div class="w-full h-full flex items-center justify-center">
                            <VueApexCharts class="cursor-pointer" width="100%" height="280" type="donut" :options="chartOptions" :series="props.projectChart.series" />
                        </div>
                        <div class="absolute bottom-4 text-[10px] text-gray-400 w-full text-center">
                            *คลิกที่กราฟเพื่อดูรายละเอียด
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex justify-between items-center bg-red-50/50">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2 text-sm">
                                <span class="text-red-500">🔥</span> โครงการที่ต้องจับตา (Watch List)
                            </h3>
                            <Link :href="safeRoute('projects.index', { status: 'delayed' })" class="text-[10px] text-[#4A148C] font-bold hover:underline">
                                ดูทั้งหมด ➤
                            </Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-if="watchProjects.length === 0">
                                        <td colspan="2" class="p-4 text-center text-gray-400">ไม่มีโครงการที่ต้องจับตาในขณะนี้</td>
                                    </tr>
                                    <tr v-for="project in watchProjects" :key="project.id" class="hover:bg-purple-50 transition cursor-pointer" @click="router.get(safeRoute('work-items.show', project.id))">
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-gray-700 truncate max-w-[180px]" :title="project.name">{{ project.name }}</div>

                                            <div class="text-[10px] text-gray-500 mt-1 flex items-center gap-2">
                                                <span class="flex items-center gap-1" title="ผู้รับผิดชอบ">
                                                    👤 {{ project.pm_name }}
                                                </span>
                                                <span class="text-gray-300">|</span>
                                                <span class="flex items-center gap-1 font-bold text-[#4A148C]" title="ความคืบหน้า">
                                                    📊 {{ project.progress }}%
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center align-top">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase block w-fit mx-auto mb-1" :class="getStatusColor(project.status)">
                                                {{ getStatusText(project.status) }}
                                            </span>
                                            <div class="text-[9px] text-gray-500 whitespace-nowrap" :class="{'text-red-500 font-bold': project.is_urgent}">
                                                ส่ง: {{ project.due_date }}
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <MoveWorkItemModal
            :show="showMoveModal"
            :item="itemToMove"
            @close="showMoveModal = false"
            @success="showMoveModal = false"
        />

    </PeaSidebarLayout>
</template>
