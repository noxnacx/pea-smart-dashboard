<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    strategies: Array
});

// --- Create Modal Logic ---
const showCreateModal = ref(false);
const form = useForm({ name: '', type: 'strategy', parent_id: null });

const submitCreate = () => {
    form.post(route('work-items.store'), {
        onSuccess: () => { showCreateModal.value = false; form.reset(); }
    });
};

// Toggle Accordion
const toggle = (item) => { item.isOpen = !item.isOpen; };

// Helpers
const formatCurrency = (value) => new Intl.NumberFormat('th-TH').format(value);
</script>

<template>
    <Head title="ยุทธศาสตร์ทั้งหมด" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-6">

            <div class="flex justify-between items-center border-b border-gray-100 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">ยุทธศาสตร์ทั้งหมด</h2>
                    <p class="text-gray-500 mt-1">บริหารจัดการยุทธศาสตร์และแผนงานภายใต้สังกัด</p>
                </div>
                <button @click="showCreateModal = true" class="bg-[#7A2F8F] hover:bg-purple-800 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-purple-200 transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่มยุทธศาสตร์
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden min-h-[500px]">
                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center text-gray-500 text-sm font-bold">
                    <div>ชื่อยุทธศาสตร์ / แผนงาน</div>
                    <div class="pr-12">สถานะความคืบหน้า</div>
                </div>

                <div v-if="!strategies.length" class="p-20 text-center text-gray-400 flex flex-col items-center">
                    <span class="text-6xl mb-4 opacity-20">🗂️</span>
                    <p>ยังไม่มีข้อมูลยุทธศาสตร์</p>
                </div>

                <div v-for="st in strategies" :key="st.id" class="border-b border-gray-100 last:border-0">

                    <div class="p-5 bg-white hover:bg-purple-50 cursor-pointer flex items-center gap-4 group transition" @click="toggle(st)">
                        <div class="w-12 h-12 rounded-xl bg-[#7A2F8F] text-white flex items-center justify-center font-bold text-xl shadow-sm shrink-0 transition-transform group-hover:scale-110">
                            {{ st.name.match(/\d+/) ? st.name.match(/\d+/)[0] : st.name.charAt(0) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-center mb-1">
                                <h4 class="font-bold text-gray-800 text-lg truncate pr-4 group-hover:text-[#7A2F8F] transition-colors">{{ st.name }}</h4>
                                <div class="flex items-center gap-3">
                                    <span v-if="st.strategy_issue_count > 0" class="flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded-full animate-pulse">
                                        🔥 {{ st.strategy_issue_count }} ปัญหา
                                    </span>
                                    <Link :href="route('work-items.show', st.id)" @click.stop class="opacity-0 group-hover:opacity-100 px-4 py-1 bg-white border border-purple-200 text-purple-700 text-xs rounded-full font-bold transition hover:bg-purple-600 hover:text-white hover:border-transparent">
                                        จัดการข้อมูล
                                    </Link>
                                    <svg class="w-6 h-6 text-gray-300 transition-transform duration-300" :class="{'rotate-180': st.isOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 w-full md:w-2/3">
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden flex">
                                    <div class="h-full bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]" :style="`width: ${st.progress}%`"></div>
                                </div>
                                <span class="text-sm font-bold text-gray-600 min-w-[3rem] text-right">{{ st.progress }}%</span>
                            </div>
                        </div>
                    </div>

                    <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[1000px]" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100 max-h-[1000px]" leave-to-class="opacity-0 max-h-0">
                        <div v-show="st.isOpen" class="bg-gray-50/50 border-t border-gray-100 overflow-hidden">
                            <div class="p-4 pl-[5.5rem] space-y-3">
                                <div v-if="!st.children.length" class="text-sm text-gray-400 italic py-2">ยังไม่มีแผนงานย่อยในยุทธศาสตร์นี้</div>

                                <div v-for="plan in st.children" :key="plan.id" class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex justify-between items-center hover:border-purple-300 hover:shadow-md transition group">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <div class="w-1.5 h-10 bg-yellow-400 rounded-full shrink-0"></div>
                                        <div class="min-w-0">
                                            <Link :href="route('work-items.show', plan.id)" class="font-bold text-base text-gray-700 hover:text-[#7A2F8F] hover:underline truncate block" :title="plan.name">{{ plan.name }}</Link>
                                            <div class="flex items-center gap-3 mt-1">
                                                <span class="text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-md border border-gray-200">📂 {{ plan.project_count }} โครงการ</span>
                                                <span v-if="plan.issue_count > 0" class="text-[11px] text-red-500 font-bold flex items-center gap-1"><span>⚠️</span> {{ plan.issue_count }} Issues</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-6 w-1/3 justify-end">
                                        <div class="text-right flex-1">
                                            <div class="text-xs font-bold text-gray-600 mb-1">{{ plan.progress }}%</div>
                                            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                                <div class="h-full bg-yellow-400" :style="`width: ${plan.progress}%`"></div>
                                            </div>
                                        </div>
                                        <Link :href="route('work-items.show', plan.id)" class="bg-gray-50 p-2 rounded-lg text-gray-400 hover:bg-[#7A2F8F] hover:text-white transition shadow-sm"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm transition-opacity">
                <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl transform transition-all scale-100">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                        <h3 class="text-lg font-bold text-white">✨ สร้างยุทธศาสตร์ใหม่</h3>
                        <button @click="showCreateModal=false" class="text-white hover:text-yellow-400 font-bold text-xl leading-none">&times;</button>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">ชื่อยุทธศาสตร์ <span class="text-red-500">*</span></label>
                            <input v-model="form.name" class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] transition-colors" placeholder="ระบุชื่อยุทธศาสตร์..." required autofocus>
                        </div>
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <button type="button" @click="showCreateModal=false" class="px-5 py-2.5 bg-white border border-gray-300 rounded-xl font-bold text-gray-600 hover:bg-gray-50 transition">ยกเลิก</button>
                            <button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] text-white rounded-xl font-bold shadow-md hover:bg-purple-800 transition transform active:scale-95">ยืนยันการสร้าง</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PeaSidebarLayout>
</template>
