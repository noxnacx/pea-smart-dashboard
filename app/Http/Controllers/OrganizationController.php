<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Department;
use App\Models\AuditLog; // ✅ Import AuditLog
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    // แสดงหน้ารายการ (Search + Pagination)
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = Division::with('departments');

        // ✅ Logic การค้นหา (หาทั้งชื่อกอง และ ชื่อแผนกที่อยู่ข้างใน)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%") // Postgres ใช้ ilike (Case Insensitive)
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhereHas('departments', function($d) use ($search) {
                      $d->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                  });
            });
        }

        // ✅ Pagination: 10 รายการต่อหน้า
        $divisions = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Organization/Index', [
            'divisions' => $divisions,
            'filters' => $request->only(['search']) // ส่งคำค้นกลับไปที่หน้า View
        ]);
    }

    // --- จัดการ กอง (Division) ---
    public function storeDivision(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50']);

        $division = Division::create($request->all());

        // ✅ เก็บ Log
        $this->logAction('CREATE', 'Division', $division->id, $division->name, $division->toArray());

        return back()->with('success', 'เพิ่มกองสำเร็จ');
    }

    public function updateDivision(Request $request, Division $division)
    {
        $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50']);

        $oldData = $division->toArray();
        $division->update($request->all());

        // ✅ เก็บ Log
        $this->logAction('UPDATE', 'Division', $division->id, $division->name, [
            'before' => $oldData,
            'after' => $division->toArray()
        ]);

        return back()->with('success', 'แก้ไขกองสำเร็จ');
    }

    public function destroyDivision(Division $division)
    {
        $name = $division->name;
        $id = $division->id;
        $division->delete();

        // ✅ เก็บ Log
        $this->logAction('DELETE', 'Division', $id, $name, ['note' => 'Deleted division and its departments']);

        return back()->with('success', 'ลบกองสำเร็จ');
    }

    // --- จัดการ แผนก (Department) ---
    public function storeDepartment(Request $request)
    {
        $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50'
        ]);

        $department = Department::create($request->all());

        // ✅ เก็บ Log
        $this->logAction('CREATE', 'Department', $department->id, $department->name, $department->toArray());

        return back()->with('success', 'เพิ่มแผนกสำเร็จ');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50']);

        $oldData = $department->toArray();
        $department->update($request->all());

        // ✅ เก็บ Log
        $this->logAction('UPDATE', 'Department', $department->id, $department->name, [
            'before' => $oldData,
            'after' => $department->toArray()
        ]);

        return back()->with('success', 'แก้ไขแผนกสำเร็จ');
    }

    public function destroyDepartment(Department $department)
    {
        $name = $department->name;
        $id = $department->id;
        $department->delete();

        // ✅ เก็บ Log
        $this->logAction('DELETE', 'Department', $id, $name, ['note' => 'Deleted department']);

        return back()->with('success', 'ลบแผนกสำเร็จ');
    }

    // --- Helper Function สำหรับบันทึก Log ---
    private function logAction($action, $modelType, $modelId, $targetName, $changes = null)
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'target_name' => $targetName,
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}
