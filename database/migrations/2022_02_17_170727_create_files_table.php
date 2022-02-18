<?php

use App\Models\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('uuid')->unique();
            $table->string('file_name');
            $table->integer('file_size');
            $table->string('extension');
            $table->string('mime');
            $table->string('local_path')->nullable();
            $table->string('azure_path')->nullable();
            $table->string('status')->default(File::STATUS_UPLOADING);
            $table->string('chunk_path')->nullable();
            $table->integer('chunks_received');
            $table->integer('chunks_total');
            $table->dateTime('last_chunk_uploaded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
};
