<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3'; // ✅ เพิ่ม router
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import SCurveChart from '@/Components/SCurveChart.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    hierarchy: Array,
    stats: Object,
    projectChart: Object,
    watchProjects: Array,
    sCurveChart: Object,
    activeIssues: Array
});

const page = usePage();
const userName = computed(() => page.props.auth.user.name);

// --- Helpers ---
const formatCurrency = (value) => new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB', maximumFractionDigits: 0 }).format(value);
const formatDate = (d) => d ? new Date(d).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';

const getStatusColor = (s) => ({
    completed: 'bg-green-100 text-green-700',
    in_progress: 'bg-blue-100 text-blue-700',
    delayed: 'bg-red-100 text-red-700',
    pending: 'bg-gray-100 text-gray-600',
    cancelled: 'bg-gray-200 text-gray-500'
}[s] || 'bg-gray-100');

const getStatusText = (s) => ({
    completed: 'เสร็จสิ้น', in_progress: 'กำลังทำ', delayed: 'ล่าช้า', pending: 'รอเริ่ม', cancelled: 'ยกเลิก'
}[s] || s);

// --- ApexCharts Options (พร้อม Drill-down Logic) ---
const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'Sarabun, sans-serif',
        // ✨ เพิ่ม Event สำหรับการคลิก
        events: {
            dataPointSelection: (event, chartContext, config) => {
                // 1. หาว่าคลิกที่ Index ไหน
                const index = config.dataPointIndex;
                // 2. แปลง Index เป็น Status Key (ต้องเรียงตามลำดับเดียวกับใน DashboardController)
                // ลำดับ: ['completed', 'in_progress', 'delayed', 'pending', 'cancelled']
                const statusKeys = ['completed', 'in_progress', 'delayed', 'pending', 'cancelled'];
                const selectedStatus = statusKeys[index];

                // 3. สั่ง Redirect ไปหน้า work-items พร้อมแนบ Filter
                if (selectedStatus) {
                    router.get(route('work-items.index'), { status: selectedStatus });
                }
            }
        }
    },
    labels: props.projectChart.labels,
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
    // เปลี่ยน Cursor ให้รู้ว่าคลิกได้
    tooltip: {
        enabled: true,
        followCursor: true
    }
}));

// --- Logic อื่นๆ คงเดิม ---
const showIssueListModal = ref(false);
const filterIssueType = ref('all');
const toggle = (item) => { item.isOpen = !item.isOpen; };
const filteredIssues = computed(() => {
    if (filterIssueType.value === 'all') return props.activeIssues;
    return props.activeIssues.filter(i => i.type === filterIssueType.value);
});

// Safe Route Check
const safeRoute = (name) => {
    try { return route().has(name) ? route(name) : '#'; } catch (e) { return '#'; }
};
</script>

