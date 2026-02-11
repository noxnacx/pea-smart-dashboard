<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    pm: Object,
    projects: Array,
    stats: Object
});

const statusColor = (s) => ({ completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', pending: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700' }[s] || 'bg-gray-100');
const formatBudget = (val) => Number(val).toLocaleString();

// --- ⚡ Quick View Logic ---
const showQuickView = ref(false);
const quickViewTitle = ref('');
const quickViewItems = ref([]);
const quickViewType = ref('');
const quickViewItemId = ref(null);

const openQuickView = (item, type) => {
    const activeItems = item.issues?.filter(i => i.type === type && i.status !== 'resolved') || [];
    if (!activeItems.length) return;

    quickViewType.value = type;
    quickViewItemId.value = item.id;
    quickViewTitle.value = type === 'issue' ? `🔥 ปัญหาที่พบ (${activeItems.length})` : `⚠️ ความเสี่ยง (${activeItems.length})`;
    quickViewItems.value = activeItems;
    showQuickView.value = true;
};
</script>

<template>
    <Head :title="pm.name" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-7xl mx-auto space-y-6">

            <nav class="flex text-sm text-gray-500 mb-4">
                <Link :href="route('pm.index')" class="hover:text-[#7A2F8F]">ทำเนียบ PM</Link>
                <span class="mx-2">/</span>
                <span class="font-bold text-[#7A2F8F]">{{ pm.name }}</span>
            </nav>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col md:flex-row items-center md:items-start gap-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-purple-50 rounded-bl-full -mr-10 -mt-10 z-0"></div>

                <div class="w-24 h-24 rounded-full bg-[#4A148C] text-white flex items-center justify-center text-4xl font-bold shadow-lg z-10 shrink-0">
                    {{ pm.name.charAt(0) }}
                </div>

                <div class="flex-1 text-center md:text-left z-10">
                    <h1 class="text-3xl font-bold text-gray-800">{{ pm.name }}</h1>
                    <p class="text-gray-500 uppercase">{{ pm.role }}</p>
                    <p v-if="pm.position" class="text-sm text-gray-400 mt-1">{{ pm.position }}</p>

                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mt-2 text-sm text-gray-600 bg-gray-50 p-2 rounded-lg border border-gray-100 w-fit mx-auto md:mx-0">

                        <div v-if="pm.division || pm.department?.division" class="flex items-center gap-1.5">
                            <span class="text-lg">🏢</span>
                            <span class="font-semibold">{{ pm.division?.name || pm.department?.division?.name }}</span>
                        </div>

                        <div v-if="pm.department" class="flex items-center gap-1.5" :class="{'border-l border-gray-300 pl-4': pm.division || pm.department?.division}">
                            <span class="text-lg">🏷️</span>
                            <span>{{ pm.department.name }}</span>
                        </div>

                        <div v-if="!pm.division && !pm.department" class="text-gray-400 italic text-xs">
                            ไม่ระบุสังกัด
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 mt-4 justify-center md:justify-start">
                        <div class="bg-purple-50 px-4 py-2 rounded-xl border border-purple-100">
                            <div class="text-xs text-gray-500 uppercase font-bold">ดูแลทั้งหมด</div>
                            <div class="text-xl font-black text-[#4A148C]">{{ pm.projects_count || 0 }} <span class="text-xs font-normal text-gray-500">โครงการ</span></div>
                        </div>
                        <div class="bg-green-50 px-4 py-2 rounded-xl border border-green-100">
                            <div class="text-xs text-gray-500 uppercase font-bold">งบประมาณรวม</div>
                            <div class="text-xl font-black text-green-700">{{ formatBudget(pm.projects_sum_budget || 0) }} <span class="text-xs font-normal text-gray-500">บาท</span></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 w-full md:w-auto z-10">
                    <div class="bg-green-50 p-3 rounded-lg text-center w-24"><div class="text-lg font-bold text-green-700">{{ stats.completed }}</div><div class="text-[10px] text-green-600">เสร็จสิ้น</div></div>
                    <div class="bg-blue-50 p-3 rounded-lg text-center w-24"><div class="text-lg font-bold text-blue-700">{{ stats.in_progress }}</div><div class="text-[10px] text-blue-600">กำลังทำ</div></div>
                    <div class="bg-red-50 p-3 rounded-lg text-center w-24"><div class="text-lg font-bold text-red-700">{{ stats.delayed }}</div><div class="text-[10px] text-red-600">ล่าช้า</div></div>
                    <div class="bg-gray-50 p-3 rounded-lg text-center w-24"><div class="text-lg font-bold text-gray-700">{{ stats.pending }}</div><div class="text-[10px] text-gray-600">รอดำเนินการ</div></div>
                </div>
            </div>

            <h2 class="text-xl font-bold text-gray-700 mt-8">📂 โครงการที่ดูแล ({{ projects.length }})</h2>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ชื่อโครงการ</th>
                            <th class="px-6 py-4">สังกัด</th>
                            <th class="px-6 py-4 text-center">สถานะ</th>
                            <th class="px-6 py-4 text-center">ความคืบหน้า</th>
                            <th class="px-6 py-4 text-right">งบประมาณ</th>
                            <th class="px-6 py-4 text-center">แจ้งเตือน</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in projects" :key="item.id" class="hover:bg-purple-50 transition">
                            <td class="px-6 py-4">
                                <Link :href="route('work-items.show', item.id)" class="font-bold text-gray-800 hover:text-[#7A2F8F] hover:underline">{{ item.name }}</Link>
                                <div class="text-xs text-gray-400 mt-1 uppercase">{{ item.type }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="item.division" class="text-gray-700">{{ item.division.name }}</div>
                                <div v-if="item.department" class="text-xs text-gray-500">{{ item.department.name }}</div>
                                <span v-if="!item.division && !item.department" class="text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4 text-center"><span class="px-2 py-1 rounded text-xs font-bold uppercase" :class="statusColor(item.status)">{{ item.status }}</span></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-full bg-gray-200 h-1.5 rounded-full"><div class="bg-[#7A2F8F] h-1.5 rounded-full" :style="`width: ${item.progress}%`"></div></div>
                                    <span class="text-xs font-bold">{{ item.progress }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right font-mono">{{ formatBudget(item.budget) }}</td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button v-if="item.has_issues" @click.stop="openQuickView(item, 'issue')" class="text-lg hover:scale-110 transition cursor-pointer p-1 rounded hover:bg-red-50" title="กดเพื่อดูปัญหา">🔥</button>
                                    <button v-if="item.has_risks" @click.stop="openQuickView(item, 'risk')" class="text-lg hover:scale-110 transition cursor-pointer p-1 rounded hover:bg-yellow-50" title="กดเพื่อดูความเสี่ยง">⚠️</button>
                                    <span v-if="!item.has_issues && !item.has_risks" class="text-gray-300">-</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="projects.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">ยังไม่มีโครงการที่ดูแล</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <Teleport to="body">
            <div v-if="showQuickView" class="fixed inset-0 z-[100] flex items-center justify-center" @click.self="showQuickView = false">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden relative z-10 animate-fade-in mx-4">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-lg" :class="quickViewType === 'issue' ? 'text-red-700' : 'text-yellow-700'">{{ quickViewTitle }}</h3>
                        <button @click="showQuickView = false" class="text-gray-400 hover:text-gray-700 font-bold text-xl">&times;</button>
                    </div>
                    <div class="p-4 max-h-[60vh] overflow-y-auto space-y-3 bg-gray-50/50 custom-scrollbar">
                        <div v-for="item in quickViewItems" :key="item.id" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-bold text-gray-800 text-sm">{{ item.title }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded font-bold uppercase tracking-wide"
                                    :class="item.severity === 'critical' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600'">
                                    {{ item.severity }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">{{ item.description }}</p>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100 bg-white text-right">
                        <Link v-if="quickViewItemId" :href="route('work-items.show', quickViewItemId)" class="text-sm font-bold text-[#7A2F8F] hover:underline flex items-center justify-end gap-1">
                            ไปจัดการที่หน้างาน <span class="text-lg">›</span>
                        </Link>
                    </div>
                </div>
            </div>
        </Teleport>

    </PeaSidebarLayout>
</template>
