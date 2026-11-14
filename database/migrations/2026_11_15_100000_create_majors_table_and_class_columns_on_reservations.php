<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 科系主檔（majors）與空間申請的「科系／年級／班級」下拉選擇。
 *
 * 科系主檔記錄每個科系有幾個年級（五專、大學部共用，所以每個科系各自設定）、每個年級分幾個班
 * （依序叫忠、孝、仁、愛，最多 4 班）。空間申請改成先選科系、再選年級、最後選班級，
 * 班級名稱自動組成「簡稱＋年級＋班名」，例如資一忠。
 *
 * classroom_reservations 新增 major_id、class_grade、class_section 三個欄位記下選了什麼；
 * class_name 仍然保留（組好的文字，舊資料與課表顯示都用它）。科系被刪除時 major_id 變空白，class_name 的文字不受影響。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('majors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                       // 科系名稱，例如「資訊科」
            $table->string('major_code', 30)->nullable()->unique(); // 科系代碼（選填），例如 CS
            $table->string('abbreviation', 10);                       // 班級名稱用的簡稱，例如「資」→ 資一忠
            $table->unsignedTinyInteger('grade_count')->default(5); // 年級數：五專通常 5、大學部通常 4，每個科系各自設定
            $table->unsignedTinyInteger('class_count')->default(2); // 每個年級的班數（1～4，依序忠、孝、仁、愛）
            $table->boolean('is_active')->default(true);            // 停用後不出現在下拉選單，舊資料保留
            $table->timestamps();
        });

        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->foreignId('major_id')->nullable()->after('class_name')->constrained('majors')->nullOnDelete();
            $table->unsignedTinyInteger('class_grade')->nullable()->after('major_id');     // 年級（1～）
            $table->unsignedTinyInteger('class_section')->nullable()->after('class_grade'); // 班別（1＝忠、2＝孝、3＝仁、4＝愛）
        });
    }

    public function down(): void
    {
        Schema::table('classroom_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('major_id');
            $table->dropColumn(['class_grade', 'class_section']);
        });

        Schema::dropIfExists('majors');
    }
};
