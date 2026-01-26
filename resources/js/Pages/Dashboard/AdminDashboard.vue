<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import SCurveChart from '@/Components/SCurveChart.vue'; // ✅ ใช้ S-Curve Component

const props = defineProps({
    hierarchy: Array,
    stats: Object,
    projectChart: Object,
    sCurveChart: Object, // ✅ รับข้อมูล S-Curve รวม
    activeIssues: Array  // ✅ รับรายการปัญหาทั้งหมด
});

// --- Chart Options ---
const projectChartOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'Kanit, sans-serif' },
    labels: props.projectChart.labels,
    colors: props.projectChart.colors,
    plotOptions: { pie: { donut: { size: '70%' } } },
    dataLabels: { enabled: false },
    legend: { position: 'right', fontSize: '12px' },
    stroke: { show: false }
}));

// --- Helpers ---
const formatCurrency = (value) => new Intl.NumberFormat('th-TH').format(value);
const formatDate = (d) => d ? new Date(d).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' }) : '-';

// --- Modals ---
const showCreateModal = ref(false);
const showIssueListModal = ref(false); // ✅ Modal สำหรับดูปัญหา
const filterIssueType = ref('all'); // ตัวกรองใน Modal (All, Issue, Risk)

const form = useForm({ name: '', type: 'strategy', parent_id: null });
const submitCreate = () => { form.post(route('work-items.store'), { onSuccess: () => { showCreateModal.value = false; form.reset(); } }); };

// Accordion Toggle
const toggle = (item) => { item.isOpen = !item.isOpen; };

// Filter Issues Logic
const filteredIssues = computed(() => {
    if (filterIssueType.value === 'all') return props.activeIssues;
    return props.activeIssues.filter(i => i.type === filterIssueType.value);
});

// Badges
const getSeverityBadge = (s) => ({ critical: 'bg-red-100 text-red-700', high: 'bg-orange-100 text-orange-700', medium: 'bg-yellow-100 text-yellow-700', low: 'bg-green-100 text-green-700' }[s] || 'bg-gray-100');
</script>

