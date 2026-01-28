<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { debounce } from 'lodash'; // ถ้าไม่มี lodash ให้ใช้ setTimeout แบบบ้านๆ ได้ครับ
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const props = defineProps({
    divisions: Object, // ✅ เปลี่ยนเป็น Object เพราะรับ Pagination มา
    filters: Object
});

// --- Search Logic ---
const search = ref(props.filters.search || '');

// ค้นหาอัตโนมัติเมื่อพิมพ์ (Debounce 500ms)
watch(search, debounce((value) => {
    router.get(route('organization.index'), { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 500));

// --- State ---
const showDivisionModal = ref(false);
const showDepartmentModal = ref(false);
const isEditing = ref(false);
const modalTitle = ref('');

// Forms
const divisionForm = useForm({ id: null, name: '', code: '' });
const departmentForm = useForm({ id: null, division_id: null, name: '', code: '' });

// --- Division Actions ---
const openCreateDivision = () => {
    isEditing.value = false;
    modalTitle.value = 'เพิ่มกองใหม่';
    divisionForm.reset();
    showDivisionModal.value = true;
};

const openEditDivision = (div) => {
    isEditing.value = true;
    modalTitle.value = 'แก้ไขกอง';
    divisionForm.id = div.id;
    divisionForm.name = div.name;
    divisionForm.code = div.code;
    showDivisionModal.value = true;
};

const submitDivision = () => {
    if (isEditing.value) {
        divisionForm.put(route('divisions.update', divisionForm.id), { onSuccess: () => showDivisionModal.value = false });
    } else {
        divisionForm.post(route('divisions.store'), { onSuccess: () => showDivisionModal.value = false });
    }
};

const deleteDivision = (div) => {
    if (confirm(`ยืนยันการลบ "${div.name}"? (แผนกภายใต้กองนี้จะถูกลบไปด้วย)`)) {
        useForm({}).delete(route('divisions.destroy', div.id));
    }
};

// --- Department Actions ---
const openCreateDepartment = (divId) => {
    isEditing.value = false;
    modalTitle.value = 'เพิ่มแผนกใหม่';
    departmentForm.reset();
    departmentForm.division_id = divId;
    showDepartmentModal.value = true;
};

const openEditDepartment = (dept) => {
    isEditing.value = true;
    modalTitle.value = 'แก้ไขแผนก';
    departmentForm.id = dept.id;
    departmentForm.division_id = dept.division_id;
    departmentForm.name = dept.name;
    departmentForm.code = dept.code;
    showDepartmentModal.value = true;
};

const submitDepartment = () => {
    if (isEditing.value) {
        departmentForm.put(route('departments.update', departmentForm.id), { onSuccess: () => showDepartmentModal.value = false });
    } else {
        departmentForm.post(route('departments.store'), { onSuccess: () => showDepartmentModal.value = false });
    }
};

const deleteDepartment = (dept) => {
    if (confirm(`ยืนยันการลบแผนก "${dept.name}"?`)) {
        useForm({}).delete(route('departments.destroy', dept.id));
    }
};
</script>

<template>
    <Head title="จัดการโครงสร้างองค์กร" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 max-w-7xl mx-auto space-y-6">

            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <h1 class="text-2xl font-bold text-[#4A148C]">🏢 จัดการโครงสร้างองค์กร</h1>

                <div class="flex gap-3 w-full md:w-auto">
                    <div class="relative w-full md:w-64">
                        <input v-model="search" type="text" placeholder="ค้นหา กอง/แผนก..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4A148C] focus:border-transparent text-sm">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <button @click="openCreateDivision" class="bg-[#4A148C] text-white px-4 py-2 rounded-lg font-bold shadow hover:bg-[#380d6b] transition whitespace-nowrap">+ เพิ่มกอง</button>
                </div>
            </div>

            <div class="space-y-6">
                <div v-for="div in divisions.data" :key="div.id" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <span class="bg-[#FDB913] text-[#4A148C] font-bold text-xs px-2 py-1 rounded">กอง</span>
                            <h2 class="text-lg font-bold text-gray-800">{{ div.name }} <span v-if="div.code" class="text-gray-400 text-sm">({{ div.code }})</span></h2>
                        </div>
                        <div class="flex gap-2">
                            <button @click="openCreateDepartment(div.id)" class="text-xs bg-green-50 text-green-600 px-3 py-1.5 rounded-lg font-bold hover:bg-green-100 border border-green-200">+ เพิ่มแผนก</button>
                            <button @click="openEditDivision(div)" class="text-gray-400 hover:text-blue-500">✏️</button>
                            <button @click="deleteDivision(div)" class="text-gray-400 hover:text-red-500">🗑</button>
                        </div>
                    </div>

                    <div class="p-4">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2">ชื่อแผนก</th>
                                    <th class="px-4 py-2 w-32">รหัส</th>
                                    <th class="px-4 py-2 w-20 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="dept in div.departments" :key="dept.id" class="hover:bg-purple-50 group">
                                    <td class="px-4 py-3 font-medium text-gray-700">{{ dept.name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ dept.code || '-' }}</td>
                                    <td class="px-4 py-3 text-center flex justify-center gap-3 opacity-0 group-hover:opacity-100 transition">
                                        <button @click="openEditDepartment(dept)" class="text-blue-500 hover:scale-110">✏️</button>
                                        <button @click="deleteDepartment(dept)" class="text-red-500 hover:scale-110">🗑</button>
                                    </td>
                                </tr>
                                <tr v-if="div.departments.length === 0">
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-400 italic">ยังไม่มีแผนกในกองนี้</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="divisions.data.length === 0" class="text-center py-12 text-gray-400">
                    <div class="text-4xl mb-2">🔍</div>
                    ไม่พบข้อมูลกองหรือแผนกที่ค้นหา
                </div>
            </div>

            <div v-if="divisions.links.length > 3" class="flex justify-center mt-6">
                <div class="flex gap-1">
                    <component
                        :is="link.url ? 'Link' : 'span'"
                        v-for="(link, index) in divisions.links"
                        :key="index"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 rounded-md text-sm border transition-colors"
                        :class="[
                            link.active ? 'bg-[#4A148C] text-white border-[#4A148C]' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50',
                            !link.url ? 'opacity-50 cursor-not-allowed' : ''
                        ]"
                    />
                </div>
            </div>
        </div>

        <div v-if="showDivisionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="font-bold text-gray-700">{{ modalTitle }}</h3>
                    <button @click="showDivisionModal=false" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อกอง</label><input v-model="divisionForm.name" class="w-full rounded border-gray-300" placeholder="เช่น กองพัฒนาระบบ..."></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">รหัสกอง (ถ้ามี)</label><input v-model="divisionForm.code" class="w-full rounded border-gray-300" placeholder="เช่น DIV-01"></div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button @click="showDivisionModal=false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">ยกเลิก</button>
                        <button @click="submitDivision" class="px-4 py-2 text-sm bg-[#4A148C] text-white rounded font-bold hover:bg-[#380d6b]">บันทึก</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDepartmentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="font-bold text-gray-700">{{ modalTitle }}</h3>
                    <button @click="showDepartmentModal=false" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">ชื่อแผนก</label><input v-model="departmentForm.name" class="w-full rounded border-gray-300" placeholder="เช่น แผนกบัญชี..."></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-1">รหัสแผนก (ถ้ามี)</label><input v-model="departmentForm.code" class="w-full rounded border-gray-300" placeholder="เช่น ACC-01"></div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button @click="showDepartmentModal=false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded">ยกเลิก</button>
                        <button @click="submitDepartment" class="px-4 py-2 text-sm bg-green-600 text-white rounded font-bold hover:bg-green-700">บันทึก</button>
                    </div>
                </div>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
