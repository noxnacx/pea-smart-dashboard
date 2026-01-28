<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    pms: Object,
    filters: Object
});

const search = ref(props.filters.search || '');

watch(search, debounce((val) => {
    router.get(route('pm.index'), { search: val }, { preserveState: true, replace: true });
}, 500));

const formatBudget = (val) => Number(val).toLocaleString();
</script>

<template>
    <Head title="ทำเนียบผู้ดูแลโครงการ" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-7xl mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-[#4A148C]">👤 ทำเนียบผู้ดูแลโครงการ (PM Directory)</h1>
                    <p class="text-gray-500 text-sm">รวมรายชื่อผู้รับผิดชอบและภาพรวมผลงาน</p>
                </div>
                <div class="relative w-full md:w-64">
                    <input v-model="search" type="text" placeholder="ค้นหาชื่อ PM..." class="pl-10 w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F]">
                    <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <Link v-for="pm in pms.data" :key="pm.id" :href="route('pm.show', pm.id)"
                      class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col items-center hover:shadow-md hover:border-[#7A2F8F] transition group relative overflow-hidden">

                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center text-2xl font-bold text-[#4A148C] mb-4 shadow-inner group-hover:scale-110 transition">
                        {{ pm.name.charAt(0) }}
                    </div>

                    <h3 class="font-bold text-gray-800 text-lg text-center mb-1 group-hover:text-[#7A2F8F]">{{ pm.name }}</h3>
                    <p class="text-xs text-gray-500 mb-4 bg-gray-100 px-2 py-1 rounded-full">Project Manager</p>

                    <div class="w-full grid grid-cols-2 gap-2 text-center mt-auto border-t border-gray-100 pt-4">
                        <div>
                            <div class="text-xs text-gray-400 uppercase font-bold">โครงการ</div>
                            <div class="text-lg font-black text-gray-700">{{ pm.work_items_count }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400 uppercase font-bold">งบรวม (ลบ.)</div>
                            <div class="text-lg font-black text-green-600">{{ formatBudget(pm.work_items_sum_budget / 1000000) }}M</div>
                        </div>
                    </div>
                </Link>
            </div>

            <div v-if="pms.data.length === 0" class="text-center py-12 text-gray-400">
                ไม่พบรายชื่อผู้ดูแลที่ค้นหา
            </div>
        </div>
    </PeaSidebarLayout>
</template>
