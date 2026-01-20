<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({ users: Array });

// --- Modal Logic ---
const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');
const form = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'staff' // default
});

const openCreateModal = () => {
    isEditing.value = false;
    modalTitle.value = 'เพิ่มผู้ใช้งานใหม่';
    form.reset();
    showModal.value = true;
};

const openEditModal = (user) => {
    isEditing.value = true;
    modalTitle.value = `แก้ไขข้อมูล: ${user.name}`;
    form.reset();
    form.id = user.id;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    // Password เว้นว่างไว้ ถ้าไม่แก้
    showModal.value = true;
};

const submit = () => {
    const options = { onSuccess: () => { showModal.value = false; form.reset(); } };
    if (isEditing.value) {
        form.put(route('users.update', form.id), options);
    } else {
        form.post(route('users.store'), options);
    }
};

const deleteUser = (user) => {
    if (confirm(`ยืนยันการลบผู้ใช้ ${user.name}?`)) {
        useForm({}).delete(route('users.destroy', user.id));
    }
};
</script>

<template>
    <Head title="จัดการผู้ใช้" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-[1600px] mx-auto space-y-6">

            <div class="flex justify-between items-end border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">จัดการผู้ใช้งาน (Users)</h2>
                    <p class="text-gray-500 mt-1">เพิ่มลบแก้ไขสิทธิ์การเข้าใช้งานระบบ</p>
                </div>
                <button @click="openCreateModal" class="bg-[#FDB913] hover:bg-yellow-400 text-[#4A148C] px-5 py-2.5 rounded-lg font-bold shadow-md transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    เพิ่มผู้ใช้ใหม่
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">ชื่อ-สกุล</th>
                            <th class="px-6 py-4">อีเมล</th>
                            <th class="px-6 py-4 text-center">สิทธิ์ (Role)</th>
                            <th class="px-6 py-4 text-center">วันที่สร้าง</th>
                            <th class="px-6 py-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-purple-50 transition">
                            <td class="px-6 py-4 font-bold text-gray-700 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-purple-100 text-[#7A2F8F] flex items-center justify-center font-bold">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                {{ user.name }}
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ user.email }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase"
                                      :class="user.role === 'admin' ? 'bg-purple-100 text-[#7A2F8F]' : 'bg-green-100 text-green-700'">
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-500">{{ new Date(user.created_at).toLocaleDateString('th-TH') }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="openEditModal(user)" class="text-blue-600 hover:text-blue-800 font-bold mr-3">แก้ไข</button>
                                <button @click="deleteUser(user)" class="text-red-500 hover:text-red-700 font-bold">ลบ</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl w-full max-w-md shadow-2xl overflow-hidden">
                <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913]">
                    <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                    <button @click="showModal = false" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700">ชื่อ-สกุล</label>
                        <input v-model="form.name" type="text" class="w-full rounded border-gray-300 mt-1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700">อีเมล (ใช้เป็น Username)</label>
                        <input v-model="form.email" type="email" class="w-full rounded border-gray-300 mt-1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700">สิทธิ์การใช้งาน (Role)</label>
                        <select v-model="form.role" class="w-full rounded border-gray-300 mt-1 bg-white">
                            <option value="staff">Staff (ผู้ใช้งานทั่วไป)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <div class="border-t pt-4 mt-2">
                        <label class="block text-sm font-bold text-gray-700 mb-1">
                            {{ isEditing ? 'เปลี่ยนรหัสผ่าน (เว้นว่างถ้าไม่เปลี่ยน)' : 'กำหนดรหัสผ่าน' }}
                        </label>
                        <input v-model="form.password" type="password" class="w-full rounded border-gray-300 mb-2" placeholder="Password" :required="!isEditing">
                        <input v-model="form.password_confirmation" type="password" class="w-full rounded border-gray-300" placeholder="Confirm Password" :required="!isEditing">
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" @click="showModal=false" class="px-4 py-2 border rounded hover:bg-gray-50">ยกเลิก</button>
                        <button type="submit" class="px-4 py-2 bg-[#7A2F8F] text-white rounded font-bold shadow hover:bg-purple-800">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
