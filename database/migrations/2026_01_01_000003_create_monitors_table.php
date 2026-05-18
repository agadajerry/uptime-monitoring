<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->unsignedTinyInteger('check_interval')->default(5); // minutes
            $table->unsignedTinyInteger('threshold')->default(3);       // consecutive failures before "down"
            $table->enum('status', ['pending', 'up', 'down'])->default('pending');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
