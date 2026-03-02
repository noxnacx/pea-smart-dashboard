<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import WorkItemNode from '@/Components/WorkItemNode.vue';
import MoveWorkItemModal from '@/Components/MoveWorkItemModal.vue';
import PmAutocomplete from '@/Components/PmAutocomplete.vue'; // ✅ นำเข้า Autocomplete

const props = defineProps({
    strategies: Array,
    workItemTypes: { type: Array, default: () => [] },
    divisions: { type: Array, default: () => [] },
    parentOptions: { type: Array, default: () => [] }
});

const page = usePage();
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
const userRole = computed(() => page.props.auth.user.role);
const userId = computed(() => page.props.auth.user.id);

const topLevelTypes = computed(() => props.workItemTypes.filter(t => t.level_order === 1));

// --- 🚀 Full Modal Logic ---
const showCreateModal = ref(false);
const showSuccessModal = ref(false);
const isEditing = ref(false); // สำหรับอนาคตเผื่อใช้แก้ข้อมูลหน้า Tree
const modalTitle = ref('');

const form = useForm({
    id: null, name: '', description: '', type: 'project', budget: 0, progress: 0,
    status: 'in_active', planned_start_date: '', planned_end_date: '',
    parent_id: null, division_id: '', department_id: '', pm_name: '',
    project_manager_id: null, weight: 1
});

// Dropdown แผนก
const modalDepartments = computed(() => {
    if (!form.division_id) return [];
    const div = props.divisions.find(d => d.id == form.division_id);
    return div ? div.departments : [];
});

// ค้นหางานแม่
const showParentDropdown = ref(false);
const parentSearch = ref('');
const parentDropdownRef = ref(null);

const filteredParents = computed(() => {
    if (!parentSearch.value) return props.parentOptions;
    const lowerSearch = parentSearch.value.toLowerCase();
    return props.parentOptions.filter(p => p.name.toLowerCase().includes(lowerSearch) || (p.type_label && p.type_label.toLowerCase().includes(lowerSearch)));
});

const selectParent = (parent) => { form.parent_id = parent.id; parentSearch.value = `[${parent.type_label}] ${parent.name}`; showParentDropdown.value = false; };
const clearParent = () => { parentSearch.value = ''; form.parent_id = null; showParentDropdown.value = false; };
const handleClickOutside = (e) => { if (parentDropdownRef.value && !parentDropdownRef.value.contains(e.target)) showParentDropdown.value = false; };

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

// เปิด Modal สร้างใหม่
const openCreateModal = () => {
    isEditing.value = false;
    modalTitle.value = `✨ เพิ่มข้อมูลใหม่`;
    form.reset();
    form.clearErrors();
    form.id = null;
    form.type = topLevelTypes.value.length > 0 ? topLevelTypes.value[0].key : 'project'; // Default
    form.parent_id = null;
    parentSearch.value = '';
    form.division_id = '';
    form.department_id = '';

    if (['pm', 'project_manager'].includes(userRole.value)) {
        form.pm_name = page.props.auth.user.name;
        form.project_manager_id = userId.value;
    } else {
        form.pm_name = '';
        form.project_manager_id = null;
    }

    form.status = 'in_active';
    form.weight = 1;
    showCreateModal.value = true;
};

const closeModalSafely = () => {
    if (form.isDirty) {
        if (confirm('ข้อมูลมีการเปลี่ยนแปลงและยังไม่ได้บันทึก ต้องการปิดหน้าต่างนี้ใช่หรือไม่?')) {
            showCreateModal.value = false; form.reset(); form.clearErrors();
        }
    } else {
        showCreateModal.value = false; form.reset(); form.clearErrors();
    }
};

const submitCreate = () => {
    const options = {
        onSuccess: () => {
            showCreateModal.value = false;
            showSuccessModal.value = true;
            setTimeout(() => showSuccessModal.value = false, 2000);
        }
    };
    form.post(route('work-items.store'), options);
};

