<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Department;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache; // ✅ เพิ่ม Cache Facade

class OrganizationController extends Controller
{
    // แสดงหน้ารายการ (Search + Pagination)
    public function index(Request $request)
    {
        $search = $request->input('search');
        $page = $request->input('page', 1);

        // สร้าง Cache Key
        $cacheKey = "organization_list_{$search}_page_{$page}";

        // 🚀 CACHE LOGIC: เก็บข้อมูลโครงสร้างองค์กร 1 ชั่วโมง (3600 วิ)
        // ใช้ Tags 'organization' เพื่อให้สั่งล้างได้ง่ายๆ
        $divisions = Cache::tags(['organization'])->remember($cacheKey, 3600, function () use ($search) {

            $query = Division::with('departments');

            // Logic การค้นหา
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('code', 'ilike', "%{$search}%")
                      ->orWhereHas('departments', function($d) use ($search) {
                          $d->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%");
                      });
                });
            }

            return $query->orderBy('name')->paginate(10)->withQueryString();
        });

        return Inertia::render('Organization/Index', [
            'divisions' => $divisions,
            'filters' => $request->only(['search'])
        ]);
    }

    // --- จัดการ กอง (Division) ---
    public function storeDivision(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50']);

        $division = Division::create($request->all());

        // 🧹 Clear Cache
        $this->clearOrgCache();

        // 📝 Log
        $this->logAction('CREATE', 'Division', $division->id, $division->name, $division->toArray());

        return back()->with('success', 'เพิ่มกองสำเร็จ');
    }

    public function updateDivision(Request $request, Division $division)
    {
        $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50']);

        $oldData = $division->toArray();
        $division->update($request->all());

        // 🧹 Clear Cache
        $this->clearOrgCache();

        // 📝 Log
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

        // 🧹 Clear Cache
        $this->clearOrgCache();

        // 📝 Log
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

        // 🧹 Clear Cache
        $this->clearOrgCache();

        // 📝 Log
        $this->logAction('CREATE', 'Department', $department->id, $department->name, $department->toArray());

        return back()->with('success', 'เพิ่มแผนกสำเร็จ');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:50']);

        $oldData = $department->toArray();
        $department->update($request->all());

        // 🧹 Clear Cache
        $this->clearOrgCache();

        // 📝 Log
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

        // 🧹 Clear Cache
        $this->clearOrgCache();

        // 📝 Log
        $this->logAction('DELETE', 'Department', $id, $name, ['note' => 'Deleted department']);

        return back()->with('success', 'ลบแผนกสำเร็จ');
    }

    // --- Helper Functions ---

    /**
     * ล้าง Cache ขององค์กรทั้งหมด รวมถึง Master Data ที่หน้าอื่นๆ เรียกใช้
     */
    private function clearOrgCache()
    {
        // 1. ล้าง Cache ของหน้านี้ (List)
        Cache::tags(['organization'])->flush();

        // 2. ล้าง Master Data ที่หน้า Calendar/User/Project เรียกใช้
        Cache::forget('master_divisions');
        Cache::forget('master_divisions_with_depts');
        Cache::forget('calendar_divisions_list');
    }

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
