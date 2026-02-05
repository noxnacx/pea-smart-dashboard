<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $page = $request->input('page', 1);

        // สร้าง Cache Key (แยกตามคำค้นหาและหน้า)
        $cacheKey = "users_list_search_{$search}_page_{$page}";

        // 🚀 CACHE LOGIC: ใช้ Tags 'users' เพื่อให้สั่งล้างได้ง่ายๆ เวลาข้อมูลเปลี่ยน
        // เก็บ Cache นาน 5 นาที (300 วินาที)
        $users = Cache::tags(['users'])->remember($cacheKey, 300, function () use ($search) {
            $query = User::with('department.division'); // Eager load

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                });
            }

            return $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        });

        // 🚀 CACHE MASTER DATA: เก็บข้อมูลกอง/แผนก 24 ชั่วโมง (86400 วิ)
        $divisions = Cache::remember('master_divisions_with_depts', 86400, function() {
            return Division::with('departments')->get();
        });

        return Inertia::render('User/Index', [
            'users' => $users,
            'divisions' => $divisions,
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:4',
            'role' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
            'is_pm' => 'boolean'
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'department_id' => $request->department_id,
            'is_pm' => $request->is_pm ?? false,
            'position' => $request->position,
            'phone' => $request->phone,
        ]);

        // 🧹 Clear Cache ทันทีที่มีการเพิ่มข้อมูล
        Cache::tags(['users'])->flush();

        return back()->with('success', 'เพิ่มผู้ใช้สำเร็จ');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
            'is_pm' => 'boolean'
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'department_id' => $request->department_id,
            'is_pm' => $request->is_pm ?? false,
            'position' => $request->position,
            'phone' => $request->phone,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // 🧹 Clear Cache ทันทีที่มีการแก้ไข
        Cache::tags(['users'])->flush();

        return back()->with('success', 'แก้ไขผู้ใช้สำเร็จ');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'ไม่สามารถลบตัวเองได้');
        }

        $user->delete();

        // 🧹 Clear Cache ทันทีที่มีการลบ
        Cache::tags(['users'])->flush();

        return back()->with('success', 'ลบผู้ใช้สำเร็จ');
    }
}