// UI Helpers
const statusColor = (status) => ({ completed: 'bg-green-100 text-green-700', delayed: 'bg-red-100 text-red-700', in_active: 'bg-gray-100 text-gray-600', in_progress: 'bg-blue-100 text-blue-700', cancelled: 'bg-gray-200 text-gray-500' }[status] || 'bg-gray-100');

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
    <Head title="โครงสร้างองค์กรแบบ Tree View" />

    <PeaSidebarLayout>
        <div class="py-8 px-6 max-w-[1920px] mx-auto space-y-6">

            <div class="flex justify-between items-center border-b border-gray-100 pb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-[#4A148C]">โครงสร้างองค์กรแบบ Tree View</h2>
                    <p class="text-gray-500 mt-1">บริหารจัดการโครงสร้างแผนงานระดับสูงสุด และกระจายเป้าหมาย (Infinite Tree View)</p>
                </div>
                <button v-if="isAdmin" @click="openCreateModal" class="bg-[#7A2F8F] hover:bg-purple-800 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-purple-200 transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                    <span class="text-xl leading-none">+</span> เพิ่มข้อมูลใหม่
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
                        :work-item-types="workItemTypes"
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
            <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeModalSafely"></div>

                <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl h-[90vh] flex flex-col relative z-10">
                    <div class="bg-[#4A148C] px-6 py-4 flex justify-between items-center border-b-4 border-[#FDB913] shrink-0">
                        <h3 class="text-lg font-bold text-white">{{ modalTitle }}</h3>
                        <button @click="closeModalSafely" class="text-white hover:text-yellow-400 font-bold text-xl">&times;</button>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">

                        <div ref="parentDropdownRef" class="relative">
                            <label class="block text-sm font-bold text-gray-700 mb-1">งานภายใต้ (สังกัด)</label>
                            <div class="relative flex items-center">
                                <input
                                    type="text"
                                    v-model="parentSearch"
                                    @input="form.parent_id = null"
                                    @focus="showParentDropdown = true"
                                    placeholder="พิมพ์ชื่อเพื่อค้นหา... (เว้นว่างไว้หากต้องการให้อยู่ระดับบนสุด)"
                                    class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F] pr-10"
                                    :class="{'bg-purple-50 text-purple-900 font-semibold border-purple-200': form.parent_id, 'border-red-500': form.errors.parent_id}"
                                >
                                <button
                                    v-if="parentSearch"
                                    @click.prevent="clearParent"
                                    type="button"
                                    class="absolute right-3 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full p-1 transition-colors z-10 flex items-center justify-center"
                                    title="ลบสังกัด"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div v-if="form.errors.parent_id" class="text-red-500 text-xs mt-1">{{ form.errors.parent_id }}</div>

                            <div v-if="showParentDropdown" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                <ul class="py-1 text-sm text-gray-700">
                                    <li v-if="filteredParents.length === 0" class="px-4 py-2 text-gray-400 italic">ไม่พบข้อมูล</li>
                                    <li v-for="parent in filteredParents" :key="parent.id" @click="selectParent(parent)" class="px-4 py-2 hover:bg-purple-50 cursor-pointer flex justify-between items-center group transition">
                                        <span>{{ parent.name }}</span>
                                        <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded group-hover:bg-purple-100 group-hover:text-purple-700">{{ parent.type_label }}</span>
                                    </li>
                                </ul>
                            </div>
                            <input type="hidden" v-model="form.parent_id">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ชื่อรายการ <span class="text-red-500">*</span></label>
                            <input v-model="form.name" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500 focus:ring-red-500': form.errors.name}" required>
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">รายละเอียด (Description)</label>
                            <textarea v-model="form.description" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] text-sm" :class="{'border-red-500': form.errors.description}" rows="3" placeholder="ระบุรายละเอียด..."></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-purple-50 p-3 rounded-lg border border-purple-100">
                            <div class="col-span-2 text-xs font-bold text-[#4A148C] uppercase">สังกัดหน่วยงาน</div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">กอง <span class="text-red-500">*</span></label>
                                <select v-model="form.division_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.division_id}" required>
                                    <option value="">-- เลือกกอง --</option>
                                    <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">แผนก</label>
                                <select v-model="form.department_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.department_id}" :disabled="!form.division_id">
                                    <option value="">-- ไม่ระบุ --</option>
                                    <option v-for="dept in modalDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">ผู้ดูแล (PM)</label>
                            <PmAutocomplete
                                v-model="form.pm_name"
                                @update:id="(id) => form.project_manager_id = id"
                                placeholder="ค้นหาจากชื่อ User..."
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">ประเภทงาน <span class="text-red-500">*</span></label>
                                <select v-model="form.type" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.type}" required>
                                    <option v-for="type in workItemTypes" :key="type.id" :value="type.key">{{ type.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">งบประมาณ</label>
                                <input v-model="form.budget" type="number" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.budget}">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">น้ำหนักงาน (Weight)</label>
                                <input v-model="form.weight" type="number" step="0.01" min="0" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]" :class="{'border-red-500': form.errors.weight}">
                                <span class="text-[10px] text-gray-500 block mt-1">ใช้คำนวณความสำคัญของงาน</span>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ (Status)</label>
                                <div class="w-full rounded-lg border border-gray-200 bg-white p-2 h-[42px] flex justify-between items-center cursor-not-allowed opacity-80" title="ระบบคำนวณสถานะให้อัตโนมัติ">
                                    <span class="text-xs font-bold px-2 py-1 rounded uppercase" :class="statusColor(form.status)">
                                        {{ form.status === 'in_active' ? 'IN ACTIVE' : form.status }}
                                    </span>
                                    <span class="text-[9px] text-[#7A2F8F] font-bold bg-purple-100 border border-purple-200 px-1.5 py-0.5 rounded">AUTO</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">เริ่ม</label>
                                <input v-model="form.planned_start_date" type="date" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">สิ้นสุด</label>
                                <input v-model="form.planned_end_date" type="date" class="w-full rounded-lg border-gray-300 focus:border-[#7A2F8F] focus:ring-[#7A2F8F]">
                            </div>
                        </div>
                    </form>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0 bg-gray-50">
                        <button type="button" @click="closeModalSafely" class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 rounded-lg font-bold">ยกเลิก</button>
                        <button type="submit" class="px-5 py-2.5 bg-[#7A2F8F] hover:bg-[#5e2270] text-white rounded-lg font-bold shadow-md" :disabled="form.processing">
                            <span v-if="form.processing">กำลังบันทึก...</span>
                            <span v-else>บันทึก</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/30 backdrop-blur-sm">
                <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center animate-fade-in">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">บันทึกสำเร็จ!</h3>
                    <p class="text-gray-500 mt-2">ข้อมูลถูกบันทึกเรียบร้อยแล้ว</p>
                </div>
            </div>
        </Teleport>
    </PeaSidebarLayout>
</template>
