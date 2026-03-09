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
    activeIssues: Array,
    focusedItem: Object // ✅ รับค่า Focus
});

const page = usePage();
const userName = computed(() => page.props.auth.user.name);

// ✅ จัดการ State ของ URL (ควบรวมทั้ง Time Machine และ Focus Mode)
const urlParams = new URLSearchParams(window.location.search);
const timeMachineFilter = ref(urlParams.get('time') || 'now');
const specificDate = ref('');
const currentFocusId = ref(urlParams.get('focus_id') || '');

if (timeMachineFilter.value && timeMachineFilter.value.match(/^\d{4}-\d{2}-\d{2}$/)) {
    specificDate.value = timeMachineFilter.value;
}

// 🔄 ฟังก์ชันรวมอัปเดต URL (ส่งค่าทั้งเวลาและจุดโฟกัสไปพร้อมกัน)
const applyFilters = (timeCode = timeMachineFilter.value, focus = currentFocusId.value) => {
    if (['now', '1m', '3m', '1y'].includes(timeCode)) {
        specificDate.value = '';
    }
    timeMachineFilter.value = timeCode;
    currentFocusId.value = focus;

    const params = {};
    if (timeCode !== 'now') params.time = timeCode;
    if (focus) params.focus_id = focus;

    router.get(route('dashboard'), params, { preserveState: true, preserveScroll: true });
};

const applySpecificDate = () => {
    if (specificDate.value) applyFilters(specificDate.value, currentFocusId.value);
};

const clearSpecificDate = () => {
    specificDate.value = '';
    applyFilters('now', currentFocusId.value);
};

const clearFocus = () => {
    applyFilters(timeMachineFilter.value, '');
};

// 🎯 โลจิกสำหรับ Modal ค้นหาจุดโฟกัส (แปลง Tree เป็น Flat List ให้ค้นหาง่ายๆ)
const showFocusModal = ref(false);
const focusSearch = ref('');

const flattenedHierarchy = computed(() => {
    const result = [];
    const flatten = (items, depth = 0, path = '') => {
        items.forEach(item => {
            const currentPath = path ? `${path} > ${item.name}` : item.name;
            result.push({ ...item, depth, fullPath: currentPath });
            if (item.children && item.children.length > 0) {
                flatten(item.children, depth + 1, currentPath);
            }
        });
    };
    flatten(props.hierarchy);
    return result;
});

const filteredFocusList = computed(() => {
    if (!focusSearch.value) return flattenedHierarchy.value;
    const q = focusSearch.value.toLowerCase();
    return flattenedHierarchy.value.filter(item =>
        item.name.toLowerCase().includes(q) || item.fullPath.toLowerCase().includes(q)
    );
});

