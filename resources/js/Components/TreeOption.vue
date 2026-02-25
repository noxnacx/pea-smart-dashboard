<script setup>
defineOptions({
  name: 'TreeOption' // จำเป็นสำหรับการเรียกตัวเองซ้ำ (Recursive)
});

const props = defineProps({
    nodes: Array,
    level: { type: Number, default: 0 },
    currentItemId: [Number, String], // ID ของตัวที่จะย้าย
    selectedId: [Number, String],    // ID ที่ถูกเลือกอยู่ (จาก Form)
    search: String,

    // ✅ เพิ่ม Prop นี้เพื่อรับค่าว่า "สายนี้/กิ่งนี้" ถูกแบนห้ามเลือกหรือไม่
    disabledBranch: { type: Boolean, default: false }
});

const emit = defineEmits(['select']);

// Helper Icons
const getIcon = (type) => ({ strategy: '🏛️', plan: '📁', project: '🚀', task: '📌' }[type] || '📄');

// Check States
const isDisabled = (nodeId) => {
    // ✅ แบนถ้า: เป็นตัวมันเอง หรือ เป็นลูกหลานของตัวมันเอง (disabledBranch ส่งมาจากแม่)
    return props.disabledBranch || nodeId === props.currentItemId;
};

const isSelected = (nodeId) => {
    return props.selectedId === nodeId;
};

// Search Logic
const matchesSearch = (node) => {
    if (!props.search) return true;

    // เช็คชื่อตัวเอง
    const selfMatch = node.name.toLowerCase().includes(props.search.toLowerCase());

    // เช็คชื่อลูกหลาน (Recursive Check)
    // ถ้าลูกหลานคนไหนตรงเงื่อนไข พ่อแม่ต้องแสดงด้วยเพื่อให้กดเข้าไปหาลูกได้
    const childrenMatch = node.children && node.children.some(c => matchesSearch(c));

    return selfMatch || childrenMatch;
};

const selectNode = (node) => {
    emit('select', node);
};
</script>

<template>
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
                    :selectedId="selectedId"
                    :search="search"
                    :disabledBranch="isDisabled(node.id)"
                    @select="selectNode"
                />
                </div>
        </div>
    </div>
</template>
