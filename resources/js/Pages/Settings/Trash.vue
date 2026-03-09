<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    deletedWorkItems: Object, // เปลี่ยนเป็น Object เพราะมาจาก Pagination
    deletedIssues: Array,
});

const activeTab = ref('work_items');

// ฟังก์ชันนับจำนวนลูกหลานทั้งหมดที่ติดมาด้วย
const countChildren = (item) => {
    if (!item.children || item.children.length === 0) return 0;
    let count = item.children.length;
    item.children.forEach(child => {
        count += countChildren(child);
    });
    return count;
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

// ✅ เปลี่ยนเป็น Custom Confirm สำหรับกู้คืน
const restoreItem = (item) => {
    const childCount = countChildren(item);
    const msg = childCount > 0
        ? `ระบบจะทำการกู้คืน "${item.name}" พร้อมกับงานย่อยที่อยู่ภายใต้อีก ${childCount} รายการ ยืนยันใช่หรือไม่?`
        : `คุณต้องการกู้คืน "${item.name}" ให้กลับไปทำงานต่อใช่หรือไม่?`;

    openConfirm(
        'ยืนยันการกู้คืนข้อมูล',
        msg,
        'กู้คืนข้อมูล',
        'bg-green-500 hover:bg-green-600 shadow-green-500/30',
        'restore', // ใช้ไอคอน restore (ตั้งชื่อไว้เฉยๆ เพื่อไปใช้ใน v-if ข้างล่าง)
        () => useForm({}).post(route('trash.restore.work-item', item.id))
    );
};

// ✅ เปลี่ยนเป็น Custom Confirm สำหรับลบถาวร
const forceDeleteItem = (item) => {
    const childCount = countChildren(item);
    const msg = childCount > 0
        ? `ข้อมูล "${item.name}" พร้อมกับงานย่อยที่อยู่ภายใต้อีก ${childCount} รายการ จะถูกลบทิ้งถาวรจากระบบ\nการกระทำนี้ไม่สามารถย้อนกลับได้ ยืนยันใช่หรือไม่?`
        : `ข้อมูล "${item.name}" จะถูกลบทิ้งถาวรและไม่สามารถกู้คืนได้อีก ยืนยันใช่หรือไม่?`;

    openConfirm(
        '🚨 ยืนยันการลบถาวร',
        msg,
        'ลบถาวร',
        'bg-red-600 hover:bg-red-700 shadow-red-600/30',
        'trash',
        () => useForm({}).delete(route('trash.force-delete.work-item', item.id))
    );
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('th-TH', {
        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <Head title="ถังขยะ (Recycle Bin)" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1200px] mx-auto space-y-6">

            <div class="flex justify-between items-center border-b border-gray-200 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-red-600 flex items-center gap-2">
                        <span>🗑️</span> ถังขยะ (Recycle Bin)
                    </h2>
                    <p class="text-gray-500 mt-1">รายการที่ถูกลบจะถูกเก็บไว้ที่นี่ 30 วันก่อนถูกลบถาวรอัตโนมัติ (กู้คืนงานแม่ จะได้งานลูกคืนมาด้วย)</p>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="bg-green-100 text-green-700 p-4 rounded-xl font-bold flex items-center gap-2 border border-green-200 animate-fade-in">
                ✅ {{ $page.props.flash.success }}
            </div>

            <div class="border-b border-gray-200 flex space-x-8">
                <button @click="activeTab = 'work_items'" :class="activeTab === 'work_items' ? 'border-red-500 text-red-600' : 'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm transition-colors">
                    รายการงาน / โครงการ ({{ deletedWorkItems.total || 0 }})
                </button>
            </div>

            <div v-show="activeTab === 'work_items'" class="space-y-4 animate-fade-in">

                <div v-if="!deletedWorkItems.data || deletedWorkItems.data.length === 0" class="text-center py-20 border-2 border-dashed border-gray-300 rounded-2xl bg-white">
                    <span class="text-5xl opacity-20">🍃</span>
                    <p class="text-gray-500 font-bold mt-4">ถังขยะว่างเปล่า</p>
                </div>

                <div v-for="item in deletedWorkItems.data" :key="item.id" class="bg-white rounded-2xl shadow-sm border border-red-100 overflow-hidden hover:shadow-md transition">

                    <div class="p-5 flex justify-between items-center bg-red-50/30">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="text-xl">{{ item.work_type ? item.work_type.icon : '📄' }}</span>
                                <h3 class="text-lg font-bold text-gray-800 line-through decoration-red-400">{{ item.name }}</h3>
                                <span class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">
                                    {{ item.work_type ? item.work_type.name : item.type }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 flex items-center gap-4 ml-9">
                                <span>📅 ลบเมื่อ: <span class="font-medium text-gray-700">{{ formatDate(item.deleted_at) }}</span></span>

                                <span v-if="countChildren(item) > 0" class="text-red-500 font-bold flex items-center gap-1 bg-red-100 px-2 py-0.5 rounded">
                                    🔗 มีงานย่อยอยู่ภายใน {{ countChildren(item) }} รายการ
                                </span>
                            </div>
                        </div>

                        <div class="flex gap-2 shrink-0">
                            <button @click="restoreItem(item)" class="px-4 py-2 bg-green-50 hover:bg-green-500 text-green-700 hover:text-white rounded-lg border border-green-200 font-bold transition flex items-center gap-1 text-sm shadow-sm">
                                ♻️ กู้คืนทั้งหมด
                            </button>
                            <button @click="forceDeleteItem(item)" class="px-3 py-2 bg-white text-red-600 hover:bg-red-600 hover:text-white border border-red-200 rounded-lg font-bold transition flex items-center gap-1 text-sm shadow-sm">
                                🗑️ ลบถาวร
                            </button>
                        </div>
                    </div>

                    <div v-if="item.children && item.children.length > 0" class="border-t border-red-100 bg-gray-50 p-4 pl-12 space-y-2">
                        <p class="text-xs font-bold text-gray-400 mb-2">งานย่อยที่จะถูกกู้คืน / ลบทิ้งพร้อมกัน:</p>

                        <div v-for="child in item.children" :key="child.id" class="flex flex-col gap-1 text-sm text-gray-600 border-l-2 border-red-200 pl-3">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 text-xs">↳</span>
                                <span>{{ child.work_type ? child.work_type.icon : '📄' }}</span>
                                <span class="line-through decoration-gray-400">{{ child.name }}</span>
                                <span v-if="child.children && child.children.length > 0" class="text-[10px] text-red-400 ml-2 bg-red-50 px-1.5 rounded">
                                    + ย่อยลงไปอีก {{ countChildren(child) }} งาน
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="flex justify-between items-center mt-6 bg-white p-4 rounded-xl shadow-sm border border-gray-200" v-if="deletedWorkItems.links && deletedWorkItems.links.length > 3">
                <div class="flex flex-wrap gap-1">
                    <Link v-for="(link, key) in deletedWorkItems.links" :key="key" :href="link.url || '#'" v-html="link.label"
                          class="px-3 py-1.5 rounded-lg text-sm font-bold transition-colors border shadow-sm"
                          :class="link.active ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-red-50 hover:text-red-600'" />
                </div>
            </div>

        </div>

        <Teleport to="body">
            <div v-if="confirmDialog.isOpen" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="confirmDialog.isOpen = false"></div>
                <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative z-10 animate-fade-in p-8 text-center transform scale-100 transition-transform">

                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-5 shadow-inner"
                         :class="confirmDialog.icon === 'trash' ? 'bg-red-100 text-red-500' : (confirmDialog.icon === 'restore' ? 'bg-green-100 text-green-500' : 'bg-yellow-100 text-yellow-500')">

                        <svg v-if="confirmDialog.icon === 'trash'" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>

                        <svg v-else-if="confirmDialog.icon === 'restore'" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>

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
        </Teleport>
    </PeaSidebarLayout>
</template>
