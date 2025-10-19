<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared(<<<'SQL'
CREATE TRIGGER tasks_before_insert_leaf_only
BEFORE INSERT ON tasks
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM goals g
        WHERE g.id = NEW.goal_id
          AND EXISTS (SELECT 1 FROM goals c WHERE c.parent_id = g.id LIMIT 1)
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only leaf goals can have tasks';
    END IF;
END
SQL);

        // ⛔️ تغییر goal_id یک تسک به هدف غیر-لیف نیز ممنوع — BEFORE UPDATE
        DB::unprepared(<<<'SQL'
CREATE TRIGGER tasks_before_update_leaf_only
BEFORE UPDATE ON tasks
FOR EACH ROW
BEGIN
    IF NEW.goal_id IS NOT NULL AND NEW.goal_id <> OLD.goal_id THEN
        IF EXISTS (
            SELECT 1
            FROM goals g
            WHERE g.id = NEW.goal_id
              AND EXISTS (SELECT 1 FROM goals c WHERE c.parent_id = g.id LIMIT 1)
            LIMIT 1
        ) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Only leaf goals can have tasks';
        END IF;
    END IF;
END
SQL);
    }

    public function down(): void
    {
        // حذف تریگرها برای رول‌بک
        DB::unprepared('DROP TRIGGER IF EXISTS tasks_before_insert_leaf_only;');
        DB::unprepared('DROP TRIGGER IF EXISTS tasks_before_update_leaf_only;');
    }
};
