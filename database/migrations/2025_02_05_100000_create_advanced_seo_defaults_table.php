<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Statamic\Eloquent\Database\BaseMigration as Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->prefix('advanced_seo_defaults'), function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('handle')->unique();
            $table->jsonb('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix('advanced_seo_defaults'));
    }
};
