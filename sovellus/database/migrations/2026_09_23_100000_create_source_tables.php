<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('status')->default('disabled');
            $table->text('credentials')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('electricity_settings', function (Blueprint $table) {
            $table->id();
            $table->string('price_basis');
            $table->decimal('green_below', 8, 2);
            $table->decimal('red_from', 8, 2);
            $table->string('message_green');
            $table->string('message_yellow');
            $table->string('message_red');
            $table->string('message_red_extra')->nullable();
            $table->string('message_unknown');
            $table->timestamps();
        });

        Schema::create('electricity_prices', function (Blueprint $table) {
            $table->id();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->decimal('price_ex_vat_eur_mwh', 18, 6);
            $table->decimal('price_inc_vat_snt', 18, 6)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique('start_at');
            $table->index('end_at');
        });

        Schema::create('weather_settings', function (Blueprint $table) {
            $table->id();
            $table->string('place');
            $table->timestamps();
        });

        Schema::create('calendar_sources', function (Blueprint $table) {
            $table->id();
            $table->string('external_calendar_id')->unique();
            $table->string('name');
            $table->boolean('selected')->default(false);
            $table->boolean('show_private_titles')->default(false);
            $table->boolean('show_location')->default(false);
            $table->timestamps();
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('calendar_sources')->cascadeOnDelete();
            $table->string('external_event_id');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('title');
            $table->string('place')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_event_id']);
            $table->index(['starts_at', 'ends_at']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('weather_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('place');
            $table->timestamp('observed_at')->nullable();
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('wind_ms', 6, 2)->nullable();
            $table->timestamp('forecast_at')->nullable();
            $table->decimal('forecast_temperature', 6, 2)->nullable();
            $table->decimal('forecast_feels_like', 6, 2)->nullable();
            $table->decimal('forecast_wind_ms', 6, 2)->nullable();
            $table->unsignedSmallInteger('symbol')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('next_at')->nullable();
            $table->decimal('next_temperature', 6, 2)->nullable();
            $table->string('next_description')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_sources');
        Schema::dropIfExists('weather_snapshots');
        Schema::dropIfExists('weather_settings');
        Schema::dropIfExists('electricity_prices');
        Schema::dropIfExists('electricity_settings');
        Schema::dropIfExists('integrations');
    }
};
