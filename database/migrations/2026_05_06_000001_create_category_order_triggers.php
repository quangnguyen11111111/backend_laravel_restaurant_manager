<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_dishes_after_insert_update_category_order');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dishes_after_update_update_category_order');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dishes_after_delete_update_category_order');

        DB::unprepared('CREATE TRIGGER trg_dishes_after_insert_update_category_order
            AFTER INSERT ON dishes
            FOR EACH ROW
            BEGIN
                IF NEW.category_id IS NOT NULL THEN
                    UPDATE categories
                    SET `order` = (
                        SELECT COUNT(*)
                        FROM dishes
                        WHERE category_id = NEW.category_id
                    )
                    WHERE id = NEW.category_id;
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER trg_dishes_after_update_update_category_order
            AFTER UPDATE ON dishes
            FOR EACH ROW
            BEGIN
                IF NEW.category_id IS NOT NULL THEN
                    UPDATE categories
                    SET `order` = (
                        SELECT COUNT(*)
                        FROM dishes
                        WHERE category_id = NEW.category_id
                    )
                    WHERE id = NEW.category_id;
                END IF;

                IF OLD.category_id IS NOT NULL AND (OLD.category_id <> NEW.category_id OR NEW.category_id IS NULL) THEN
                    UPDATE categories
                    SET `order` = (
                        SELECT COUNT(*)
                        FROM dishes
                        WHERE category_id = OLD.category_id
                    )
                    WHERE id = OLD.category_id;
                END IF;
            END');

        DB::unprepared('CREATE TRIGGER trg_dishes_after_delete_update_category_order
            AFTER DELETE ON dishes
            FOR EACH ROW
            BEGIN
                IF OLD.category_id IS NOT NULL THEN
                    UPDATE categories
                    SET `order` = (
                        SELECT COUNT(*)
                        FROM dishes
                        WHERE category_id = OLD.category_id
                    )
                    WHERE id = OLD.category_id;
                END IF;
            END');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_dishes_after_insert_update_category_order');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dishes_after_update_update_category_order');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_dishes_after_delete_update_category_order');
    }
};
