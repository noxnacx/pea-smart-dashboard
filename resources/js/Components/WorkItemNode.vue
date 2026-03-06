<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

defineOptions({ name: 'WorkItemNode' });

const props = defineProps({
    item: Object,
    level: { type: Number, default: 0 },
    isLast: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    workItemTypes: { type: Array, default: () => [] } // ✅ เพิ่มตัวรับค่าข้อมูลสีและอิโมจิทั้งหมด
});

const emit = defineEmits(['request-move']);

const isOpen = ref(false);
const hasChildren = computed(() => props.item.children && props.item.children.length > 0);
const isCancelled = computed(() => props.item.status === 'cancelled');

// ✅ ฟังก์ชันแปลสถานะเป็นภาษาไทยสำหรับแสดงผล (Phase 3)
const getStatusText = (status) => ({
    completed: 'เสร็จสมบูรณ์',
    delayed: 'ล่าช้า',
    in_active: 'รอเริ่มดำเนินการ',
    in_progress: 'กำลังดำเนินการ',
    cancelled: 'ยกเลิก'
}[status] || status);

// 🚀 1. สรุปรายการงานลูกแบบ Dynamic
const childSummary = computed(() => {
    if (!hasChildren.value) return '';
    const counts = props.item.children.reduce((acc, child) => {
        // ✅ ค้นหาชื่อประเภทจาก Array
        const childTypeInfo = props.workItemTypes.find(t => t.key === child.type);
        const typeLabel = childTypeInfo ? childTypeInfo.name : child.type;
        acc[typeLabel] = (acc[typeLabel] || 0) + 1;
        return acc;
    }, {});
    return Object.entries(counts).map(([label, count]) => `${count} ${label}`).join(' / ');
});

// 🚀 2. จัดการหน้าตาและสีสันแบบ Dynamic 100%
const meta = computed(() => {
    // ✅ จับคู่ข้อมูลจาก workItemTypes ด้วย key (ชัวร์สุด 100%)
    const typeInfo = props.workItemTypes.find(t => t.key === props.item.type)
                  || props.item.work_type
                  || props.item.workType;

    const typeName = typeInfo ? typeInfo.name : props.item.type;
    const dynamicColor = typeInfo ? typeInfo.color_code : null;
    const dynamicIcon = typeInfo ? typeInfo.icon : null;

    // Fallback เผื่อไม่มีการตั้งค่า
    let icon = '📄';
    let fallbackColor = '#9CA3AF'; // สีเทา
    let defaultBadgeClass = 'bg-gray-100 text-gray-600 border border-gray-200';
    let defaultBgClass = 'bg-gray-50';

    // Fallback สำรองตาม Key เดิม
    switch (props.item.type) {
        case 'strategy': icon = '🏛️'; fallbackColor = '#6B21A8'; defaultBgClass = 'bg-purple-50'; defaultBadgeClass = 'bg-[#4A148C] text-white'; break;
        case 'plan': icon = '📁'; fallbackColor = '#EAB308'; defaultBgClass = 'bg-yellow-50'; defaultBadgeClass = 'bg-yellow-100 text-yellow-700 border border-yellow-200'; break;
        case 'project': icon = '🚀'; fallbackColor = '#3B82F6'; defaultBgClass = 'bg-blue-50'; defaultBadgeClass = 'bg-blue-50 text-blue-600 border border-blue-200'; break;
        case 'task': icon = '📌'; fallbackColor = '#22C55E'; defaultBgClass = 'bg-green-50'; defaultBadgeClass = 'bg-green-50 text-green-600 border border-green-200'; break;
    }

    const finalColor = dynamicColor || fallbackColor;
    const finalIcon = dynamicIcon || icon;

    // ถ้ายกเลิกงาน ให้เป็นสีเทาทั้งหมด
    if (isCancelled.value) {
        return {
            label: typeName,
            icon: finalIcon,
            borderStyle: `border-left: 4px solid #D1D5DB;`,
            bgStyle: `background-color: #F3F4F6;`,
            badgeStyle: ``,
            badgeClass: 'bg-gray-200 text-gray-500 border border-gray-300',
            barStyle: `background-color: #D1D5DB;`,
            textClass: 'text-gray-500 decoration-gray-400'
        };
    }

    let computedBadgeStyle = '';
    let computedBadgeClass = defaultBadgeClass;
    let computedBgStyle = '';
    let computedBgClass = defaultBgClass;

    if (dynamicColor) {
        computedBadgeClass = '';
        computedBgClass = '';
        computedBgStyle = `background-color: ${finalColor}0D;`;

        if (props.level === 0) {
            computedBadgeStyle = `background-color: ${finalColor}; color: #ffffff; border: 1px solid ${finalColor};`;
        } else {
            computedBadgeStyle = `background-color: ${finalColor}1A; color: ${finalColor}; border: 1px solid ${finalColor}40;`;
        }
    }

    return {
        label: typeName,
        icon: finalIcon,
        borderStyle: `border-left: 4px solid ${finalColor};`,
        bgStyle: computedBgStyle,
        bgClass: computedBgClass,
        badgeStyle: computedBadgeStyle,
        badgeClass: computedBadgeClass,
        barStyle: `background-color: ${finalColor};`,
        textClass: 'text-gray-800'
    };
});

