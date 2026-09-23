<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('pin_hash')->nullable();
            $table->unsignedTinyInteger('pin_failures')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
        });

        Schema::table('chores', function (Blueprint $table) {
            $table->decimal('reward_eur', 8, 2)->nullable();
            $table->date('anchor_on')->nullable();
            $table->unsignedTinyInteger('interval_days')->nullable();
        });

        Schema::table('chore_occurrences', function (Blueprint $table) {
            $table->foreignId('assignee_member_id')->nullable()->after('chore_id')->constrained('family_members')->nullOnDelete();
            $table->foreignId('completed_by_member_id')->nullable()->after('completed_by')->constrained('family_members')->nullOnDelete();
            $table->decimal('earned_eur', 8, 2)->nullable();
            $table->unsignedTinyInteger('pin_failures')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
        });

        Schema::create('chore_rotation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unique(['chore_id', 'family_member_id']);
        });

        Schema::create('family_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_member_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('pin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chore_occurrence_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->timestamp('created_at')->useCurrent();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table chores drop constraint if exists chores_recurrence_check');
            DB::statement("alter table chores add constraint chores_recurrence_check check (recurrence in ('once', 'daily', 'weekdays', 'interval'))");
            DB::statement("alter table family_absences add constraint family_absences_pattern_check check (pattern in ('range', 'every_other_week', 'every_other_weekend'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pin_events');
        Schema::dropIfExists('family_absences');
        Schema::dropIfExists('chore_rotation_members');

        Schema::table('chore_occurrences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_member_id');
            $table->dropConstrainedForeignId('completed_by_member_id');
            $table->dropColumn(['earned_eur', 'pin_failures', 'pin_locked_until']);
        });

        Schema::table('chores', function (Blueprint $table) {
            $table->dropColumn(['reward_eur', 'anchor_on', 'interval_days']);
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_failures', 'pin_locked_until']);
        });
    }
};
