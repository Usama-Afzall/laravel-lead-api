<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 254);
            $table->string('phone', 30)->nullable();
            $table->text('message');
            $table->string('source', 50)->nullable();

            // Optional campaign attribution, sent by the front end if available.
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();

            // 45 characters fits the longest textual IPv6 address.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // For your own workflow: set when someone has followed the lead up.
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
