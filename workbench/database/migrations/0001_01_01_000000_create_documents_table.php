<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title');
            $table->date('issued_on')->nullable();
            $table->timestamp('recorded_at')->nullable();
            // `from` e' una parola riservata in MySQL e PostgreSQL: serve a
            // provare che gli identificatori vengano wrappati dal grammar.
            $table->date('from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
