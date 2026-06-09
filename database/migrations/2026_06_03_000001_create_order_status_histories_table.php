<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('created_by_type')->default('system');
            $table->string('created_by_id')->nullable();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['created_by_type', 'created_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
