<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capell_demo_kit_generation_runs')) {
            return;
        }

        Schema::create('capell_demo_kit_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->default('queued')->index();
            $table->text('parameters');
            $table->nullableMorphs('requested_by', 'demo_kit_runs_requester_idx');
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capell_demo_kit_generation_runs');
    }
};
