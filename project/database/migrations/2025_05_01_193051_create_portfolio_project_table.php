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
        Schema::create('portfolio_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('portfolio_bg')->nullable(); // Background image URL
            $table->string('portfolio_id')->unique(); // Custom portfolio ID like "p11a"
            $table->string('role');
            $table->text('project_description'); // Changed from description to match Flutter
            $table->json('project_images')->nullable(); // Array of image URLs
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('portfolio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_project');
    }
};