const selectFocus = (id) => {
    showFocusModal.value = false;
    applyFilters(timeMachineFilter.value, id);
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

                <div class="flex flex-col items-end gap-3">

                    <button @click="showFocusModal = true" class="flex items-center gap-2 bg-white px-4 py-2 rounded-xl border border-gray-200 shadow-sm hover:border-[#7A2F8F] hover:shadow transition group">
                        <div v-if="focusedItem" class="flex items-center gap-2">
                            <span class="text-lg">🎯</span>
                            <div class="text-left">
                                <div class="text-[10px] text-purple-600 font-bold uppercase tracking-wider">กำลังโฟกัสข้อมูล</div>
                                <div class="text-sm font-bold text-gray-800 truncate max-w-[200px]">{{ focusedItem.name }}</div>
                            </div>
                            <span class="bg-gray-100 p-1 rounded-full group-hover:bg-red-50 group-hover:text-red-500 transition ml-2" @click.stop="clearFocus" title="ยกเลิกโฟกัส">✖</span>
                        </div>
                        <div v-else class="flex items-center gap-2 text-gray-600">
                            <span class="text-lg grayscale opacity-50 group-hover:grayscale-0 group-hover:opacity-100 transition">🌍</span>
                            <span class="text-sm font-bold">แสดงข้อมูลทั้งหมด (Global View)</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>

                    <div class="flex flex-wrap items-center gap-2 bg-white p-1.5 rounded-xl border border-gray-200 shadow-sm">
                        <span class="text-xs font-bold text-gray-400 pl-3 pr-2 uppercase tracking-widest hidden sm:inline-block">⏳ มุมมองข้อมูล:</span>

                        <div class="flex items-center gap-2 bg-purple-50 px-3 py-1 rounded-lg border border-purple-100 transition focus-within:ring-2 focus-within:ring-purple-300">
                            <span class="text-xs font-bold text-purple-800">วันที่:</span>
                            <input type="date" v-model="specificDate" @change="applySpecificDate" class="text-xs rounded border-none bg-transparent focus:ring-0 p-0 m-0 h-6 cursor-pointer text-purple-900 font-bold">
                            <button v-if="specificDate" @click="clearSpecificDate" class="text-red-400 hover:text-red-600 text-lg leading-none transition" title="ล้างวันที่">&times;</button>
                        </div>

                        <div class="h-6 w-px bg-gray-200 mx-1 hidden md:block"></div>

                        <button @click="applyFilters('now', currentFocusId)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='now' && !specificDate ?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">ปัจจุบัน</button>
                        <button @click="applyFilters('1m', currentFocusId)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='1m'?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">1 เดือน</button>
                        <button @click="applyFilters('3m', currentFocusId)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='3m'?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">3 เดือน</button>
                        <button @click="applyFilters('1y', currentFocusId)" class="px-3 py-1.5 text-xs font-bold rounded-lg transition" :class="timeMachineFilter==='1y'?'bg-[#7A2F8F] text-white shadow':'text-gray-600 hover:bg-gray-100'">1 ปี</button>
                    </div>
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

        <Teleport to="body">
            <div v-if="showFocusModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showFocusModal = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl relative z-10 animate-fade-in flex flex-col max-h-[85vh]">

                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913] shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">🎯 เลือกเป้าหมายเพื่อโฟกัสข้อมูล</h3>
                            <p class="text-[10px] text-purple-200 mt-0.5">เมื่อเลือกแล้ว Dashboard จะแสดงผลรวมเฉพาะงานย่อยภายใต้สิ่งที่เลือกเท่านั้น</p>
                        </div>
                        <button @click="showFocusModal = false" class="text-white hover:text-yellow-400 font-bold text-2xl">&times;</button>
                    </div>

                    <div class="p-4 border-b border-gray-100 bg-gray-50 shrink-0">
                        <div class="relative">
                            <input type="text" v-model="focusSearch" placeholder="🔍 พิมพ์ค้นหาชื่องาน, แผนงาน หรือยุทธศาสตร์..." class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] pl-10 py-2.5 text-sm shadow-sm">
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>

                    <div class="overflow-y-auto flex-1 p-2 custom-scrollbar">
                        <button @click="clearFocus" class="w-full text-left p-4 rounded-xl hover:bg-gray-100 transition flex items-center gap-3 border-b border-gray-100 mb-2 group">
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-xl grayscale group-hover:grayscale-0 transition">🌍</div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm">ดูข้อมูลทั้งหมด (Global View)</h4>
                                <p class="text-xs text-gray-500">ล้างการตั้งค่าโฟกัส และดูภาพรวมของทั้งองค์กร (ค่าเริ่มต้น)</p>
                            </div>
                            <div v-if="!currentFocusId" class="ml-auto text-green-500">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </button>

                        <div v-if="filteredFocusList.length === 0" class="p-10 text-center text-gray-400">
                            ไม่มีข้อมูลที่ค้นหา
                        </div>

                        <div v-for="item in filteredFocusList" :key="item.id"
                             @click="selectFocus(item.id)"
                             class="w-full text-left p-3 rounded-lg hover:bg-purple-50 transition flex items-center gap-3 border-b border-gray-50 group cursor-pointer"
                             :class="currentFocusId == item.id ? 'bg-purple-50 ring-1 ring-purple-300' : ''">

                            <div :style="`width: ${item.depth * 20}px`" class="shrink-0 hidden md:block"></div>

                            <div class="w-8 h-8 rounded flex items-center justify-center text-lg shrink-0" :class="item.type === 'strategy' ? 'bg-blue-100' : (item.type === 'plan' ? 'bg-yellow-100' : 'bg-purple-100')">
                                {{ item.type === 'strategy' ? '🏛️' : (item.type === 'plan' ? '📁' : '🚀') }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] text-gray-400 uppercase tracking-widest font-bold mb-0.5 flex items-center gap-1">
                                    <span v-if="item.depth > 0" class="text-purple-300">↳</span>
                                    {{ item.type }}
                                </div>
                                <h4 class="font-bold text-gray-800 text-sm truncate group-hover:text-[#4A148C] transition">{{ item.name }}</h4>
                            </div>

                            <div v-if="currentFocusId == item.id" class="ml-auto text-[#7A2F8F]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </PeaSidebarLayout>
</template>
