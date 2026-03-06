<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({ alignments: Array });
const showModal = ref(false);
const isEditing = ref(false);
const form = useForm({ id: null, key: '', description: '' });

const openCreate = () => { isEditing.value = false; form.reset(); form.clearErrors(); showModal.value = true; };
const openEdit = (item) => { isEditing.value = true; form.id = item.id; form.key = item.key; form.description = item.description; showModal.value = true; };
const submit = () => {
    isEditing.value ? form.put(route('strategic-alignments.update', form.id), { onSuccess: () => showModal.value = false })
                  : form.post(route('strategic-alignments.store'), { onSuccess: () => showModal.value = false });
};
const deleteItem = (id) => { if(confirm('ยืนยันลบ?')) useForm({}).delete(route('strategic-alignments.destroy', id)); };
</script>

<template>
    <Head title="จัดการความสอดคล้องยุทธศาสตร์" />
    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-5xl mx-auto space-y-6">
            <div class="flex justify-between items-end border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-3xl font-black text-[#4A148C]">จัดการยุทธศาสตร์ (Strategic Alignments)</h2>
                    <p class="text-gray-500 mt-1">ตั้งค่า KEY ยุทธศาสตร์ เพื่อนำไปให้ผู้ใช้เลือกในหน้าโครงการ</p>
                </div>
                <button @click="openCreate" class="bg-[#FDB913] text-[#4A148C] font-bold px-4 py-2 rounded-lg shadow hover:bg-yellow-400">+ เพิ่มยุทธศาสตร์</button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 border-b">
                        <tr><th class="p-4 w-32">KEY</th><th class="p-4">รายละเอียด (Description)</th><th class="p-4 text-center w-32">จัดการ</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-if="!alignments.length"><td colspan="3" class="p-8 text-center text-gray-400">ยังไม่มีข้อมูล</td></tr>
                        <tr v-for="item in alignments" :key="item.id" class="hover:bg-purple-50">
                            <td class="p-4 font-bold text-[#7A2F8F]">{{ item.key }}</td>
                            <td class="p-4 text-gray-600 whitespace-pre-line">{{ item.description }}</td>
                            <td class="p-4 text-center space-x-2">
                                <button @click="openEdit(item)" class="text-blue-500 hover:scale-110 transition">✏️</button>
                                <button @click="deleteItem(item.id)" class="text-red-500 hover:scale-110 transition">🗑️</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-xl">
                <div class="p-4 bg-[#4A148C] text-white font-bold flex justify-between">{{ isEditing ? 'แก้ไขข้อมูล' : 'เพิ่มข้อมูลใหม่' }}<button @click="showModal=false">&times;</button></div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div><label class="block text-sm font-bold mb-1">KEY (เช่น SO1)</label><input v-model="form.key" class="w-full border-gray-300 rounded-lg focus:ring-purple-500" required></div>
                    <div><label class="block text-sm font-bold mb-1">รายละเอียดยุทธศาสตร์</label><textarea v-model="form.description" rows="5" class="w-full border-gray-300 rounded-lg focus:ring-purple-500" required></textarea></div>
                    <div class="flex justify-end gap-2 pt-4 border-t"><button type="button" @click="showModal=false" class="px-4 py-2 bg-gray-100 rounded-lg">ยกเลิก</button><button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg">บันทึก</button></div>
                </form>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
