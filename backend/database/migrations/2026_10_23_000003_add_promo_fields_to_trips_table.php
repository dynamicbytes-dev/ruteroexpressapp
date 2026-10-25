<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete()->after('city_id');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('coupon_id');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent');
            $table->decimal('subsidy_amount', 10, 2)->default(0)->after('discount_amount');
            $table->string('promo_type', 20)->nullable()->after('subsidy_amount'); // 'coupon' | 'first_trips'
            $table->index(['promo_type']);
            $table->index(['coupon_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['promo_type']);
            $table->dropIndex(['coupon_id']);
            $table->dropColumn([
                'coupon_id',
                'discount_percent',
                'discount_amount',
                'subsidy_amount',
                'promo_type',
            ]);
        });
    }
};
