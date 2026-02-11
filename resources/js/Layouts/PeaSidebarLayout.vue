<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import GlobalSearchModal from '@/Components/GlobalSearchModal.vue';

const showingNavigationDropdown = ref(false);
const isSearchOpen = ref(false);

const toggleSearch = () => {
    isSearchOpen.value = !isSearchOpen.value;
};

// เช็คสิทธิ์ว่าเป็น Admin หรือ PM หรือไม่ (สำหรับโชว์เมนูงานของฉัน)
const page = usePage();
const isPmOrAdmin = computed(() => ['admin', 'pm', 'project_manager'].includes(page.props.auth.user.role));

// ✅ ปุ่มลัด Shift + S สำหรับเปิด Search
const handleKeydown = (e) => {
    if (e.shiftKey && (e.key === 'S' || e.key === 's')) {
        // ป้องกันถ้ากำลังพิมพ์ใน input หรือ textarea อยู่
        if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;

        e.preventDefault();
        isSearchOpen.value = true;
    }
    // ปิดด้วย Esc
    if (e.key === 'Escape') {
        isSearchOpen.value = false;
    }
};

// ฟังก์ชันช่วยเช็ค Route (กัน Error กรณี Route ยังไม่ได้สร้าง)
const safeRoute = (name) => {
    try { return route(name); } catch (e) { return '#'; }
};
const isRouteActive = (name) => {
    try { return route().current(name); } catch (e) { return false; }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex font-sans">

        <GlobalSearchModal :show="isSearchOpen" @close="isSearchOpen = false" />

        <aside class="w-64 bg-[#4A148C] text-white flex-shrink-0 hidden md:flex flex-col shadow-2xl z-20">

            <div class="h-20 flex items-center px-6 bg-[#380d6b] border-b border-purple-800/50 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-purple-500 opacity-10 rounded-full -mr-8 -mt-8"></div>

                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border-2 border-[#FDB913] mr-3 shadow-lg z-10 p-1 overflow-hidden">
                    <img src="/images/logo.jpg" alt="PEA Logo" class="w-full h-full object-contain" />
                </div>

                <div class="z-10">
                    <h1 class="font-bold text-lg tracking-wide leading-none text-[#FDB913]">PEA SMART</h1>
                    <span class="text-[10px] text-purple-300 font-medium tracking-wider">DASHBOARD</span>
                </div>
            </div>

            <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto custom-scrollbar">

                <div class="px-4 mt-2 mb-2 text-[10px] font-bold text-purple-300/60 uppercase tracking-widest">
                    General
                </div>

                <Link :href="route('dashboard')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group relative overflow-hidden"
                      :class="route().current('dashboard') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    ภาพรวม (Dashboard)
                </Link>

                <Link :href="route('calendar.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('calendar.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    ปฏิทินรวมงาน
                </Link>

                <Link v-if="isPmOrAdmin"
                      :href="safeRoute('my-works.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="isRouteActive('my-works.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    งานของฉัน (My Works)
                </Link>

                <div class="px-4 mt-6 mb-2 text-[10px] font-bold text-purple-300/60 uppercase tracking-widest">
                    Work Management
                </div>

                <Link :href="route('strategies.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('strategies.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    ยุทธศาสตร์ทั้งหมด
                </Link>

                <Link :href="route('plans.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('plans.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    แผนงานทั้งหมด
                </Link>

                <Link :href="route('projects.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('projects.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    โครงการทั้งหมด
                </Link>

                <Link :href="route('tasks.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('tasks.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    งานย่อยทั้งหมด
                </Link>

                <Link :href="route('issues.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('issues.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    ปัญหาและความเสี่ยง
                </Link>

                <div class="px-4 mt-6 mb-2 text-[10px] font-bold text-purple-300/60 uppercase tracking-widest">
                    System
                </div>

                <button @click="isSearchOpen = true"
                        type="button"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group text-purple-100 hover:bg-purple-800/50 hover:text-white cursor-pointer border border-transparent hover:border-purple-700/50">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <span>ค้นหาอัจฉริยะ</span>
                    </div>
                    <span class="text-[10px] bg-purple-900/50 border border-purple-700 px-1.5 py-0.5 rounded text-purple-300 group-hover:text-white transition">Shift S</span>
                </button>

                <Link :href="route('reports.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('reports.*') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    รายงาน (Reports)
                </Link>

                <Link v-if="$page.props.auth.user.role === 'admin'"
                      :href="route('organization.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('organization.*') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    จัดการโครงสร้างองค์กร
                </Link>

                <Link :href="route('pm.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('pm.*') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    ทำเนียบ PM
                </Link>

                <Link v-if="$page.props.auth.user.role === 'admin'"
                      :href="route('users.index')"
                      class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                      :class="route().current('users.*') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    จัดการผู้ใช้ (Users)
                </Link>

                <Link v-if="$page.props.auth.user.role === 'admin'"
                      :href="route('audit-logs.index')"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 font-medium group"
                    :class="route().current('audit-logs.index') ? 'bg-[#FDB913] text-[#4A148C] shadow-lg translate-x-1' : 'text-purple-100 hover:bg-purple-800/50 hover:text-white hover:translate-x-1'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Audit Logs
                </Link>
            </nav>

            <div class="p-4 border-t border-purple-800/50 bg-[#380d6b] hover:bg-[#2d0a56] transition-colors cursor-pointer">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white font-bold shadow-md border-2 border-purple-700">
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-white truncate">{{ $page.props.auth.user.name }}</p>
                        <p class="text-[10px] text-purple-300 truncate uppercase tracking-wide font-semibold">{{ $page.props.auth.user.role || 'Staff' }}</p>
                    </div>
                    <Link :href="route('logout')" method="post" as="button" class="text-purple-300 hover:text-[#FDB913] transition-colors p-2 hover:bg-white/10 rounded-full">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
             <div class="md:hidden bg-[#4A148C] text-white p-4 flex justify-between items-center shadow-lg sticky top-0 z-30">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center border border-[#FDB913] p-1 overflow-hidden">
                        <img src="/images/logo.jpg" alt="PEA Logo" class="w-full h-full object-contain" />
                    </div>
                    <span class="font-bold tracking-wide">PEA SMART</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="isSearchOpen = true" class="text-white hover:text-[#FDB913] transition p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                    <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="text-white hover:text-[#FDB913] transition p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>

            <div v-if="showingNavigationDropdown" class="md:hidden bg-[#380d6b] text-white border-b border-purple-800 shadow-xl absolute w-full z-40 top-[60px]">
                 <nav class="px-4 py-4 space-y-2">
                    <Link :href="route('dashboard')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">Dashboard</Link>
                    <Link :href="route('calendar.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">ปฏิทินรวมงาน</Link>

                    <Link v-if="isPmOrAdmin" :href="safeRoute('my-works.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">งานของฉัน (My Works)</Link>

                    <div class="px-4 text-[10px] text-purple-400 font-bold uppercase mt-2">Work Management</div>
                    <Link :href="route('strategies.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">ยุทธศาสตร์ทั้งหมด</Link>
                    <Link :href="route('plans.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">แผนงานทั้งหมด</Link>
                    <Link :href="route('projects.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">โครงการทั้งหมด</Link>
                    <Link :href="route('tasks.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">งานย่อยทั้งหมด</Link>
                    <Link :href="route('issues.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">ปัญหาและความเสี่ยง</Link>

                    <div class="px-4 text-[10px] text-purple-400 font-bold uppercase mt-2">System</div>
                    <button @click="isSearchOpen = true" class="w-full text-left flex items-center gap-3 px-4 py-3 hover:bg-purple-800 rounded-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        ค้นหาอัจฉริยะ
                    </button>

                    <Link :href="route('reports.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">รายงาน</Link>

                    <Link v-if="$page.props.auth.user.role === 'admin'" :href="route('organization.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">จัดการโครงสร้างองค์กร</Link>

                    <Link :href="route('pm.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">ทำเนียบ PM</Link>

                    <Link v-if="$page.props.auth.user.role === 'admin'" :href="route('users.index')" class="block px-4 py-3 hover:bg-purple-800 rounded-lg">ผู้ใช้งาน</Link>

                    <div class="border-t border-purple-800 mt-2 pt-2">
                         <Link :href="route('logout')" method="post" as="button" class="block w-full text-left px-4 py-3 text-red-300 hover:text-red-100">ออกจากระบบ</Link>
                    </div>
                 </nav>
            </div>

            <div class="flex-1 overflow-auto bg-gray-50 scroll-smooth">
                <slot />
            </div>
        </main>
    </div>
</template>
