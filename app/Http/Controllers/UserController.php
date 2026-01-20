<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        // ดึงข้อมูลผู้ใช้ทั้งหมด เรียงจากใหม่ไปเก่า
        $users = User::orderBy('created_at', 'desc')->get();

        return Inertia::render('User/Index', [
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|string|in:admin,pm,staff', // บังคับเลือก Role
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->back()->with('success', 'สร้างผู้ใช้สำเร็จ');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|in:admin,pm,staff',
        ]);

        // ถ้ามีการกรอกรหัสผ่านใหม่ ให้เปลี่ยนด้วย
        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $user->password = Hash::make($request->password);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        // ถ้ารหัสเปลี่ยน ก็เซฟรหัสใหม่
        if ($request->filled('password')) {
            $user->save();
        }

        return redirect()->back()->with('success', 'อัปเดตข้อมูลสำเร็จ');
    }

    public function destroy(User $user)
    {
        // ป้องกันไม่ให้ลบตัวเอง
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'คุณไม่สามารถลบบัญชีตัวเองได้');
        }

        $user->delete();
        return redirect()->back()->with('success', 'ลบผู้ใช้สำเร็จ');
    }
}
