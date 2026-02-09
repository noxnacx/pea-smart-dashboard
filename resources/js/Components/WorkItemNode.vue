<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

defineOptions({ name: 'WorkItemNode' });

const props = defineProps({
    item: Object,
    level: { type: Number, default: 0 },
    isLast: { type: Boolean, default: false }
});

const isOpen = ref(false);
const hasChildren = computed(() => props.item.children && props.item.children.length > 0);
const isCancelled = computed(() => props.item.status === 'cancelled'); // ✅ เช็คสถานะยกเลิก

// สร้างข้อความสรุป
const childSummary = computed(() => {
    if (!hasChildren.value) return '';
    const counts = props.item.children.reduce((acc, child) => {
        const typeLabel = { 'plan': 'แผนงาน', 'project': 'โครงการ', 'task': 'Task', 'strategy': 'ยุทธศาสตร์' }[child.type] || 'รายการ';
        acc[typeLabel] = (acc[typeLabel] || 0) + 1;
        return acc;
    }, {});
    return Object.entries(counts).map(([label, count]) => `${count} ${label}`).join(' / ');
});

// Helper สีและไอคอน
const meta = computed(() => {
    // ✅ ถ้าถูกยกเลิก ให้ Override สีทุกอย่างเป็นสีเทา
    if (isCancelled.value) {
        let icon = '📄';
        switch (props.item.type) {
            case 'strategy': icon = '🏛️'; break;
            case 'plan': icon = '📁'; break;
            case 'project': icon = '🚀'; break;
            case 'task': icon = '📌'; break;
        }
        return {
            label: props.item.type, // หรือจะเขียนว่า 'ยกเลิก' ก็ได้ แต่คงประเภทไว้ดีกว่า
            bg: 'bg-gray-100 opacity-75', // พื้นหลังจางๆ
            border: 'border-l-4 border-gray-300', // ขอบสีเทา
            icon: icon, // ไอคอนเดิม (แต่จะถูกทำให้จางด้วย opacity)
            bar: 'bg-gray-300', // หลอดเทา
            badge: 'bg-gray-200 text-gray-500 border border-gray-300', // ป้ายเทา
            text: 'text-gray-500 decoration-gray-400' // สีตัวอักษรเทา
        };
    }

    // สีปกติ (Active)
    switch (props.item.type) {
        case 'strategy': return { label: 'ยุทธศาสตร์', bg: 'bg-purple-50', border: 'border-l-4 border-purple-600', icon: '🏛️', bar: 'bg-purple-600', badge: 'bg-[#4A148C] text-white', text: 'text-gray-800' };
        case 'plan': return { label: 'แผนงาน', bg: 'bg-yellow-50', border: 'border-l-4 border-yellow-400', icon: '📁', bar: 'bg-yellow-400', badge: 'bg-yellow-100 text-yellow-700 border border-yellow-200', text: 'text-gray-800' };
        case 'project': return { label: 'โครงการ', bg: 'bg-blue-50', border: 'border-l-4 border-blue-500', icon: '🚀', bar: 'bg-blue-500', badge: 'bg-blue-50 text-blue-600 border border-blue-200', text: 'text-gray-800' };
        case 'task': return { label: 'Task', bg: 'bg-green-50', border: 'border-l-4 border-green-500', icon: '📌', bar: 'bg-green-500', badge: 'bg-green-50 text-green-600 border border-green-200', text: 'text-gray-800' };
        default: return { label: props.item.type, bg: 'bg-gray-50', border: 'border-l-4 border-gray-400', icon: '📄', bar: 'bg-gray-400', badge: 'bg-gray-100 text-gray-600', text: 'text-gray-800' };
    }
});

const toggle = () => { if (hasChildren.value) isOpen.value = !isOpen.value; };
</script>

<template>
    <div class="relative w-full">

        <div v-if="level > 0"
             class="absolute border-l-2 border-gray-200"
             :class="isLast ? 'h-8' : 'h-full'"
             :style="{ left: '-1.4rem', top: '0' }">
        </div>

        <div v-if="level > 0"
             class="absolute w-4 border-b-2 border-gray-200"
             :style="{ left: '-1.4rem', top: '1.75rem' }">
        </div>

        <div class="relative mb-3 transition-all duration-300">
            <div
                class="rounded-lg shadow-sm border border-gray-100 flex items-center p-3 gap-3 cursor-pointer transition-all"
                :class="[
                    meta.border,
                    meta.bg,
                    isOpen ? 'ring-1 ring-gray-200' : '',
                    isCancelled ? 'grayscale-[0.5]' : 'hover:shadow-md' // ถ้า Cancelled ให้หม่นลง
                ]"
                @click="toggle"
            >
                <button
                    class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors shrink-0"
                    :class="{'invisible': !hasChildren}"
                >
                    <svg class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="{'rotate-90': isOpen}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <div class="text-xl select-none" :class="{'opacity-50': isCancelled}">{{ meta.icon }}</div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wide" :class="meta.badge">
                            {{ meta.label }}
                        </span>

                        <h4 class="font-bold text-sm truncate transition" :class="[meta.text, !isCancelled ? 'hover:text-purple-700' : '']">
                            <Link :href="route('work-items.show', item.id)" @click.stop>
                                {{ item.name }}
                            </Link>
                        </h4>

                        <span v-if="isCancelled" class="text-[10px] bg-gray-600 text-white px-2 py-0.5 rounded-full font-bold">
                            (ยกเลิก)
                        </span>

                        <span v-if="item.issue_count > 0 && !isCancelled" class="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold animate-pulse">
                            🔥 {{ item.issue_count }} ปัญหา
                        </span>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <div class="flex items-center gap-2 w-32">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500" :class="meta.bar" :style="`width: ${item.progress}%`"></div>
                            </div>
                            <span class="font-bold" :class="{'text-gray-400': isCancelled}">{{ item.progress }}%</span>
                        </div>
                        <span v-if="childSummary" class="hidden sm:inline-block border-l border-gray-200 pl-2">
                            มี {{ childSummary }}
                        </span>
                    </div>
                </div>

                <Link :href="route('work-items.show', item.id)" class="p-2 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition" @click.stop>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </Link>
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
                />
            </div>
        </transition>

    </div>
</template>
