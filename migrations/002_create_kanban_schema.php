<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 254)->unique();
            $table->string('name', 100);
            $table->string('password_hash');
            $table->timestampsTz();
        });

        $schema->create('boards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->string('title', 150);
            $table->timestampsTz();
        });

        $schema->create('board_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->string('title', 150);
            $table->unsignedInteger('sort_index');
            $table->index(['board_id', 'sort_index']);
            $table->timestampsTz();
        });

        $schema->create('cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('column_id')->constrained('board_columns')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->default('');
            $table->unsignedInteger('sort_index');
            $table->timestampTz('archived_at')->nullable();
            $table->index(['column_id', 'archived_at', 'sort_index']);
            $table->timestampsTz();
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('cards');
        $schema->drop('board_columns');
        $schema->drop('boards');
        $schema->drop('users');
    }
};