<template>
    <Head title="ภาพรวมระบบ" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-8 bg-gray-50/50 min-h-screen">

            <div class="flex flex-col md:flex-row justify-between items-end border-b border-gray-200 pb-6 gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-3xl">👋</span>
                        <h2 class="text-3xl font-black text-[#4A148C]">สวัสดี, {{ userName }}</h2>
                    </div>
                    <p class="text-gray-500 font-medium">ยินดีต้อนรับสู่ <span class="text-[#FDB913] font-bold">PEA Smart Dashboard</span> ระบบติดตามโครงการและแผนงาน</p>
                </div>
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

                <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm relative overflow-hidden hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">งบประมาณรวม</p>
                            <h3 class="text-2xl font-black text-gray-800">{{ formatCurrency(stats.total_budget) }}</h3>
                        </div>
                        <div class="p-3 bg-green-50 rounded-xl text-green-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 h-1.5 mt-4 rounded-full overflow-hidden"><div class="bg-green-500 h-full w-[70%]"></div></div>
                </div>

                <div @click="showIssueListModal=true" class="cursor-pointer bg-gradient-to-br from-[#FDB913] to-[#F57F17] rounded-2xl p-6 text-white shadow-lg relative overflow-hidden hover:scale-[1.02] transition duration-300">
                    <div class="absolute right-0 bottom-0 w-24 h-24 bg-black opacity-10 rounded-full -mr-5 -mb-5"></div>
                    <div class="relative z-10">
                        <p class="text-yellow-100 text-xs font-bold uppercase tracking-wider mb-1">ปัญหาที่รอแก้ไข</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-4xl font-black">{{ stats.open_issues }}</h3>
                            <span class="text-sm font-medium opacity-80" v-if="stats.critical_items > 0">({{ stats.critical_items }} Critical)</span>
                        </div>
                        <p class="text-yellow-100 text-sm mt-2">คลิกเพื่อดูรายละเอียด</p>
                    </div>
                </div>

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

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                        <h4 class="font-bold text-gray-700 mb-4 text-lg flex items-center gap-2">
                            <span class="bg-purple-100 p-1.5 rounded-lg text-purple-600">📈</span> ภาพรวมความก้าวหน้าทั้งองค์กร (S-Curve)
                        </h4>
                        <div class="h-[400px] w-full relative">
                            <div v-if="sCurveChart.categories.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm">ยังไม่มีข้อมูล</div>
                            <SCurveChart v-else :categories="sCurveChart.categories" :planned="sCurveChart.planned" :actual="sCurveChart.actual" />
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2"><span>📑</span> โครงสร้างยุทธศาสตร์</h3>
                        </div>
                        <div v-if="!hierarchy.length" class="p-8 text-center text-gray-400">ยังไม่มีข้อมูลยุทธศาสตร์</div>
                        <div v-for="st in hierarchy" :key="st.id" class="border-b border-gray-100 last:border-0">
                            <div class="p-4 bg-white hover:bg-purple-50 cursor-pointer flex items-center gap-4 group" @click="toggle(st)">
                                <div class="w-10 h-10 rounded-lg bg-[#7A2F8F] text-white flex items-center justify-center font-bold shadow-sm shrink-0 transition-transform group-hover:scale-110">
                                    {{ st.name.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center mb-1">
                                        <h4 class="font-bold text-gray-800 text-base truncate pr-4">{{ st.name }}</h4>
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-[#4A148C] text-sm">{{ st.progress }}%</span>
                                            <svg class="w-5 h-5 text-gray-300 transition-transform duration-300" :class="{'rotate-180': st.isOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </div>
                                    </div>
                                    <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden flex">
                                        <div class="h-full bg-green-500" :style="`width: ${st.progress}%`"></div>
                                    </div>
                                </div>
                            </div>

                            <div v-show="st.isOpen" class="bg-gray-50 border-t border-gray-100 p-3 pl-[4.5rem] space-y-2 shadow-inner">
                                <div v-if="!st.children.length" class="text-sm text-gray-400 italic">ยังไม่มีแผนงานย่อย</div>
                                <div v-for="plan in st.children" :key="plan.id" class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm flex justify-between items-center hover:border-purple-300 transition">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-1 h-8 bg-[#FDB913] rounded-full shrink-0"></div>
                                        <div class="min-w-0">
                                            <Link :href="safeRoute('work-items.show') !== '#' ? route('work-items.show', plan.id) : '#'" class="font-bold text-sm text-gray-700 hover:text-[#7A2F8F] hover:underline truncate block">{{ plan.name }}</Link>
                                            <div class="text-[10px] text-gray-400">{{ plan.project_count }} โครงการ</div>
                                        </div>
                                    </div>
                                    <div class="text-right w-16">
                                        <div class="text-xs font-bold text-gray-600">{{ plan.progress }}%</div>
                                        <div class="w-full bg-gray-100 h-1 rounded-full mt-1"><div class="h-full bg-[#FDB913]" :style="`width: ${plan.progress}%`"></div></div>
                                    </div>
                                </div>
                            </div>
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
                            <Link :href="safeRoute('work-items.index')" class="text-[10px] text-[#4A148C] font-bold hover:underline">ดูทั้งหมด</Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-if="watchProjects.length === 0">
                                        <td colspan="2" class="p-4 text-center text-gray-400">ไม่มีโครงการที่ต้องจับตาในขณะนี้</td>
                                    </tr>
                                    <tr v-for="project in watchProjects" :key="project.id" class="hover:bg-purple-50 transition cursor-pointer" @click="router.get(safeRoute('work-items.show') !== '#' ? route('work-items.show', project.id) : '#')">
                                        <td class="px-4 py-3">
                                            <div class="font-bold text-gray-700 truncate max-w-[150px]" :title="project.name">{{ project.name }}</div>
                                            <div class="text-[10px] text-gray-400 mt-0.5">งบ: {{ formatCurrency(project.budget) }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase block w-fit mx-auto mb-1" :class="getStatusColor(project.status)">
                                                {{ getStatusText(project.status) }}
                                            </span>
                                            <div class="text-[9px] text-gray-500" :class="{'text-red-500 font-bold': project.is_urgent}">
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

        <Teleport to="body">
            <div v-if="showIssueListModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showIssueListModal=false">
                <div class="bg-white rounded-2xl w-full max-w-4xl h-[80vh] overflow-hidden shadow-2xl flex flex-col">
                    <div class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center shrink-0">
                        <h3 class="text-lg font-bold">📋 รายการปัญหาและความเสี่ยง</h3>
                        <button @click="showIssueListModal=false" class="text-2xl hover:text-red-400">&times;</button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
                        <div v-if="filteredIssues.length === 0" class="text-center text-gray-400 py-20 text-lg">ไม่มีรายการในหมวดนี้</div>
                        <div v-else class="grid gap-4">
                            <div v-for="issue in filteredIssues" :key="issue.id" class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition flex gap-4">
                                <div class="text-2xl pt-1">{{ issue.type === 'issue' ? '🔥' : '⚠️' }}</div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-1">
                                        <h4 class="font-bold text-gray-800 text-lg">{{ issue.title }}</h4>
                                        <span class="text-[10px] px-2 py-1 rounded font-bold uppercase" :class="issue.severity==='critical'?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700'">{{ issue.severity }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2">{{ issue.description }}</p>
                                    <div class="text-xs text-gray-400 flex gap-3 items-center">
                                        <span>โครงการ: {{ issue.work_item?.name }}</span>
                                        <Link :href="safeRoute('work-items.show') !== '#' ? route('work-items.show', issue.work_item_id) : '#'" class="text-[#7A2F8F] hover:underline font-bold">ไปที่หน้างาน ➤</Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </PeaSidebarLayout>
</template>
