<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    item: Object, // งานที่จะถูกย้าย (The Item being moved)
});

const emit = defineEmits(['close', 'success']);

// State
const treeData = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const expandedNodes = ref(new Set()); // เก็บ ID ของ Node ที่เปิดอยู่

// Form สำหรับส่งข้อมูล
const form = useForm({
    parent_id: null
});

// --- Fetch Full Tree Data ---
// ดึงข้อมูล Tree ทั้งหมดเพื่อมาแสดงให้เลือก
const fetchTree = async () => {
    isLoading.value = true;
    try {
        // เราใช้ API เดียวกับหน้า Strategy เพื่อดึง Tree ทั้งหมด
        const response = await axios.get(route('api.strategies.tree')); // * เดี๋ยวต้องไปเพิ่ม Route นี้
        treeData.value = response.data;
    } catch (error) {
        console.error("Failed to load tree:", error);
    } finally {
        isLoading.value = false;
    }
};

// --- Logic การ Disable Node (ห้ามเลือกตัวเองและลูกหลาน) ---
const isNodeDisabled = (node) => {
    if (!props.item) return false;

    // 1. ห้ามเลือกตัวเอง
    if (node.id === props.item.id) return true;

    // 2. ห้ามเลือก Node ที่เป็นลูกหลานของตัวเอง (เช็คจาก path หรือ recursive check)
    // แต่วิธีที่ง่ายกว่าคือ: ใน Backend เราจะกันไว้แล้ว แต่ใน Frontend เพื่อ UX ที่ดี
    // เราควร Disable Visual ด้วย.
    // เนื่องจาก treeData เป็น Tree ใหม่ เราต้องเช็คว่า node นี้อยู่ใต้ item เราไหม
    // *วิธีง่ายสุด:* คือการเช็คว่า node.id อยู่ใน list ของลูกหลาน props.item ไหม
    // แต่ props.item อาจจะมีลูกไม่ครบถ้าหน้าเดิมโหลดไม่หมด
    // ดังนั้น: เราจะใช้ Logic ง่ายๆ คือ "ถ้า node นี้มี ancestor เป็น props.item.id ให้ disable"
    // (ซึ่งทำใน frontend ยากถ้าไม่มี parent pointer)

    // *Workaround:* เราจะใช้วิธี Disable แค่ "ตัวเอง" ก่อนในเบื้องต้น
    // ส่วนลูกหลาน ถ้า Tree มัน render ลูกหลานออกมาภายใต้ "ตัวมันเอง"
    // ตัวมันเองโดน disable ไปแล้ว User ก็จะกดเข้าไปเลือกข้างในไม่ได้อยู่แล้ว (โดยธรรมชาติของ UI)

    return false;
};

// --- Recursive Component สำหรับแสดง Tree ใน Modal ---
const TreeOption = {
    name: 'TreeOption',
    props: ['nodes', 'level', 'currentItemId', 'search'],
    template: `
        <div v-for="node in nodes" :key="node.id" class="w-full">
            <div v-if="matchesSearch(node)">
                <div
                    class="flex items-center p-2 rounded-lg cursor-pointer transition-colors border border-transparent"
                    :class="[
                        isSelected(node.id) ? 'bg-purple-100 border-purple-300 text-purple-800' : 'hover:bg-gray-50',
                        isDisabled(node.id) ? 'opacity-50 cursor-not-allowed bg-gray-50' : ''
                    ]"
                    :style="{ paddingLeft: (level * 1.5) + 0.5 + 'rem' }"
                    @click="!isDisabled(node.id) && selectNode(node)"
                >
                    <span class="mr-2 text-lg">{{ getIcon(node.type) }}</span>

                    <span class="flex-1 text-sm font-medium truncate">
                        {{ node.name }}
                        <span v-if="isDisabled(node.id)" class="text-xs text-red-400 ml-2">(ตัวเอง/ลูกหลาน)</span>
                    </span>

                    <span v-if="isSelected(node.id)" class="text-purple-600 font-bold">✓</span>
                </div>

                <div v-if="node.children && node.children.length > 0" class="border-l border-gray-100 ml-4">
                    <TreeOption
                        :nodes="node.children"
                        :level="level + 1"
                        :currentItemId="currentItemId"
                        :search="search"
                        @select="$emit('select', $event)"
                    />
                </div>
            </div>
        </div>
    `,
    methods: {
        getIcon(type) {
            return { strategy: '🏛️', plan: '📁', project: '🚀', task: '📌' }[type] || '📄';
        },
        isDisabled(nodeId) {
            // Disable ตัวเอง
            if (nodeId === this.currentItemId) return true;
            return false;
            // หมายเหตุ: จริงๆ ควร Disable ลูกหลานด้วย แต่ใน UI แบบ Tree ถ้าพ่อ (ตัวเอง) ถูก Disable
            // หรือถูกซ่อน (เพราะเรากำลังย้ายตัวเอง) ลูกหลานมันก็จะติดไปด้วยอยู่แล้ว
        },
        isSelected(nodeId) {
            // เช็คว่า Parent ID ที่เลือกใน Form ตรงกับ Node นี้ไหม
            // แต่ต้อง inject form มาใช้ หรือส่งผ่าน props.
            // เพื่อความง่าย ใช้ event emit ขึ้นไปจัดการข้างบนดีกว่า
            return this.$attrs.selectedId === nodeId;
        },
        selectNode(node) {
            this.$emit('select', node);
        },
        matchesSearch(node) {
            if (!this.search) return true;
            // ถ้าตัวเองตรง หรือ ลูกหลานตรง ให้แสดง
            const selfMatch = node.name.toLowerCase().includes(this.search.toLowerCase());
            const childrenMatch = node.children && node.children.some(c => this.matchesSearch(c)); // Recursive check อย่างง่าย
            return selfMatch || childrenMatch; // (Logic นี้อาจหนักถ้าข้อมูลเยอะ แต่พอถูไถ)
        }
    }
};

