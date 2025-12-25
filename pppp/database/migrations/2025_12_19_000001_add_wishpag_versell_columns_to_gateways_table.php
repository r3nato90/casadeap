<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            if (!Schema::hasColumn('gateways', 'wishpag_base_url')) {
                $table->string('wishpag_base_url', 255)->nullable()->after('ondapay_secret');
            }
            if (!Schema::hasColumn('gateways', 'wishpag_secret_key')) {
                $table->string('wishpag_secret_key', 255)->nullable()->after('wishpag_base_url');
            }
            if (!Schema::hasColumn('gateways', 'wishpag_company_id')) {
                $table->string('wishpag_company_id', 255)->nullable()->after('wishpag_secret_key');
            }

            if (!Schema::hasColumn('gateways', 'versell_base_url')) {
                $table->string('versell_base_url', 255)->nullable()->after('wishpag_company_id');
            }
            if (!Schema::hasColumn('gateways', 'versell_vspi')) {
                $table->string('versell_vspi', 255)->nullable()->after('versell_base_url');
            }
            if (!Schema::hasColumn('gateways', 'versell_vsps')) {
                $table->string('versell_vsps', 255)->nullable()->after('versell_vspi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gateways', function (Blueprint $table) {
            foreach ([
                'wishpag_base_url','wishpag_secret_key','wishpag_company_id',
                'versell_base_url','versell_vspi','versell_vsps',
            ] as $col) {
                if (Schema::hasColumn('gateways', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
