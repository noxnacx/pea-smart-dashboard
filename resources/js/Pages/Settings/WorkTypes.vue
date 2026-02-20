<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    types: Array
});

const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');

const form = useForm({
    id: null,
    name: '',
    key: '',
    level_order: 1,
    color_code: '#7A2F8F'
});

const openCreateModal = () => {
    isEditing.value = false;
    modalTitle.value = '➕ เพิ่มประเภทงานใหม่';
    form.reset();
    form.clearErrors();
    // หาค่า level สูงสุดปัจจุบันแล้วบวก 1
    const maxLevel = props.types.reduce((max, t) => t.level_order > max ? t.level_order : max, 0);
    form.level_order = maxLevel + 1;
    showModal.value = true;
};

const openEditModal = (item) => {
    isEditing.value = true;
    modalTitle.value = `✏️ แก้ไขประเภท: ${item.name}`;
    form.clearErrors();
    form.id = item.id;
    form.name = item.name;
    form.key = item.key;
    form.level_order = item.level_order;
    form.color_code = item.color_code || '#7A2F8F';
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('work-item-types.update', form.id), {
            onSuccess: () => showModal.value = false
        });
    } else {
        form.post(route('work-item-types.store'), {
            onSuccess: () => showModal.value = false
        });
    }
};

const deleteItem = (id) => {
    if (confirm('ยืนยันการลบประเภทงานนี้? (ต้องไม่มีงานค้างอยู่ในประเภทนี้)')) {
        useForm({}).delete(route('work-item-types.destroy', id));
    }
};
</script>

<template>
    <Head title="ตั้งค่าประเภทงาน" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1200px] mx-auto space-y-6">
            <div class="flex justify-between items-center border-b border-gray-200 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">⚙️ โครงสร้างประเภทงาน (Work Item Types)</h2>
                    <p class="text-gray-500 mt-1">จัดการลำดับชั้นและสีสันของประเภทงานในระบบ</p>
                </div>
                <button @click="openCreateModal" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-5 py-2.5 rounded-xl font-bold shadow-md transition-all flex items-center gap-2">
                    + เพิ่มประเภทงาน
                </button>
            </div>

            <div v-if="$page.props.errors?.error" class="bg-red-100 text-red-700 p-4 rounded-xl font-bold flex items-center gap-2 border border-red-200">
                ⚠️ {{ $page.props.errors.error }}
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-center w-24">ลำดับชั้น<br>(Level)</th>
                            <th class="px-6 py-4">ชื่อประเภทงาน</th>
                            <th class="px-6 py-4">รหัส (Key)</th>
                            <th class="px-6 py-4 text-center">สีป้าย (Color)</th>
                            <th class="px-6 py-4 text-center w-32">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="types.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400">ยังไม่มีข้อมูลประเภทงาน</td>
                        </tr>
                        <tr v-for="item in types" :key="item.id" class="hover:bg-purple-50 transition">
                            <td class="px-6 py-4 text-center font-black text-[#4A148C] text-lg">{{ item.level_order }}</td>
                            <td class="px-6 py-4 font-bold text-gray-800">{{ item.name }}</td>
                            <td class="px-6 py-4 font-mono text-gray-500">{{ item.key }}</td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-6 h-6 rounded-full shadow-inner border border-gray-300" :style="`background-color: ${item.color_code}`"></div>
                                    <span class="text-xs text-gray-500 uppercase">{{ item.color_code }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button @click="openEditModal(item)" class="p-1.5 rounded-lg hover:bg-yellow-50 text-lg transition" title="แก้ไข">✏️</button>
                                    <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg hover:bg-red-50 text-lg transition text-red-500" title="ลบ">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="bg-gray-50 p-4 border-t border-gray-200 text-xs text-gray-500">
                    💡 <b>คำแนะนำ:</b> ลำดับชั้น (Level) ที่ตัวเลขน้อยที่สุด (เช่น 1) จะถือเป็นงานระดับบนสุด (Parent สูงสุด) ในโครงสร้าง Tree
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl relative z-10">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                        <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                        <button @click="showModal = false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submit" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อประเภทงาน <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F]" required placeholder="เช่น ยุทธศาสตร์, แผนงาน">
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">รหัสอ้างอิง (Key) <span class="text-red-500">*</span></label>
                            <input v-model="form.key" type="text" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] font-mono text-sm" :disabled="isEditing" required placeholder="เช่น strategy, plan">
                            <span v-if="!isEditing" class="text-[10px] text-gray-400">ห้ามซ้ำกับรายการอื่น (ภาษาอังกฤษ/ตัวเลข/ไม่มีเว้นวรรค)</span>
                            <div v-if="form.errors.key" class="text-red-500 text-xs mt-1">{{ form.errors.key }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">ลำดับชั้น (Level) <span class="text-red-500">*</span></label>
                                <input v-model="form.level_order" type="number" min="1" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F]" required>
                                <span class="text-[10px] text-gray-400">1 = ใหญ่สุด</span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สีป้าย</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.color_code" type="color" class="w-10 h-10 rounded border border-gray-300 cursor-pointer p-0.5">
                                    <input v-model="form.color_code" type="text" class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase focus:border-[#7A2F8F]">
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100 flex justify-end gap-3 mt-2">
                            <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg font-bold">ยกเลิก</button>
                            <button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] text-white rounded-lg font-bold shadow-md" :disabled="form.processing">บันทึก</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PeaSidebarLayout>
</template>
