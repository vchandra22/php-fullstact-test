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
        Schema::create('my_client', function (Blueprint $table) {
            $table->id();
            $table->char('name', 250);
            $table->char('slug', 100);
            $table->enum('is_project', ['0', '1'])->default('0');
            $table->char('self_capture', 1)->default('1');
            $table->char('client_prefix', 4);
            $table->char('client_logo', 255)->default('no-image.jpg');
            $table->text('address')->nullable();
            $table->char('phone_number', 50)->nullable();
            $table->char('city', 50)->nullable();
            $table->timestamps(0);
            $table->timestamp('deleted_at')->nullable();

            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('my_client');
    }
};
