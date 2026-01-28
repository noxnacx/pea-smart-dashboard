<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division; // ✅ Import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = User::with('department.division'); // ✅ Eager load department & division

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        // ✅ ดึงข้อมูล Master Data ส่งไปให้หน้าบ้านใช้ทำ Dropdown
        $divisions = Division::with('departments')->get();

        return Inertia::render('User/Index', [
            'users' => $users,
            'divisions' => $divisions, // ✅ ส่งไป view
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
            'department_id' => 'nullable|exists:departments,id', // ✅ Validate
            'is_pm' => 'boolean' // ✅ Validate
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'department_id' => $request->department_id, // ✅ Save
            'is_pm' => $request->is_pm ?? false, // ✅ Save
            'position' => $request->position,
            'phone' => $request->phone,
        ]);

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

        return back()->with('success', 'แก้ไขผู้ใช้สำเร็จ');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'ไม่สามารถลบตัวเองได้');
        }
        $user->delete();
        return back()->with('success', 'ลบผู้ใช้สำเร็จ');
    }
}
