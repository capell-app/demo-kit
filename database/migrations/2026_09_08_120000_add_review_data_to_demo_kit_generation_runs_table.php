<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('capell_demo_kit_generation_runs')) {
            return;
        }

        Schema::table('capell_demo_kit_generation_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('capell_demo_kit_generation_runs', 'fingerprint')) {
                $table->string('fingerprint', 64)->nullable()->index();
            }

            if (! Schema::hasColumn('capell_demo_kit_generation_runs', 'review')) {
                $table->text('review')->nullable();
            }

            if (! Schema::hasColumn('capell_demo_kit_generation_runs', 'created_content')) {
                $table->text('created_content')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('capell_demo_kit_generation_runs')) {
            return;
        }

        Schema::table('capell_demo_kit_generation_runs', function (Blueprint $table): void {
            foreach (['fingerprint', 'review', 'created_content'] as $column) {
                if (Schema::hasColumn('capell_demo_kit_generation_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
