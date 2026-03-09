<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    pms: Object,
    divisions: Array,
    filters: Object
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');

const filterForm = ref({
    search: props.filters.search || '',
    division_id: props.filters.division_id || '',
    department_id: props.filters.department_id || '',
    sort_by: props.filters.sort_by || 'projects_count',
    sort_dir: props.filters.sort_dir || 'desc'
});

const filterDepartments = computed(() => {
    if (!filterForm.value.division_id) return [];
    const div = props.divisions.find(d => d.id == filterForm.value.division_id);
    return div ? div.departments : [];
});

watch(filterForm, debounce(() => {
    if (!filterForm.value.division_id) {
        filterForm.value.department_id = '';
    }
    router.get(route('pm.index'), filterForm.value, { preserveState: true, replace: true });
}, 500), { deep: true });

const formatBudget = (val) => {
    if (!val) return '0';
    return Number(val).toLocaleString(undefined, { maximumFractionDigits: 1 });
};

// 🚀🚀🚀 ระบบ Custom Confirm Modal สวยๆ 🚀🚀🚀
const confirmDialog = ref({
    isOpen: false,
    title: '',
    message: '',
    confirmText: 'ยืนยัน',
    colorClass: 'bg-red-500 hover:bg-red-600 shadow-red-500/30',
    icon: 'trash',
    onConfirm: null
});

const openConfirm = (title, message, confirmText, colorClass, icon, onConfirmAction) => {
    confirmDialog.value = {
        isOpen: true,
        title,
        message,
        confirmText,
        colorClass,
        icon,
        onConfirm: onConfirmAction
    };
};

const executeConfirm = () => {
    if (confirmDialog.value.onConfirm) {
        confirmDialog.value.onConfirm();
    }
    confirmDialog.value.isOpen = false;
};

// ✅ เปลี่ยนเป็น Custom Confirm สำหรับลบ User
const deletePm = (id) => {
    openConfirm(
        'ยืนยันการลบผู้ใช้งาน',
        '⚠️ คำเตือน: การลบนี้คือการ "ลบ User" ออกจากระบบถาวร\n(งานที่บุคคลนี้ดูแลอยู่จะกลายเป็นไม่มีผู้รับผิดชอบ แต่ตัวงานจะไม่ถูกลบ)\n\nคุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้งานนี้?',
        'ลบผู้ใช้งาน',
        'bg-red-600 hover:bg-red-700 shadow-red-600/30',
        'trash',
        () => router.delete(route('pm.destroy', id))
    );
};
</script>

