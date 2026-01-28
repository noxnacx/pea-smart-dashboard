<script setup>
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce } from 'lodash';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    users: Object,
    divisions: Array, // ✅ รับข้อมูลกอง/แผนก มาจาก Controller
    filters: Object
});

const search = ref(props.filters.search || '');

watch(search, debounce((value) => {
    router.get(route('users.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 500));

// --- Modal Logic ---
const showModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');

const form = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    role: 'staff',
    division_id: '',   // ✅ ตัวแปรชั่วคราวสำหรับ Dropdown กอง
    department_id: '', // ✅ เก็บจริง
    is_pm: false,      // ✅ เป็น PM หรือไม่
    position: '',
    phone: ''
});

// ✅ Computed: กรองแผนกตามกองที่เลือก
const filteredDepartments = computed(() => {
    if (!form.division_id) return [];
    const div = props.divisions.find(d => d.id === form.division_id);
    return div ? div.departments : [];
});

const openCreateModal = () => {
    isEditing.value = false;
    modalTitle.value = 'เพิ่มผู้ใช้งาน';
    form.reset();
    form.division_id = ''; // Reset
    showModal.value = true;
};

const openEditModal = (user) => {
    isEditing.value = true;
    modalTitle.value = `แก้ไข: ${user.name}`;
    form.id = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = ''; // ไม่ต้องใส่รหัสเดิม
    form.role = user.role;
    form.is_pm = !!user.is_pm; // Convert to boolean
    form.position = user.position;
    form.phone = user.phone;

    // ✅ Logic การ Set ค่า Dropdown กลับคืน
    if (user.department) {
        form.department_id = user.department_id;
        form.division_id = user.department.division_id; // Set กองให้ตรงกับแผนก
    } else {
        form.department_id = '';
        form.division_id = '';
    }

    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('users.update', form.id), { onSuccess: () => showModal.value = false });
    } else {
        form.post(route('users.store'), { onSuccess: () => showModal.value = false });
    }
};

const deleteUser = (user) => {
    if(confirm(`ยืนยันลบผู้ใช้ ${user.name}?`)) {
        useForm({}).delete(route('users.destroy', user.id));
    }
};
</script>

<template>
    <Head title="จัดการผู้ใช้" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-7xl mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h1 class="text-2xl font-bold text-[#4A148C]">👥 จัดการผู้ใช้งาน (Users)</h1>
                <div class="flex gap-3 w-full md:w-auto">
                    <input v-model="search" type="text" placeholder="ค้นหาชื่อ / อีเมล..." class="w-full md:w-64 pl-4 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-[#4A148C] focus:border-[#4A148C]">
                    <button @click="openCreateModal" class="bg-[#4A148C] text-white px-4 py-2 rounded-lg font-bold shadow hover:bg-[#380d6b] transition whitespace-nowrap">+ เพิ่มผู้ใช้</button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">ชื่อ - สกุล</th>
                            <th class="px-6 py-3">บทบาท (Role)</th>
                            <th class="px-6 py-3">สังกัด (กอง/แผนก)</th> <th class="px-6 py-3 text-center">สถานะ PM</th> <th class="px-6 py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-purple-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-800">{{ user.name }}</div>
                                <div class="text-xs text-gray-500">{{ user.email }}</div>
                                <div v-if="user.phone" class="text-xs text-gray-400">📞 {{ user.phone }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-bold uppercase"
                                      :class="user.role === 'admin' ? 'bg-red-100 text-red-700' : (user.role === 'pm' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700')">
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="user.department">
                                    <div class="text-gray-700 font-bold">{{ user.department.division?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ user.department.name }}</div>
                                </div>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="user.is_pm" class="text-green-600 text-lg">✅</span>
                                <span v-else class="text-gray-300">-</span>
                            </td>
                            <td class="px-6 py-4 text-center flex justify-center gap-3">
                                <button @click="openEditModal(user)" class="text-blue-500 hover:scale-110 transition">✏️</button>
                                <button @click="deleteUser(user)" class="text-red-500 hover:scale-110 transition">🗑</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="users.links.length > 3" class="flex justify-center mt-6">
                <div class="flex gap-1">
                    <component :is="link.url ? 'Link' : 'span'" v-for="(link, i) in users.links" :key="i" :href="link.url" v-html="link.label"
                        class="px-3 py-1 rounded text-sm border transition"
                        :class="link.active ? 'bg-[#4A148C] text-white border-[#4A148C]' : 'bg-white text-gray-600 hover:bg-gray-50'" />
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="font-bold text-gray-700">{{ modalTitle }}</h3>
                    <button @click="showModal=false" class="text-2xl text-gray-400 hover:text-red-500">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อ-สกุล</label><input v-model="form.name" class="w-full rounded border-gray-300" required></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">อีเมล</label><input v-model="form.email" type="email" class="w-full rounded border-gray-300" required></div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">รหัสผ่าน {{ isEditing ? '(เว้นว่างถ้าไม่เปลี่ยน)' : '' }}</label><input v-model="form.password" type="password" class="w-full rounded border-gray-300" :required="!isEditing"></div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">สิทธิ์ (Role)</label>
                            <select v-model="form.role" class="w-full rounded border-gray-300">
                                <option value="staff">Staff</option>
                                <option value="pm">Project Manager (PM)</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <div class="col-span-2 text-xs font-bold text-gray-500 uppercase">ข้อมูลสังกัด</div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">กอง</label>
                            <select v-model="form.division_id" class="w-full rounded border-gray-300 text-sm">
                                <option value="">-- เลือกกอง --</option>
                                <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label>
                            <select v-model="form.department_id" class="w-full rounded border-gray-300 text-sm" :disabled="!form.division_id">
                                <option value="">-- เลือกแผนก --</option>
                                <option v-for="dept in filteredDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 bg-purple-50 p-3 rounded-lg border border-purple-100">
                        <input type="checkbox" v-model="form.is_pm" id="is_pm" class="w-5 h-5 text-[#4A148C] rounded focus:ring-[#4A148C]">
                        <div>
                            <label for="is_pm" class="block text-sm font-bold text-gray-800">ตั้งเป็น Project Manager (ผู้ดูแลโครงการ)</label>
                            <span class="text-xs text-gray-500">หากติ๊ก จะมีรายชื่อปรากฏในหน้าทำเนียบ PM</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">ตำแหน่ง</label><input v-model="form.position" class="w-full rounded border-gray-300"></div>
                        <div><label class="block text-sm font-bold text-gray-700 mb-1">เบอร์โทร</label><input v-model="form.phone" class="w-full rounded border-gray-300"></div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4">
                        <button type="button" @click="showModal=false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">ยกเลิก</button>
                        <button type="submit" class="px-4 py-2 bg-[#4A148C] text-white rounded font-bold hover:bg-[#380d6b]" :disabled="form.processing">
                            {{ form.processing ? 'กำลังบันทึก...' : 'บันทึก' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
