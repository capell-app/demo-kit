<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (TableName::existsAndIsOwned('capell_demo_kit_generation_runs', basename(__FILE__, '.php'))) {
            return;
        }

        Schema::create(TableName::resolve('capell_demo_kit_generation_runs'), function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20)->default('queued')->index();
            $table->text('parameters');
            $table->nullableMorphs('requested_by', TableName::namedIndex('capell_demo_kit_generation_runs', 'demo_kit_runs_requester_idx'));
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('capell_demo_kit_generation_runs'));
    }
};