<template>
    <Head title="ทำเนียบผู้ดูแลโครงการ" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1600px] mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-gray-200 pb-6">
                <div>
                    <h1 class="text-3xl font-extrabold text-[#4A148C]">👤 ทำเนียบผู้ดูแลโครงการ (PM Directory)</h1>
                    <p class="text-gray-500 mt-1">รวมรายชื่อผู้รับผิดชอบและภาพรวมผลงานทั้งหมดในระบบ</p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col lg:flex-row gap-4 justify-between items-center">

                <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                    <div class="relative w-full sm:w-64">
                        <input v-model="filterForm.search" type="text" placeholder="ค้นหาชื่อ PM..." class="pl-10 w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] text-sm">
                        <span class="absolute left-3 top-2 text-gray-400">🔍</span>
                    </div>

                    <select v-model="filterForm.division_id" class="w-full sm:w-48 rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] text-sm">
                        <option value="">-- ทุกกอง --</option>
                        <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                    </select>

                    <select v-model="filterForm.department_id" class="w-full sm:w-48 rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] text-sm" :disabled="!filterForm.division_id">
                        <option value="">-- ทุกแผนก --</option>
                        <option v-for="dept in filterDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <span class="text-sm font-bold text-gray-500 whitespace-nowrap">เรียงตาม:</span>
                    <select v-model="filterForm.sort_by" class="rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] text-sm bg-gray-50">
                        <option value="projects_count">จำนวนโครงการ (มากสุด)</option>
                        <option value="budget">งบประมาณรวมสูงสุด</option>
                        <option value="name">ชื่อพนักงาน (A-Z)</option>
                    </select>
                    <button @click="filterForm.sort_dir = filterForm.sort_dir === 'desc' ? 'asc' : 'desc'" class="p-2 border border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 transition text-gray-600">
                        <svg v-if="filterForm.sort_dir === 'desc'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
                    </button>
                </div>

            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="pm in pms.data" :key="pm.id"
                      class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col items-center hover:shadow-lg hover:border-[#7A2F8F] transition-all group relative overflow-hidden transform hover:-translate-y-1">

                    <button v-if="isAdmin"
                            @click.prevent="deletePm(pm.id)"
                            class="absolute top-3 right-3 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition z-10 p-1.5"
                            title="ลบผู้ใช้งาน">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>

                    <Link :href="route('pm.show', pm.id)" class="flex flex-col items-center w-full h-full">
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center text-3xl font-black text-[#4A148C] mb-4 shadow-inner group-hover:scale-110 transition-transform duration-300 border-4 border-white ring-1 ring-gray-100">
                            {{ pm.name.charAt(0) }}
                        </div>

                        <h3 class="font-bold text-gray-800 text-lg text-center mb-1 group-hover:text-[#7A2F8F] truncate w-full px-2">{{ pm.name }}</h3>

                        <p class="text-xs text-gray-500 mb-4 bg-gray-100 px-3 py-1 rounded-full uppercase font-bold tracking-wider">{{ pm.role || 'PM' }}</p>

                        <div class="w-full grid grid-cols-2 gap-4 text-center mt-auto border-t border-gray-100 pt-5">
                            <div class="bg-blue-50/50 rounded-lg p-2 group-hover:bg-blue-50 transition">
                                <div class="text-[10px] text-blue-500 uppercase font-bold mb-1">รับผิดชอบ</div>
                                <div class="text-xl font-black text-gray-800">{{ pm.projects_count || 0 }} <span class="text-xs font-normal text-gray-500">งาน</span></div>
                            </div>
                            <div class="bg-green-50/50 rounded-lg p-2 group-hover:bg-green-50 transition">
                                <div class="text-[10px] text-green-600 uppercase font-bold mb-1">งบประมาณดูแล</div>
                                <div class="text-xl font-black text-green-700">{{ formatBudget(pm.projects_sum_budget / 1000000) }}<span class="text-sm font-bold ml-0.5">M</span></div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <div v-if="pms.data.length === 0" class="text-center py-20 border-2 border-dashed border-gray-200 rounded-2xl bg-white">
                <span class="text-5xl opacity-20">👤</span>
                <p class="text-gray-500 font-bold mt-4">ไม่พบรายชื่อผู้ดูแลตามเงื่อนไขที่ค้นหา</p>
                <button @click="filterForm.search = ''; filterForm.division_id = ''; filterForm.department_id = ''" class="text-[#7A2F8F] text-sm font-bold mt-2 hover:underline">ล้างตัวกรองทั้งหมด</button>
            </div>

            <div class="flex justify-between items-center mt-8 bg-white p-4 rounded-xl shadow-sm border border-gray-200" v-if="pms.links && pms.links.length > 3">
                <div class="flex flex-wrap gap-1">
                    <Link v-for="(link, key) in pms.links" :key="key" :href="link.url || '#'" v-html="link.label"
                          class="px-3 py-1.5 rounded-lg text-sm font-bold transition-colors border shadow-sm"
                          :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 border-gray-300 hover:bg-purple-50 hover:text-[#7A2F8F]'"
                          :preserve-scroll="true" />
                </div>
                <div class="text-sm text-gray-500 font-medium">
                    แสดง <span class="font-bold text-gray-800">{{ pms.from || 0 }}</span> ถึง <span class="font-bold text-gray-800">{{ pms.to || 0 }}</span> จากทั้งหมด <span class="font-bold text-gray-800">{{ pms.total }}</span> รายการ
                </div>
            </div>

        </div>

        <Teleport to="body">
            <div v-if="confirmDialog.isOpen" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="confirmDialog.isOpen = false"></div>
                <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative z-10 animate-fade-in p-8 text-center transform scale-100 transition-transform">

                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner bg-red-100 text-red-500">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </div>

                    <h3 class="text-2xl font-black text-gray-800 mb-2">{{ confirmDialog.title }}</h3>
                    <p class="text-sm text-gray-500 mb-8 leading-relaxed whitespace-pre-line">{{ confirmDialog.message }}</p>

                    <div class="flex gap-3 justify-center">
                        <button @click="confirmDialog.isOpen = false" class="px-5 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold transition flex-1">ยกเลิก</button>
                        <button @click="executeConfirm" class="px-5 py-3 text-white rounded-xl font-bold transition flex-1 shadow-lg transform hover:-translate-y-0.5" :class="confirmDialog.colorClass">
                            {{ confirmDialog.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </PeaSidebarLayout>
</template>
