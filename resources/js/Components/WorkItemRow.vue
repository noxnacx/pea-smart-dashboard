<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    item: Object,
    level: Number,
    expandedItems: Object,
});

const emit = defineEmits(['toggle', 'create', 'edit', 'delete']);

// คำนวณระยะย่อหน้าตามลำดับชั้น
const paddingLeft = computed(() => `${props.level * 2.5}rem`);

// จัดสไตล์สีพื้นหลังตามลำดับชั้น
const rowStyle = computed(() => {
    if (props.level === 0) return 'bg-white border-l-4 border-[#7A2F8F] shadow-sm mb-2'; // แม่ (ยุทธศาสตร์)
    if (props.level === 1) return 'bg-[#F3E5F5] border-l-2 border-purple-300 border-b border-white'; // ลูกชั้น 1
    return 'bg-gray-50 border-l border-gray-100 text-xs'; // หลานๆ
});

const statusBadge = (status) => {
    const map = {
        completed: 'bg-green-100 text-green-700 border-green-200',
        delayed: 'bg-red-100 text-red-700 border-red-200',
        pending: 'bg-gray-100 text-gray-600 border-gray-200'
    };
    return map[status] || map.pending;
};
</script>

<template>
    <div class="transition-all duration-200 hover:bg-yellow-50/30 group">
        <div class="grid grid-cols-12 gap-4 px-6 py-3 items-center" :class="rowStyle">

            <div class="col-span-6 flex items-center" :style="{ paddingLeft: paddingLeft }">
                <button v-if="item.children && item.children.length > 0"
                    @click="$emit('toggle', item.id)"
                    class="mr-2 w-6 h-6 flex items-center justify-center rounded hover:bg-black/5 transition-transform text-gray-500"
                    :class="{ 'rotate-90 text-[#7A2F8F] font-bold': expandedItems[item.id] }">
                    ▶
                </button>
                <span v-else class="mr-8"></span>

                <div class="flex flex-col">
                    <span v-if="level === 0" class="text-[10px] font-bold text-[#7A2F8F] tracking-wider mb-0.5">STRATEGY</span>

                    <Link :href="route('work-items.show', item.id)"
                          class="font-medium text-gray-800 hover:text-[#7A2F8F] hover:underline transition-colors cursor-pointer"
                          :class="{'text-lg font-bold text-[#4A148C]': level === 0}">
                        <span v-if="level > 1" class="mr-2 text-gray-400">•</span>
                        {{ item.name }}
                    </Link>
                </div>
            </div>

            <div class="col-span-2 text-center">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wide"
                      :class="statusBadge(item.status)">
                    {{ item.status }}
                </span>
            </div>

            <div class="col-span-2 px-2">
                <div class="flex justify-between text-[10px] mb-1 text-gray-500 font-semibold">
                    <span>Progress</span>
                    <span>{{ item.progress }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500"
                         :class="level === 0 ? 'bg-[#FDB913]' : 'bg-[#7A2F8F]'"
                         :style="`width: ${item.progress}%`"></div>
                </div>
            </div>

            <div class="col-span-2 flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                <button v-if="level < 3" @click="$emit('create', item.id)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Add Sub-item">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button @click="$emit('edit', item)" class="p-1.5 text-gray-500 hover:text-[#7A2F8F] hover:bg-purple-50 rounded" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button @click="$emit('delete', item.id)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded" title="Delete">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        </div>

        <div v-if="expandedItems[item.id] && item.children">
            <WorkItemRow
                v-for="child in item.children"
                :key="child.id"
                :item="child"
                :level="level + 1"
                :expandedItems="expandedItems"
                @toggle="$emit('toggle', $event)"
                @create="$emit('create', $event)"
                @edit="$emit('edit', $event)"
                @delete="$emit('delete', $event)"
            />
        </div>
    </div>
</template>
