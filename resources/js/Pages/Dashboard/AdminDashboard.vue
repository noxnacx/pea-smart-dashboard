<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

// รับค่า Hierarchy, Stats, ChartData, RecentLogs
const props = defineProps({
    hierarchy: Array,
    stats: Object,
    chartData: Object,
    recentLogs: Array
});

// --- Helpers ---
const formatCurrency = (value) => {
    // แปลงเป็น Number ก่อนเสมอ แก้ปัญหา NaN
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(Number(value) || 0);
};

// --- Chart Config (Donut) ---
const chartOptions = {
    chart: { type: 'donut', fontFamily: 'Kanit, sans-serif' },
    labels: props.chartData.labels.length ? props.chartData.labels : ['ไม่มีข้อมูล'],
    colors: ['#7A2F8F', '#FDB913', '#10B981', '#EF4444'], // สีธีม PEA + Status Color
    plotOptions: {
        pie: { donut: { size: '65%' } }
    },
    dataLabels: { enabled: false },
    legend: { position: 'bottom' }
};
const chartSeries = props.chartData.series.length ? props.chartData.series : [1];

// --- Create Modal Logic ---
const showCreateModal = ref(false);
const form = useForm({ name: '', type: 'strategy', budget: 0, parent_id: null });
const submitCreate = () => {
    form.post(route('work-items.store'), {
        onSuccess: () => { showCreateModal.value = false; form.reset(); }
    });
};

// Toggle Accordion
const toggle = (item) => { item.isOpen = !item.isOpen; };
</script>

<template>
    <Head title="Executive Dashboard" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-8">

            <div class="flex justify-between items-center border-b border-gray-100 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">Executive Dashboard</h2>
                    <p class="text-gray-500 mt-1">ภาพรวมการดำเนินงานและงบประมาณประจำปี</p>
                </div>
                <button @click="showCreateModal = true" class="flex items-center gap-2 bg-[#7A2F8F] hover:bg-purple-800 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-purple-200 transition-all transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่มยุทธศาสตร์
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">📂</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase">โครงการทั้งหมด</p>
                        <h3 class="text-2xl font-extrabold text-gray-800">{{ stats.total_projects }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-2xl">💰</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase">งบประมาณรวม</p>
                        <h3 class="text-xl font-extrabold text-green-600">{{ formatCurrency(stats.total_budget).split('.')[0] }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center text-2xl">✅</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase">เสร็จสิ้น</p>
                        <h3 class="text-2xl font-extrabold text-[#7A2F8F]">{{ stats.completed }}</h3>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-2xl">🔥</div>
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase">ล่าช้า</p>
                        <h3 class="text-2xl font-extrabold text-red-500">{{ stats.delayed }}</h3>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-8">

                <div class="col-span-12 lg:col-span-8 space-y-6">
                    <h3 class="font-bold text-gray-700 text-lg flex items-center gap-2">
                        <span>📊</span> แผนงานและยุทธศาสตร์
                    </h3>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden divide-y divide-gray-100">
                        <div v-if="hierarchy.length === 0" class="p-8 text-center text-gray-400">ยังไม่มีข้อมูล</div>

                        <div v-for="strategy in hierarchy" :key="strategy.id" class="group bg-white">
                            <div class="p-4 hover:bg-gray-50 transition cursor-pointer flex items-center gap-4" @click="toggle(strategy)">
                                <div class="w-10 h-10 rounded-lg bg-[#7A2F8F] text-white flex items-center justify-center font-bold text-lg shadow-sm shrink-0">
                                    {{ strategy.name.match(/\d+/) ? strategy.name.match(/\d+/)[0] : strategy.name.charAt(0) }}
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-gray-800 text-base group-hover:text-[#7A2F8F] transition-colors">{{ strategy.name }}</h4>
                                    <div class="flex gap-4 mt-1 text-xs text-gray-500 font-mono">
                                        <span>งบ: {{ formatCurrency(strategy.budget) }}</span>
                                        <span :class="strategy.progress >= 100 ? 'text-green-600 font-bold' : ''">ความคืบหน้า: {{ strategy.progress }}%</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Link :href="route('work-items.show', strategy.id)" @click.stop class="px-3 py-1 text-xs font-bold text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100">รายละเอียด</Link>
                                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{'rotate-180': strategy.isOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>

                            <div v-show="strategy.isOpen" class="bg-gray-50 border-t border-gray-100 pl-16 pr-4 py-2 space-y-2">
                                <div v-if="!strategy.children?.length" class="py-2 text-sm text-gray-400 italic">ไม่มีแผนงานย่อย</div>
                                <div v-for="plan in strategy.children" :key="plan.id" class="bg-white p-3 rounded-lg border border-gray-200 flex justify-between items-center hover:border-purple-300 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-1.5 h-8 bg-[#FDB913] rounded-full"></div>
                                        <div>
                                            <p class="font-bold text-sm text-gray-700">{{ plan.name }}</p>
                                            <p class="text-[10px] text-gray-400">{{ plan.children?.length || 0 }} โครงการย่อย</p>
                                        </div>
                                    </div>
                                    <Link :href="route('work-items.show', plan.id)" class="text-gray-400 hover:text-[#7A2F8F]"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 space-y-8">

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <h4 class="font-bold text-gray-700 mb-4">สถานะโครงการ</h4>
                        <div class="flex justify-center">
                            <VueApexCharts width="100%" type="donut" :options="chartOptions" :series="chartSeries" />
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h4 class="font-bold text-gray-700 text-sm">กิจกรรมล่าสุด</h4>
                            <Link :href="route('audit-logs.index')" class="text-xs text-[#7A2F8F] hover:underline">ดูทั้งหมด</Link>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-if="recentLogs.length === 0" class="p-4 text-center text-xs text-gray-400">ไม่มีกิจกรรมเร็วๆ นี้</div>
                            <div v-for="log in recentLogs" :key="log.id" class="p-4 flex gap-3 hover:bg-gray-50 transition">
                                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ log.user ? log.user.name.charAt(0) : 'S' }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-800 truncate">{{ log.user ? log.user.name : 'System' }}</p>
                                    <p class="text-[10px] text-gray-500 truncate w-full">
                                        {{ log.action }} {{ log.model_type }} #{{ log.model_id }}
                                    </p>
                                    <p class="text-[9px] text-gray-400 mt-0.5">{{ new Date(log.created_at).toLocaleTimeString('th-TH') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm transition-opacity">
            <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center"><h3 class="text-lg font-bold text-white">✨ สร้างยุทธศาสตร์ใหม่</h3><button @click="showCreateModal=false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button></div>
                <form @submit.prevent="submitCreate" class="p-6 space-y-5">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1.5">ชื่อยุทธศาสตร์</label><input v-model="form.name" class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" required autofocus></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1.5">งบประมาณ (บาท)</label><input v-model="form.budget" type="number" class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]"></div>
                    <div class="flex justify-end gap-3 pt-2"><button type="button" @click="showCreateModal=false" class="px-4 py-2 bg-gray-100 rounded-xl font-bold text-gray-600">ยกเลิก</button><button type="submit" class="px-4 py-2 bg-[#7A2F8F] text-white rounded-xl font-bold shadow-md hover:bg-purple-800">ยืนยัน</button></div>
                </form>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
