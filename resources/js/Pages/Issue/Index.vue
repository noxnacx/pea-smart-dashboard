<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    issues: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const filterType = ref(props.filters.type || '');
const filterStatus = ref(props.filters.status || '');

// Helpers
const formatDate = (d) => new Date(d).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });

const getSeverityBadge = (s) => ({
    critical: 'bg-red-100 text-red-700 border-red-200',
    high: 'bg-orange-100 text-orange-700 border-orange-200',
    medium: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    low: 'bg-green-100 text-green-700 border-green-200'
}[s] || 'bg-gray-100');

// ✅ เพิ่ม in_progress ตรงนี้
const getStatusBadge = (s) => ({
    open: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700', // สีส้ม/เหลืองเข้ม
    resolved: 'bg-green-100 text-green-700',
    mitigated: 'bg-purple-100 text-purple-700'
}[s] || 'bg-gray-100');

// Search Logic
watch([search, filterType, filterStatus], debounce(() => {
    router.get(route('issues.index'), {
        search: search.value,
        type: filterType.value,
        status: filterStatus.value
    }, { preserveState: true, replace: true });
}, 300));
</script>

<template>
    <Head title="ปัญหาและความเสี่ยงทั้งหมด" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-6">

            <div class="flex justify-between items-center border-b border-gray-100 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">ปัญหาและความเสี่ยง</h2>
                    <p class="text-gray-500 mt-1">รายการ Issue และ Risk ทั้งหมดในระบบ</p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-wrap gap-4 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
                    <input v-model="search" class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:ring-purple-500 focus:border-purple-500" placeholder="ค้นหาชื่อปัญหา หรือโครงการ...">
                </div>
                <select v-model="filterType" class="rounded-lg border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">ทุกประเภท</option>
                    <option value="issue">🔥 ปัญหา (Issue)</option>
                    <option value="risk">⚠️ ความเสี่ยง (Risk)</option>
                </select>

                <select v-model="filterStatus" class="rounded-lg border-gray-300 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">ทุกสถานะ</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-500 font-bold text-sm border-b border-gray-200">
                        <tr>
                            <th class="p-4">รายการ</th>
                            <th class="p-4">ประเภท</th>
                            <th class="p-4">ความรุนแรง</th>
                            <th class="p-4">โครงการที่เกี่ยวข้อง</th>
                            <th class="p-4">ผู้รับผิดชอบ</th>
                            <th class="p-4">สถานะ</th>
                            <th class="p-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="!issues.data.length">
                            <td colspan="7" class="p-10 text-center text-gray-400">ไม่พบข้อมูล</td>
                        </tr>
                        <tr v-for="issue in issues.data" :key="issue.id" class="hover:bg-purple-50/50 transition group">
                            <td class="p-4">
                                <div class="font-bold text-gray-800">{{ issue.title }}</div>
                                <div class="text-xs text-gray-500 truncate max-w-[200px]">{{ issue.description }}</div>
                            </td>
                            <td class="p-4">
                                <span v-if="issue.type === 'issue'" class="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded">🔥 Issue</span>
                                <span v-else class="text-xs font-bold text-yellow-600 bg-yellow-50 px-2 py-1 rounded">⚠️ Risk</span>
                            </td>
                            <td class="p-4">
                                <span class="text-[10px] font-bold px-2 py-1 rounded border uppercase" :class="getSeverityBadge(issue.severity)">
                                    {{ issue.severity }}
                                </span>
                            </td>
                            <td class="p-4">
                                <Link v-if="issue.work_item" :href="route('work-items.show', issue.work_item.id)" class="text-sm text-[#4A148C] hover:underline font-bold">
                                    {{ issue.work_item.name }}
                                </Link>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="p-4 text-sm text-gray-600">
                                {{ issue.user?.name || '-' }}
                            </td>
                            <td class="p-4">
                                <span class="text-[10px] font-bold px-2 py-1 rounded uppercase" :class="getStatusBadge(issue.status)">
                                    {{ issue.status === 'in_progress' ? 'In Progress' : issue.status }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <Link :href="route('work-items.show', issue.work_item_id) + '?tab=issues'" class="text-sm text-gray-400 hover:text-purple-600 font-bold transition">
                                    ดูรายละเอียด >
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-100 flex justify-center" v-if="issues.links.length > 3">
                    <div class="flex gap-1">
                        <Link v-for="(link, k) in issues.links" :key="k" :href="link.url || '#'" v-html="link.label"
                            class="px-3 py-1 rounded border text-sm transition"
                            :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        />
                    </div>
                </div>
            </div>

        </div>
    </PeaSidebarLayout>
</template>
