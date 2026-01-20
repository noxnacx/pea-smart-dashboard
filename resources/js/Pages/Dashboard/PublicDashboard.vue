<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import WorkItemRow from '@/Components/WorkItemRow.vue';

const props = defineProps({
    hierarchy: Array,
    stats: Object,
    canLogin: Boolean,
});

// Logic การขยายเมนู (เหมือน Admin)
const expandedItems = ref({});
const toggle = (id) => expandedItems.value[id] = !expandedItems.value[id];
if (props.hierarchy) props.hierarchy.forEach(h => expandedItems.value[h.id] = true);

// คำนวณ % สำหรับ Donut Chart
const completionRate = computed(() => {
    if (!props.stats.total) return 0;
    return Math.round((props.stats.completed / props.stats.total) * 100);
});
</script>

<template>
    <Head title="PEA Smart Dashboard (Public)" />

    <div class="min-h-screen bg-gray-50 font-sans text-gray-900">

        <nav class="bg-white border-b-4 border-[#FDB913] sticky top-0 z-50 shadow-sm">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-[#7A2F8F] rounded-full flex items-center justify-center text-white font-bold text-2xl border-4 border-white shadow-lg">
                            P
                        </div>
                        <div>
                            <h1 class="text-2xl font-extrabold text-[#4A148C] tracking-tight">PEA SMART DASHBOARD</h1>
                            <p class="text-xs text-gray-500 font-medium">ระบบติดตามผลการดำเนินงาน การไฟฟ้าส่วนภูมิภาค</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <Link v-if="canLogin" :href="route('login')" class="text-sm font-bold text-[#7A2F8F] hover:bg-purple-50 px-4 py-2 rounded-lg transition">
                            Login (สำหรับเจ้าหน้าที่)
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-10">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <div class="bg-white rounded-2xl shadow-lg p-6 flex flex-col items-center justify-center border border-gray-100 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[#7A2F8F] to-[#FDB913]"></div>
                        <h3 class="text-gray-500 font-bold uppercase text-xs tracking-widest mb-6">ภาพรวมความสำเร็จ (Overall Progress)</h3>

                        <div class="relative w-48 h-48 rounded-full"
                             :style="`background: conic-gradient(#10B981 0% ${completionRate}%, #f3f4f6 ${completionRate}% 100%)`">
                            <div class="absolute inset-4 bg-white rounded-full flex flex-col items-center justify-center shadow-inner">
                                <span class="text-5xl font-black text-[#4A148C]">{{ completionRate }}%</span>
                                <span class="text-xs text-gray-400 font-bold mt-1">COMPLETED</span>
                            </div>
                        </div>

                        <div class="flex justify-center gap-6 mt-6 w-full text-xs font-bold text-gray-500">
                            <div class="flex items-center gap-2"><span class="w-3 h-3 bg-[#10B981] rounded-full"></span> สำเร็จแล้ว</div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 bg-gray-200 rounded-full"></span> อยู่ระหว่างดำเนินการ</div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                        <div class="bg-[#7A2F8F] rounded-2xl p-6 text-white shadow-lg flex flex-col justify-between relative overflow-hidden group">
                            <div class="absolute right-[-20px] top-[-20px] bg-white/10 w-32 h-32 rounded-full group-hover:scale-110 transition"></div>
                            <p class="text-purple-200 text-sm font-bold uppercase">โครงการทั้งหมด</p>
                            <p class="text-5xl font-black mt-2">{{ stats.total }}</p>
                            <p class="text-xs text-purple-200 mt-4">Total Projects</p>
                        </div>

                        <div class="bg-white rounded-2xl p-6 shadow-md border-l-4 border-red-500 flex flex-col justify-between">
                            <p class="text-gray-400 text-sm font-bold uppercase">โครงการล่าช้า</p>
                            <p class="text-5xl font-black text-red-500 mt-2">{{ stats.delayed }}</p>
                            <p class="text-xs text-red-400 mt-4">Needs Attention</p>
                        </div>

                        <div class="bg-white rounded-2xl p-6 shadow-md border-l-4 border-[#FDB913] flex flex-col justify-between col-span-2">
                             <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-gray-400 text-sm font-bold uppercase">งบประมาณรวมทั้งสิ้น (Total Budget)</p>
                                    <div class="flex items-baseline gap-2 mt-2">
                                        <p class="text-4xl font-black text-gray-800">
                                            {{ stats.budget ? Number(stats.budget).toLocaleString() : 0 }}
                                        </p>
                                        <span class="text-sm font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded">THB</span>
                                    </div>
                                </div>
                                <div class="bg-yellow-50 p-3 rounded-full">
                                    <svg class="w-8 h-8 text-[#FDB913]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-200">
                    <div class="grid grid-cols-12 gap-4 bg-gray-900 px-8 py-5 text-xs font-bold text-white uppercase tracking-widest border-b-4 border-[#FDB913]">
                        <div class="col-span-6">Strategy & Master Plan</div>
                        <div class="col-span-2 text-center">Status</div>
                        <div class="col-span-2 text-center">Progress</div>
                        <div class="col-span-2 text-right"></div>
                    </div>

                    <div class="bg-gray-50/30 min-h-[300px]">
                        <WorkItemRow
                            v-for="item in hierarchy"
                            :key="item.id"
                            :item="item"
                            :level="0"
                            :expandedItems="expandedItems"
                            :readOnly="true"
                            @toggle="toggle"
                        />
                         <div v-if="hierarchy.length === 0" class="text-center py-10 text-gray-400">
                            ไม่พบข้อมูลสาธารณะ
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <footer class="bg-[#4A148C] text-white py-8 mt-12 text-center text-sm opacity-90">
            <p>&copy; 2026 Provincial Electricity Authority (PEA). All rights reserved.</p>
        </footer>
    </div>
</template>
