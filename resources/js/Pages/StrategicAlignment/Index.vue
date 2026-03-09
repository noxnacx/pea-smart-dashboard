<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

// 🚀 เปลี่ยนจากการรับเป็น Array ธรรมดา ให้รับ Object (เพื่อรองรับ Pagination)
const props = defineProps({
    alignments: Object,
    filters: Object
});

const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');
const form = useForm({ id: null, key: '', description: '' });

// 🚀 ระบบค้นหา
const search = ref(props.filters?.search || '');
watch(search, debounce((value) => {
    router.get(
        route('strategic-alignments.index'), // ตรวจสอบ Route Name ให้ตรงกับของคุณด้วยนะครับ
        { search: value },
        { preserveState: true, replace: true }
    );
}, 500));

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

// นำ Custom Confirm มาใช้ตอนปิด Modal แบบปลอดภัย
const closeModalSafely = () => {
    if (form.isDirty) {
        openConfirm(
            'ละทิ้งการเปลี่ยนแปลง?',
            'ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดหน้าต่างนี้ใช่หรือไม่?',
            'ละทิ้งข้อมูล',
            'bg-yellow-500 hover:bg-yellow-600 shadow-yellow-500/30',
            'warning',
            () => {
                showModal.value = false;
                form.reset();
                form.clearErrors();
            }
        );
    } else {
        showModal.value = false;
        form.reset();
        form.clearErrors();
    }
};

const openCreate = () => {
    isEditing.value = false;
    modalTitle.value = '✨ เพิ่มยุทธศาสตร์ใหม่';
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEdit = (item) => {
    isEditing.value = true;
    modalTitle.value = `✏️ แก้ไข: ${item.key}`;
    form.id = item.id;
    form.key = item.key;
    form.description = item.description;
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('strategic-alignments.update', form.id), { onSuccess: () => showModal.value = false });
    } else {
        form.post(route('strategic-alignments.store'), { onSuccess: () => showModal.value = false });
    }
};

const deleteItem = (id) => {
    openConfirm(
        'ยืนยันการลบข้อมูล',
        'คุณแน่ใจหรือไม่ว่าต้องการลบยุทธศาสตร์นี้? (ข้อมูลจะถูกลบออกจากระบบ)',
        'ลบข้อมูล',
        'bg-red-500 hover:bg-red-600 shadow-red-500/30',
        'trash',
        () => useForm({}).delete(route('strategic-alignments.destroy', id))
    );
};
</script>