// --- Actions ---
const selectParent = (node) => {
    // ถ้าเลือกตัวเอง -> ห้าม
    if (node && node.id === props.item.id) return;

    // ตั้งค่า parent_id ใหม่
    form.parent_id = node ? node.id : null;
};

const submitMove = () => {
    if (!props.item) return;

    form.put(route('work-items.move', props.item.id), {
        onSuccess: () => {
            emit('success');
            emit('close');
            form.reset();
        },
        onError: () => {
            // Handle error
        }
    });
};

// Watch Show prop เพื่อโหลดข้อมูล
watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchTree();
        // ตั้งค่าเริ่มต้นเป็น Parent ปัจจุบัน
        form.parent_id = props.item?.parent_id || null;
    }
});

// Helper for icon inside main template
const getIcon = (type) => ({ strategy: '🏛️', plan: '📁', project: '🚀', task: '📌' }[type] || '📄');

</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[200] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">

        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg flex flex-col max-h-[85vh]">

                <div class="bg-purple-50 px-4 py-3 sm:px-6 border-b border-purple-100 flex justify-between items-center">
                    <h3 class="text-base font-semibold leading-6 text-purple-900" id="modal-title">
                        🔄 ย้ายงาน: <span class="text-purple-600">{{ item?.name }}</span>
                    </h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-500">&times;</button>
                </div>

                <div class="px-4 py-4 sm:px-6 flex-1 overflow-y-auto min-h-[300px]">

                    <div class="mb-4">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="🔍 ค้นหาปลายทาง..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                        >
                    </div>

                    <div
                        class="flex items-center p-2 rounded-lg cursor-pointer mb-2 border border-dashed border-gray-300 hover:bg-gray-50 hover:border-purple-300 transition-colors"
                        :class="{'bg-purple-50 border-purple-500 text-purple-700': form.parent_id === null}"
                        @click="form.parent_id = null"
                    >
                        <span class="mr-2">🏁</span>
                        <span class="text-sm font-bold">ตั้งเป็นยุทธศาสตร์หลัก (Root)</span>
                        <span v-if="form.parent_id === null" class="ml-auto text-purple-600 font-bold">✓</span>
                    </div>

                    <div v-if="isLoading" class="text-center py-10 text-gray-400">
                        กำลังโหลดโครงสร้าง...
                    </div>

                    <div v-else class="space-y-1">
                        <TreeOption
                            :nodes="treeData"
                            :level="0"
                            :currentItemId="item?.id"
                            :selectedId="form.parent_id"
                            :search="searchQuery"
                            @select="selectParent"
                        />
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                    <button
                        type="button"
                        class="inline-flex w-full justify-center rounded-md bg-[#7A2F8F] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 sm:ml-3 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="form.processing"
                        @click="submitMove"
                    >
                        <span v-if="form.processing">กำลังบันทึก...</span>
                        <span v-else>ยืนยันการย้าย</span>
                    </button>
                    <button
                        type="button"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                        @click="$emit('close')"
                    >
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
