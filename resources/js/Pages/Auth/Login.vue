<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-[#4A148C]/5 via-white to-[#FDB913]/10">
        <Head title="เข้าสู่ระบบ" />

        <div class="w-full sm:max-w-md mt-6 px-8 py-10 bg-white shadow-2xl rounded-[2rem] border-t-8 border-[#FDB913] relative overflow-hidden">
            <div class="absolute top-0 left-0 -mt-20 -ml-20 w-40 h-40 bg-[#7A2F8F]/5 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 -mb-20 -mr-20 w-40 h-40 bg-[#FDB913]/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col items-center mb-10 text-center relative z-10">
                <div class="w-32 h-32 mb-6 p-1.5 rounded-full bg-white shadow-[0_8px_30px_rgba(0,0,0,0.12)] flex items-center justify-center border-4 border-[#FDB913]">
                    <div class="w-full h-full rounded-full bg-white flex items-center justify-center overflow-hidden p-2">
                        <img src="/images/logo.jpg" alt="PEA Logo" class="w-full h-full object-contain" />
                    </div>
                </div>

                <h1 class="text-3xl font-black text-[#4A148C] tracking-tight leading-none">
                    PEA SMART
                </h1>
                <span class="text-xl font-bold text-[#FDB913] tracking-[0.2em] -mt-1 block">DASHBOARD</span>

                <div class="flex items-center gap-3 mt-5 w-full justify-center">
                    <span class="h-px w-12 bg-gradient-to-r from-transparent to-gray-300"></span>
                    <p class="text-sm font-bold text-[#7A2F8F] uppercase tracking-wider whitespace-nowrap">
                        กองกลยุทธ์ดิจิทัล
                    </p>
                    <span class="h-px w-12 bg-gradient-to-l from-transparent to-gray-300"></span>
                </div>
            </div>

            <div v-if="status" class="mb-6 text-sm font-bold text-green-700 bg-green-100 p-4 rounded-xl border-l-4 border-green-500 text-center relative z-10 shadow-sm">
                {{ status }}
            </div>

            <form @submit.prevent="submit" class="space-y-6 relative z-10">
                <div>
                    <InputLabel for="email" value="อีเมล (Email)" class="text-[#4A148C] font-extrabold text-base pl-1" />
                    <div class="relative mt-2">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 peer-focus:text-[#7A2F8F] transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                              <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
                              <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
                            </svg>
                        </span>
                        <TextInput
                            id="email"
                            type="email"
                            class="peer block w-full pl-12 py-3.5 border-2 border-gray-200 bg-gray-50/50 focus:border-[#7A2F8F] focus:ring-4 focus:ring-[#7A2F8F]/20 focus:bg-white rounded-2xl shadow-sm transition-all font-bold text-gray-700 text-lg placeholder:text-gray-400 placeholder:font-medium"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="ระบุอีเมลของคุณ..."
                        />
                    </div>
                    <InputError class="mt-2 pl-1 font-bold" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="รหัสผ่าน (Password)" class="text-[#4A148C] font-extrabold text-base pl-1" />
                    <div class="relative mt-2">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 peer-focus:text-[#7A2F8F] transition-colors">
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                              <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <TextInput
                            id="password"
                            type="password"
                            class="peer block w-full pl-12 py-3.5 border-2 border-gray-200 bg-gray-50/50 focus:border-[#7A2F8F] focus:ring-4 focus:ring-[#7A2F8F]/20 focus:bg-white rounded-2xl shadow-sm transition-all font-bold text-gray-700 text-lg placeholder:text-gray-400 placeholder:font-medium"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                            placeholder="ระบุรหัสผ่าน..."
                        />
                    </div>
                    <InputError class="mt-2 pl-1 font-bold" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between py-2">
                    <label class="flex items-center cursor-pointer group">
                        <div class="relative">
                            <Checkbox name="remember" v-model:checked="form.remember" class="peer sr-only" />
                            <div class="w-6 h-6 bg-gray-100 border-2 border-gray-300 rounded-lg peer-checked:bg-[#7A2F8F] peer-checked:border-[#7A2F8F] peer-focus:ring-4 peer-focus:ring-[#7A2F8F]/30 transition-all flex items-center justify-center">
                                <svg class="w-4 h-4 text-white scale-0 peer-checked:scale-100 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        <span class="ms-3 text-sm text-gray-600 group-hover:text-[#4A148C] transition font-bold select-none">จดจำฉันไว้ในระบบ</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm font-bold text-[#7A2F8F] hover:text-[#FDB913] transition underline-offset-4 hover:underline"
                    >
                        ลืมรหัสผ่าน?
                    </Link>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        class="w-full flex justify-center items-center py-4 bg-gradient-to-r from-[#4A148C] to-[#7A2F8F] hover:from-[#3a0f6e] hover:to-[#5e2270] text-white font-extrabold text-xl rounded-2xl shadow-[0_12px_25px_-10px_rgba(122,47,143,0.6)] border-b-[6px] border-[#320b5e] active:border-b-0 active:translate-y-[6px] active:shadow-none transition-all relative overflow-hidden group"
                        :class="{ 'opacity-80 cursor-not-allowed': form.processing }"
                        :disabled="form.processing"
                    >
                        <span class="relative z-10 flex items-center gap-3">
                            <svg v-if="!form.processing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-7 h-7 group-hover:translate-x-1 transition-transform">
                              <path fill-rule="evenodd" d="M7.5 3.75A1.5 1.5 0 006 5.25v13.5a1.5 1.5 0 001.5 1.5h6a1.5 1.5 0 001.5-1.5V15a.75.75 0 011.5 0v3.75a3 3 0 01-3 3h-6a3 3 0 01-3-3V5.25a3 3 0 013-3h6a3 3 0 013 3V9A.75.75 0 0115 9V5.25a1.5 1.5 0 00-1.5-1.5h-6zm10.72 4.72a.75.75 0 011.06 0l3 3a.75.75 0 010 1.06l-3 3a.75.75 0 11-1.06-1.06l1.72-1.72H9a.75.75 0 010-1.5h10.94l-1.72-1.72a.75.75 0 010-1.06z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else class="animate-spin h-7 w-7 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ form.processing ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
                        </span>
                        <div class="absolute inset-0 h-full w-full bg-white/20 skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-700 ease-in-out"></div>
                    </button>
                </div>
            </form>

            <div class="mt-10 text-center relative z-10">
                <p class="text-xs text-gray-400 font-bold tracking-wide">
                    © {{ new Date().getFullYear() }} การไฟฟ้าส่วนภูมิภาค (PEA).<br>All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>