<template>
    <Head title="Executive Dashboard" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-8">

            <div class="flex justify-between items-center border-b border-gray-100 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">Executive Dashboard</h2>
                    <p class="text-gray-500 mt-1">ภาพรวมสุขภาพโครงการ (Project Health) และปัญหาอุปสรรค</p>
                </div>
                <button @click="showCreateModal = true" class="bg-[#7A2F8F] hover:bg-purple-800 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-purple-200 transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่มยุทธศาสตร์
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl shadow-inner">📊</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">ความก้าวหน้าเฉลี่ย</p>
                        <h3 class="text-3xl font-extrabold text-blue-600">{{ stats.avg_progress }}<span class="text-lg text-gray-400 ml-1">%</span></h3>
                    </div>
                </div>

                <div @click="showIssueListModal=true; filterIssueType='issue'" class="bg-white p-5 rounded-2xl shadow-sm border border-l-4 border-l-red-500 border-gray-100 flex items-center gap-4 hover:shadow-lg hover:-translate-y-1 transition cursor-pointer group">
                    <div class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-2xl shadow-inner group-hover:bg-red-500 group-hover:text-white transition">🔥</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider group-hover:text-red-500">ปัญหา (Issues)</p>
                        <h3 class="text-3xl font-extrabold text-red-500">{{ stats.open_issues }} <span class="text-xs text-gray-400 font-normal">รายการ</span></h3>
                    </div>
                </div>

                <div @click="showIssueListModal=true; filterIssueType='risk'" class="bg-white p-5 rounded-2xl shadow-sm border border-l-4 border-l-orange-400 border-gray-100 flex items-center gap-4 hover:shadow-lg hover:-translate-y-1 transition cursor-pointer group">
                    <div class="w-14 h-14 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center text-2xl shadow-inner group-hover:bg-orange-500 group-hover:text-white transition">⚠️</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider group-hover:text-orange-500">ความเสี่ยง (Risks)</p>
                        <h3 class="text-3xl font-extrabold text-orange-500">{{ stats.active_risks }} <span class="text-xs text-gray-400 font-normal">รายการ</span></h3>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-2xl shadow-inner">🚨</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">ระดับวิกฤต (Critical)</p>
                        <h3 class="text-3xl font-extrabold text-gray-700">{{ stats.critical_items }} <span class="text-xs text-gray-400 font-normal">รายการ</span></h3>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">

                <div class="col-span-12 lg:col-span-7 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden min-h-[600px]">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h3 class="font-bold text-gray-700 flex items-center gap-2"><span>📑</span> โครงสร้างยุทธศาสตร์</h3>
                            <span class="text-xs text-gray-400">คลิกเพื่อดูแผนงานย่อย</span>
                        </div>

                        <div v-if="!hierarchy.length" class="p-16 text-center text-gray-400">ยังไม่มีข้อมูลยุทธศาสตร์</div>

                        <div v-for="st in hierarchy" :key="st.id" class="border-b border-gray-100 last:border-0">
                            <div class="p-4 bg-white hover:bg-purple-50 cursor-pointer flex items-center gap-4 group" @click="toggle(st)">
                                <div class="w-10 h-10 rounded-lg bg-[#7A2F8F] text-white flex items-center justify-center font-bold shadow-sm shrink-0 transition-transform group-hover:scale-110">
                                    {{ st.name.charAt(0) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center mb-1">
                                        <h4 class="font-bold text-gray-800 text-base truncate pr-4">{{ st.name }}</h4>
                                        <div class="flex items-center gap-2">
                                            <span v-if="st.strategy_issue_count > 0" class="px-2 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded-full animate-pulse">
                                                🔥 {{ st.strategy_issue_count }} ปัญหา
                                            </span>
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
                                            <Link :href="route('work-items.show', plan.id)" class="font-bold text-sm text-gray-700 hover:text-[#7A2F8F] hover:underline truncate block" :title="plan.name">{{ plan.name }}</Link>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 rounded">{{ plan.project_count }} โครงการ</span>
                                                <span v-if="plan.issue_count > 0" class="text-[10px] text-red-500 font-bold flex items-center gap-1"><span>⚠️</span> {{ plan.issue_count }}</span>
                                            </div>
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

                <div class="col-span-12 lg:col-span-5 space-y-6">

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 h-[380px] flex flex-col">
                        <h4 class="font-bold text-gray-700 mb-2 text-sm flex items-center gap-2">
                            <span>📈</span> ภาพรวมความก้าวหน้าทั้งองค์กร (S-Curve)
                        </h4>
                        <div class="flex-1 w-full relative">
                            <div v-if="sCurveChart.categories.length === 0" class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm">ยังไม่มีข้อมูล</div>
                            <SCurveChart v-else :categories="sCurveChart.categories" :planned="sCurveChart.planned" :actual="sCurveChart.actual" />
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 h-[200px] flex gap-4">
                        <div class="w-1/2 flex flex-col justify-center">
                            <h4 class="font-bold text-gray-700 text-sm mb-1">สถานะโครงการ</h4>
                            <p class="text-xs text-gray-500">สัดส่วนสถานะการดำเนินงาน</p>
                        </div>
                        <div class="w-1/2 flex items-center justify-center">
                            <VueApexCharts width="100%" height="100%" type="donut" :options="projectChartOptions" :series="props.projectChart.series" />
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showIssueListModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="showIssueListModal=false">
                <div class="bg-white rounded-2xl w-full max-w-4xl h-[80vh] overflow-hidden shadow-2xl flex flex-col">
                    <div class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center shrink-0">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-bold">📋 รายการปัญหาและความเสี่ยง</h3>
                            <div class="flex bg-gray-700 rounded-lg p-1">
                                <button @click="filterIssueType='all'" :class="filterIssueType==='all'?'bg-gray-500 text-white':'text-gray-400 hover:text-gray-200'" class="px-3 py-1 rounded text-xs transition">ทั้งหมด</button>
                                <button @click="filterIssueType='issue'" :class="filterIssueType==='issue'?'bg-red-500 text-white':'text-gray-400 hover:text-gray-200'" class="px-3 py-1 rounded text-xs transition">ปัญหา (Issue)</button>
                                <button @click="filterIssueType='risk'" :class="filterIssueType==='risk'?'bg-orange-500 text-white':'text-gray-400 hover:text-gray-200'" class="px-3 py-1 rounded text-xs transition">ความเสี่ยง (Risk)</button>
                            </div>
                        </div>
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
                                        <span class="text-[10px] px-2 py-1 rounded font-bold uppercase" :class="getSeverityBadge(issue.severity)">{{ issue.severity }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2">{{ issue.description }}</p>
                                    <div class="text-xs text-gray-400 flex gap-3 items-center">
                                        <span>📅 {{ formatDate(issue.created_at) }}</span>
                                        <span>👤 {{ issue.user?.name }}</span>
                                        <span class="bg-gray-100 px-2 py-0.5 rounded text-gray-500">โครงการ: {{ issue.work_item?.name }}</span>
                                        <Link :href="route('work-items.show', issue.work_item_id)" class="text-[#7A2F8F] hover:underline font-bold">ไปที่หน้างาน ➤</Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center text-white">
                        <h3 class="font-bold">✨ สร้างยุทธศาสตร์ใหม่</h3>
                        <button @click="showCreateModal=false" class="text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อยุทธศาสตร์</label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F]" required autofocus placeholder="ระบุชื่อยุทธศาสตร์...">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="showCreateModal=false" class="px-4 py-2 bg-gray-100 rounded-lg text-sm text-gray-600">ยกเลิก</button>
                            <button type="submit" class="px-4 py-2 bg-[#7A2F8F] text-white rounded-lg text-sm font-bold shadow hover:bg-purple-800">ยืนยัน</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </PeaSidebarLayout>
</template>
