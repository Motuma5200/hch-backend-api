<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSymptomLogsTable extends Migration
{
    public function up()
    {
        Schema::create('symptom_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symptom');
            $table->text('description')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe']);
            $table->timestamp('recorded_at');
            $table->timestamps();
            
            $table->index(['user_id', 'recorded_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('symptom_logs');
    }
}