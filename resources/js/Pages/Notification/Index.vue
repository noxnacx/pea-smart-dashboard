<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue'; // ✅ เปลี่ยนมาใช้ PeaSidebarLayout ของคุณ

const props = defineProps({
    notifications: Object
});

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }) + ' น.';
};

// กดอ่านทั้งหมด
const markAllAsRead = () => {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
};

// กดคลิกที่การแจ้งเตือน (พาไปหน้าเป้าหมาย)
const handleNotificationClick = (notif) => {
    if (!notif.read_at) {
        router.post(route('notifications.read', notif.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (notif.data.url) router.get(notif.data.url);
            }
        });
    } else {
        if (notif.data.url) router.get(notif.data.url);
    }
};

// 🚀 ฟังก์ชันลบการแจ้งเตือน
const deleteNotification = (id) => {
    if (confirm('คุณต้องการลบการแจ้งเตือนนี้ใช่หรือไม่?')) {
        router.delete(route('notifications.destroy', id), {
            preserveScroll: true
        });
    }
};

// ไอคอนแยกตามประเภท (ปรับสีให้เข้าธีม PEA)
const getIcon = (type) => {
    switch (type) {
        case 'assigned_pm':
            return `<svg class="w-6 h-6 text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`;
        case 'work_deleted':
            return `<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`;
        case 'issue_created':
            return `<svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
        default:
            return `<svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>`;
    }
};
</script>

<template>
    <Head title="การแจ้งเตือน" />

    <PeaSidebarLayout>
        <div class="p-6 md:p-8 max-w-5xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border-2 border-[#FDB913] shadow-sm">
                        <svg class="w-6 h-6 text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-[#4A148C]">การแจ้งเตือน (Notifications)</h1>
                        <p class="text-sm text-gray-500">ติดตามความเคลื่อนไหวและรายการงานที่อัปเดตใหม่</p>
                    </div>
                </div>

                <button v-if="props.notifications.data.some(n => !n.read_at)"
                        @click="markAllAsRead"
                        class="bg-[#FDB913] hover:bg-yellow-500 text-[#4A148C] px-5 py-2.5 rounded-lg font-bold transition-all shadow-md flex items-center gap-2 text-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    ทำเครื่องหมายอ่านแล้วทั้งหมด
                </button>
            </div>

            <div class="bg-white rounded-2xl shadow-lg border border-purple-100 overflow-hidden">
                <div v-if="props.notifications.data.length === 0" class="p-16 text-center">
                    <div class="w-24 h-24 bg-purple-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-purple-100">
                        <svg class="w-10 h-10 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-[#4A148C]">ไม่มีการแจ้งเตือน</h3>
                    <p class="text-gray-400 mt-2">คุณตามทันทุกความเคลื่อนไหวแล้ว!</p>
                </div>

                <ul v-else class="divide-y divide-gray-100">
                    <li v-for="notif in props.notifications.data" :key="notif.id"
                        @click="handleNotificationClick(notif)"
                        class="relative p-5 transition-all duration-300 hover:bg-purple-50/50 cursor-pointer group flex items-start gap-4"
                        :class="!notif.read_at ? 'bg-purple-50 border-l-4 border-l-[#FDB913]' : 'bg-white border-l-4 border-l-transparent'">

                        <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center shadow-sm"
                             :class="!notif.read_at ? 'bg-white border border-[#FDB913]/30' : 'bg-gray-50 border border-gray-100'"
                             v-html="getIcon(notif.data.type)">
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-1">
                                <h3 class="text-base font-bold truncate transition-colors group-hover:text-[#4A148C]"
                                    :class="!notif.read_at ? 'text-[#4A148C]' : 'text-gray-600'">
                                    {{ notif.data.title }}
                                </h3>
                                <span class="text-[11px] font-medium whitespace-nowrap text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">
                                    {{ formatDate(notif.created_at) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm line-clamp-2 leading-relaxed"
                               :class="!notif.read_at ? 'text-gray-700 font-medium' : 'text-gray-500'">
                                {{ notif.data.message }}
                            </p>
                        </div>

                        <button @click.stop="deleteNotification(notif.id)"
                                class="flex-shrink-0 p-2 text-gray-300 hover:text-white hover:bg-red-500 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                                title="ลบการแจ้งเตือน">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </li>
                </ul>

                <div v-if="props.notifications.links && props.notifications.links.length > 3" class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-center">
                    <div class="flex gap-1">
                        <template v-for="(link, index) in props.notifications.links" :key="index">
                            <Link v-if="link.url" :href="link.url"
                                  class="px-3 py-1 rounded-md text-sm transition-colors border font-medium"
                                  :class="link.active ? 'bg-[#4A148C] text-white border-[#4A148C]' : 'bg-white text-[#4A148C] border-gray-200 hover:bg-purple-50'"
                                  v-html="link.label"></Link>
                            <span v-else class="px-3 py-1 text-sm text-gray-400 border border-transparent" v-html="link.label"></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </PeaSidebarLayout>
</template>
