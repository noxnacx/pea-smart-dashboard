<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import WorkItemNode from '@/Components/WorkItemNode.vue';
import MoveWorkItemModal from '@/Components/MoveWorkItemModal.vue';

const props = defineProps({
    strategies: Array,
    workItemTypes: { type: Array, default: () => [] } // รับประเภทงานทั้งหมดมาจาก Controller
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');

// 🚀 หาว่าประเภทงาน Level 1 (สูงสุด) คืออะไร เพื่อเอาชื่อมาโชว์ที่ปุ่ม
const topLevelTypes = computed(() => props.workItemTypes.filter(t => t.level_order === 1));
const topLevelName = computed(() => {
    return topLevelTypes.value.length > 0 ? topLevelTypes.value[0].name : 'ยุทธศาสตร์';
});

// --- Create Modal Logic ---
const showCreateModal = ref(false);
const form = useForm({
    name: '',
    type: 'strategy',
    work_item_type_id: null,
    parent_id: null
});

const openCreateModal = () => {
    form.reset();
    // Default ตั้งค่าให้เป็น Level 1 ตัวแรกที่เจอ
    if (topLevelTypes.value.length > 0) {
        form.work_item_type_id = topLevelTypes.value[0].id;
        form.type = topLevelTypes.value[0].key;
    }
    showCreateModal.value = true;
};

const submitCreate = () => {
    form.post(route('work-items.store'), {
        onSuccess: () => { showCreateModal.value = false; form.reset(); }
    });
};

// --- Move Modal Logic ---
const showMoveModal = ref(false);
const itemToMove = ref(null);

const openMoveModal = (item) => {
    if (!isAdmin.value) return;
    itemToMove.value = item;
    showMoveModal.value = true;
};
</script>

<template>
    <Head :title="`โครงสร้าง${topLevelName}`" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-6">

            <div class="flex justify-between items-center border-b border-gray-100 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">โครงสร้าง{{ topLevelName }}ทั้งหมด</h2>
                    <p class="text-gray-500 mt-1">บริหารจัดการแผนระดับสูงสุด และโครงสร้างย่อยทั้งหมด (Infinite Tree View)</p>
                </div>
                <button v-if="isAdmin" @click="openCreateModal" class="bg-[#7A2F8F] hover:bg-purple-800 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-purple-200 transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่ม{{ topLevelName }}
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden min-h-[500px]">

                <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center text-gray-500 text-sm font-bold">
                    <div class="pl-4">โครงสร้างรายการงาน</div>
                    <div class="pr-16">สถานะความคืบหน้า</div>
                </div>

                <div v-if="!strategies.length" class="p-20 text-center text-gray-400 flex flex-col items-center">
                    <span class="text-6xl mb-4 opacity-20">🗂️</span>
                    <p>ยังไม่มีข้อมูลในระบบ</p>
                </div>

                <div v-else>
                    <WorkItemNode
                        v-for="strategy in strategies"
                        :key="strategy.id"
                        :item="strategy"
                        :level="0"
                        :can-manage="isAdmin"
                        @request-move="openMoveModal"
                    />
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
            <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm transition-opacity">
                <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl transform transition-all scale-100">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                        <h3 class="text-lg font-bold text-white">✨ สร้าง{{ topLevelName }}ใหม่</h3>
                        <button @click="showCreateModal=false" class="text-white hover:text-yellow-400 font-bold text-xl leading-none">&times;</button>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-5">

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">ประเภท <span class="text-red-500">*</span></label>
                            <select v-model="form.work_item_type_id" class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F]" required>
                                <option v-for="type in topLevelTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">ชื่อรายการ <span class="text-red-500">*</span></label>
                            <input v-model="form.name" class="w-full rounded-xl border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] transition-colors" placeholder="ระบุชื่อ..." required autofocus>
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
