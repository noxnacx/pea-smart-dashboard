<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            // หัวข้อที่ 5: การเปลี่ยนแปลงสถาปัตยกรรมองค์กร (เก็บเป็น Path ของรูปภาพ)
            $table->string('architecture_image')->nullable()->comment('5. การเปลี่ยนแปลงสถาปัตยกรรมองค์กร (รูปภาพ)');

            // หัวข้อที่ 6: หน่วยงานรับผิดชอบ
            $table->text('responsible_agency')->nullable()->comment('6. หน่วยงานรับผิดชอบ');

            // หัวข้อที่ 7: กรอบงบประมาณ (แยกจาก budget ที่เป็นตัวเลข เอาไว้พิมพ์อธิบาย)
            $table->text('budget_framework')->nullable()->comment('7. กรอบงบประมาณ');

            // หัวข้อที่ 8: ตัวชี้วัดการดำเนินงาน (เก็บ Text ที่ User เลือกมาจาก KPI Master)
            $table->text('kpi_details')->nullable()->comment('8. ตัวชี้วัดการดำเนินงาน และค่าเป้าหมาย');

            // หัวข้อที่ 9: ผลประโยชน์ที่คาดว่าจะได้รับ
            $table->text('expected_benefits')->nullable()->comment('9. ผลประโยชน์ที่คาดว่าจะได้รับ');

            // หัวข้อที่ 10: ผลกระทบที่อาจเกิดขึ้น
            $table->text('potential_impacts')->nullable()->comment('10. ผลกระทบที่อาจเกิดขึ้น');

            // หัวข้อที่ 11: ปัจจัยความสำเร็จ
            $table->text('success_factors')->nullable()->comment('11. ปัจจัยความสำเร็จ');

            // หัวข้อที่ 12: ขีดความสามารถของพนักงาน
            $table->text('capability')->nullable()->comment('12. ขีดความสามารถของพนักงานที่จำเป็น');

            // หัวข้อที่ 13: ระยะเวลาดำเนินการ แผนงานโดยประมาณ และผลลัพธ์ที่คาดหวังในแต่ละช่วง
            $table->text('estimated_timeline')->nullable()->comment('13. ระยะเวลาดำเนินการและผลลัพธ์แต่ละช่วง');
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn([
                'architecture_image',
                'responsible_agency',
                'budget_framework',
                'kpi_details',
                'expected_benefits',
                'potential_impacts',
                'success_factors',
                'capability',
                'estimated_timeline'
            ]);
        });
    }
};
