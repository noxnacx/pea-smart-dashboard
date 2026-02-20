<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    deletedWorkItems: Array,
    deletedIssues: Array, // เตรียมไว้เผื่อในอนาคตต้องการกู้คืน Issue ด้วย
});

const activeTab = ref('work_items');

const restoreItem = (id) => {
    if (confirm('คุณต้องการกู้คืนข้อมูลนี้ให้กลับไปทำงานต่อใช่หรือไม่?')) {
        useForm({}).post(route('trash.restore.work-item', id));
    }
};

const forceDeleteItem = (id) => {
    if (confirm('🚨 คำเตือน: ข้อมูลนี้จะถูกลบทิ้งถาวรและไม่สามารถกู้คืนได้อีก ยืนยันใช่หรือไม่?')) {
        useForm({}).delete(route('trash.force-delete.work-item', id));
    }
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
        <div class="py-8 px-4 max-w-[1400px] mx-auto space-y-6">
            <div class="flex justify-between items-center border-b border-gray-200 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-red-600">🗑️ ถังขยะ (Recycle Bin)</h2>
                    <p class="text-gray-500 mt-1">รายการที่ถูกลบจะถูกเก็บไว้ที่นี่ 30 วันก่อนถูกลบถาวรอัตโนมัติ</p>
                </div>
            </div>

            <div class="border-b border-gray-200 flex space-x-8">
                <button @click="activeTab = 'work_items'" :class="activeTab === 'work_items' ? 'border-red-500 text-red-600' : 'text-gray-500'" class="py-3 px-1 border-b-2 font-bold text-sm transition-colors">
                    รายการงาน / โครงการ ({{ deletedWorkItems.length }})
                </button>
            </div>

            <div v-show="activeTab === 'work_items'" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ชื่อรายการ</th>
                            <th class="px-6 py-4 text-center w-32">ประเภท</th>
                            <th class="px-6 py-4 text-center">ถูกลบเมื่อ (เวลา)</th>
                            <th class="px-6 py-4 text-center w-40">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="deletedWorkItems.length === 0">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                <div class="text-4xl mb-3">🍃</div>
                                ถังขยะว่างเปล่า
                            </td>
                        </tr>
                        <tr v-for="item in deletedWorkItems" :key="item.id" class="hover:bg-red-50 transition group">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800">{{ item.name }}</div>
                                <div v-if="item.description" class="text-[10px] text-gray-400 truncate max-w-md mt-1">{{ item.description }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold uppercase">{{ item.type }}</span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500 text-xs">
                                ⏱️ {{ formatDate(item.deleted_at) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button @click="restoreItem(item.id)" class="px-3 py-1.5 bg-green-50 text-green-700 hover:bg-green-500 hover:text-white rounded border border-green-200 font-bold transition flex items-center gap-1 text-xs" title="กู้คืนรายการนี้">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> กู้คืน
                                    </button>
                                    <button @click="forceDeleteItem(item.id)" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded border border-red-200 font-bold transition flex items-center gap-1 text-xs" title="ลบถาวร (กู้ไม่ได้แล้ว)">
                                        🗑️ ลบถาวร
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
