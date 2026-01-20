<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">

        <aside class="w-64 bg-[#4A148C] text-white flex-shrink-0 hidden md:flex flex-col shadow-xl z-20">
            <div class="h-20 flex items-center px-6 bg-[#380d6b] border-b border-purple-800">
                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-[#4A148C] font-bold border-2 border-[#FDB913] mr-3">
                    P
                </div>
                <div>
                    <h1 class="font-bold text-lg tracking-wide leading-none">PEA SMART</h1>
                    <span class="text-[10px] text-purple-300">Dashboard System</span>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2">
                <Link :href="route('dashboard')"
                      class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors font-medium"
                      :class="route().current('dashboard') ? 'bg-[#FDB913] text-[#4A148C] shadow-md' : 'text-purple-100 hover:bg-purple-800'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    ภาพรวม (Dashboard)
                </Link>

                <Link href="#"
                      class="flex items-center gap-3 px-4 py-3 rounded-lg text-purple-100 hover:bg-purple-800 transition-colors font-medium opacity-70 cursor-not-allowed" title="Coming Soon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    รายงาน (Reports)
                </Link>

                <Link :href="route('users.index')"
                    class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors font-medium"
                    :class="route().current('users.*') ? 'bg-[#FDB913] text-[#4A148C] shadow-md' : 'text-purple-100 hover:bg-purple-800'">
                    จัดการผู้ใช้ (Users)
                </Link>
            </nav>

            <div class="p-4 border-t border-purple-800 bg-[#380d6b]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold">
                        U
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">
                            {{ $page.props.auth.user.name }}
                        </p>
                        <p class="text-xs text-purple-300 truncate">
                            {{ $page.props.auth.user.email }}
                        </p>
                    </div>
                    <Link :href="route('logout')" method="post" as="button" class="text-purple-300 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </Link>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <div class="md:hidden bg-[#4A148C] text-white p-4 flex justify-between items-center shadow-md">
                <span class="font-bold">PEA SMART</span>
                <button @click="showingNavigationDropdown = !showingNavigationDropdown">☰</button>
            </div>

            <div class="flex-1 overflow-auto bg-gray-50">
                <slot />
            </div>
        </main>
    </div>
</template>