<template>
    <Head title="จัดการความสอดคล้องยุทธศาสตร์" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-6xl mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-gray-200 pb-6 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-[#4A148C]">🎯 จัดการยุทธศาสตร์ (Strategic Alignments)</h2>
                    <p class="text-gray-500 mt-1">ตั้งค่า KEY ยุทธศาสตร์ เพื่อนำไปให้ผู้ใช้เลือกในหน้าโครงการ (แสดงหน้าละ 10 รายการ เรียง A-Z)</p>
                </div>
                <button @click="openCreate" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] font-bold px-5 py-2.5 rounded-xl shadow-md transition-all flex items-center gap-2 transform hover:-translate-y-0.5 whitespace-nowrap">
                    <span class="text-xl leading-none">+</span> เพิ่มยุทธศาสตร์
                </button>
            </div>

            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex items-center">
                <div class="relative w-full max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input v-model="search" type="text" class="pl-10 w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] shadow-sm text-sm" placeholder="ค้นหา KEY หรือ รายละเอียด..." />
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 w-40 font-black tracking-wider">KEY</th>
                            <th class="px-6 py-4 font-black tracking-wider">รายละเอียด (Description)</th>
                            <th class="px-6 py-4 text-center w-32 font-black tracking-wider">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="!alignments.data || alignments.data.length === 0">
                            <td colspan="3" class="px-6 py-12 text-center text-gray-400">
                                <div class="text-4xl mb-3 opacity-30">🔍</div>
                                ไม่พบข้อมูลยุทธศาสตร์
                            </td>
                        </tr>
                        <tr v-for="item in alignments.data" :key="item.id" class="hover:bg-purple-50 transition duration-150">
                            <td class="px-6 py-4 font-black text-[#7A2F8F] text-base align-top">{{ item.key }}</td>
                            <td class="px-6 py-4 text-gray-600 whitespace-pre-line leading-relaxed">{{ item.description }}</td>
                            <td class="px-6 py-4 text-center align-top">
                                <div class="flex justify-center gap-2">
                                    <button @click="openEdit(item)" class="p-1.5 rounded-lg hover:bg-blue-100 text-blue-600 transition" title="แก้ไข">✏️</button>
                                    <button @click="deleteItem(item.id)" class="p-1.5 rounded-lg hover:bg-red-100 text-red-600 transition" title="ลบ">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center bg-gray-50" v-if="alignments.links && alignments.links.length > 3">
                    <div class="flex flex-wrap gap-1">
                        <Link v-for="(link, key) in alignments.links" :key="key" :href="link.url || '#'" v-html="link.label"
                              class="px-3 py-1.5 rounded-lg text-sm font-bold transition-colors border shadow-sm"
                              :class="link.active ? 'bg-[#7A2F8F] text-white border-[#7A2F8F]' : 'bg-white text-gray-600 border-gray-300 hover:bg-purple-50 hover:text-[#7A2F8F]'"
                              :preserve-scroll="true" />
                    </div>
                    <div class="text-xs text-gray-500 font-medium">
                        แสดง <span class="font-bold text-gray-800">{{ alignments.from || 0 }}</span> ถึง <span class="font-bold text-gray-800">{{ alignments.to || 0 }}</span> จากทั้งหมด <span class="font-bold text-gray-800">{{ alignments.total }}</span> รายการ
                    </div>
                </div>
            </div>
        </div>

        <Teleport to="body">

            <div v-if="confirmDialog.isOpen" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="confirmDialog.isOpen = false"></div>
                <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative z-10 animate-fade-in p-8 text-center transform scale-100 transition-transform">

                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner"
                         :class="confirmDialog.icon === 'trash' ? 'bg-red-100 text-red-500' : 'bg-yellow-100 text-yellow-500'">
                        <svg v-if="confirmDialog.icon === 'trash'" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <svg v-else class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
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

            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @click.self="closeModalSafely">
                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-fade-in relative z-10">
                    <div class="p-6 bg-[#4A148C] text-white flex justify-between items-center border-b-4 border-[#FDB913]">
                        <h3 class="text-lg font-bold">{{ modalTitle }}</h3>
                        <button @click="closeModalSafely" class="text-white hover:text-yellow-400 font-bold text-2xl leading-none">&times;</button>
                    </div>

                    <form @submit.prevent="submit" class="p-8 space-y-6">

                        <div class="bg-purple-50 p-4 rounded-xl border border-purple-100 mb-2">
                            <label class="block text-sm font-bold text-[#4A148C] mb-2">🔑 รหัสยุทธศาสตร์ (KEY) <span class="text-red-500">*</span></label>
                            <input v-model="form.key" class="w-full border-gray-300 rounded-lg focus:border-[#7A2F8F] focus:ring-[#7A2F8F] shadow-sm font-mono text-lg tracking-wider" required placeholder="เช่น SO1, S1, ยุทธศาสตร์ที่ 1">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">📝 รายละเอียดยุทธศาสตร์ <span class="text-red-500">*</span></label>
                            <textarea v-model="form.description" rows="5" class="w-full border-gray-300 rounded-lg focus:border-[#7A2F8F] focus:ring-[#7A2F8F] shadow-sm leading-relaxed" required placeholder="ระบุรายละเอียดเป้าหมาย หรือตัวชี้วัดของยุทธศาสตร์นี้..."></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 mt-4">
                            <button type="button" @click="closeModalSafely" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold transition">ยกเลิก</button>
                            <button type="submit" class="px-5 py-2.5 bg-[#4A148C] hover:bg-purple-800 text-white rounded-xl font-bold shadow-md transition transform hover:-translate-y-0.5" :disabled="form.processing">
                                <span v-if="form.processing">กำลังบันทึก...</span>
                                <span v-else>บันทึกข้อมูล</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PeaSidebarLayout>
</template>