const toggle = () => { if (hasChildren.value) isOpen.value = !isOpen.value; };
</script>

<template>
    <div class="relative w-full">

        <div v-if="level > 0" class="absolute border-l-2 border-gray-200" :class="isLast ? 'h-8' : 'h-full'" :style="{ left: '-1.4rem', top: '0' }"></div>
        <div v-if="level > 0" class="absolute w-4 border-b-2 border-gray-200" :style="{ left: '-1.4rem', top: '1.75rem' }"></div>

        <div class="relative mb-3 transition-all duration-300 group">
            <div
                class="rounded-lg shadow-sm border border-gray-100 flex items-center p-3 gap-3 cursor-pointer transition-all"
                :class="[meta.bgClass, isOpen ? 'ring-1 ring-gray-200' : '', isCancelled ? 'grayscale-[0.5]' : 'hover:shadow-md']"
                :style="`${meta.borderStyle} ${meta.bgStyle}`"
                @click="toggle"
            >
                <button
                    class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-black/5 transition-colors shrink-0"
                    :class="{'invisible': !hasChildren}"
                >
                    <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="{'rotate-90': isOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>

                <div class="text-2xl select-none w-8 text-center shrink-0" :class="{'opacity-50': isCancelled}">{{ meta.icon }}</div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wide"
                              :class="meta.badgeClass" :style="meta.badgeStyle">{{ meta.label }}</span>

                        <h4 class="font-bold text-sm truncate transition hover:text-[#4A148C]" :class="[meta.textClass]">
                            <Link :href="route('work-items.show', item.id)" @click.stop>{{ item.name }}</Link>
                        </h4>

                        <span v-if="isCancelled" class="text-[10px] bg-gray-600 text-white px-2 py-0.5 rounded-full font-bold">(ยกเลิก)</span>
                        <span v-if="item.issue_count > 0 && !isCancelled" class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold animate-pulse">🔥 {{ item.issue_count }} ปัญหา</span>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <div class="flex items-center gap-2 w-32" :title="`สถานะ: ${getStatusText(item.status)}`">
                            <div class="flex-1 h-1.5 bg-gray-200/80 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500" :style="`${meta.barStyle} width: ${item.progress}%`"></div>
                            </div>
                            <span class="font-bold" :class="{'text-gray-400': isCancelled}">{{ item.progress }}%</span>
                        </div>
                        <span v-if="childSummary" class="hidden sm:inline-block border-l border-gray-200 pl-2">มี {{ childSummary }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        v-if="canManage"
                        @click.stop="$emit('request-move', item)"
                        class="p-2 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition"
                        title="ย้ายสังกัด"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </button>

                    <Link :href="route('work-items.show', item.id)" class="p-2 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition" @click.stop title="ดูรายละเอียด">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </Link>
                </div>
            </div>
        </div>

        <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="isOpen && hasChildren" class="ml-8 border-l-2 border-transparent relative">
                <WorkItemNode
                    v-for="(child, index) in item.children"
                    :key="child.id"
                    :item="child"
                    :level="level + 1"
                    :isLast="index === item.children.length - 1"
                    :can-manage="canManage"
                    :work-item-types="workItemTypes"
                    @request-move="$emit('request-move', $event)"
                />
            </div>
        </transition>

    </div>
</template>
