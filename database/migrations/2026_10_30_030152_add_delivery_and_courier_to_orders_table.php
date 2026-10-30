<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_type')->default('pickup')->after('payment_status')->index();
            $table->string('courier_name')->nullable()->after('delivery_type');
            $table->string('booking_code')->nullable()->after('courier_name')->index();
            $table->string('driver_name')->nullable()->after('booking_code');
            $table->string('driver_phone')->nullable()->after('driver_name');
            $table->string('delivery_status')->nullable()->after('driver_phone')->index();
            $table->timestamp('picked_up_at')->nullable()->after('delivery_status');
            $table->text('delivery_notes')->nullable()->after('picked_up_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_type',
                'courier_name',
                'booking_code',
                'driver_name',
                'driver_phone',
                'delivery_status',
                'picked_up_at',
                'delivery_notes',
            ]);
        });
    }
};
