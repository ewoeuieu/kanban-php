<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        $schema->create('scheduled_jobs', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->bigInteger('initialized_at');
            $table->bigInteger('last_success_at')->nullable();
            $table->bigInteger('deleted_total')->default(0);
        });

        $schema->getConnection()->table('scheduled_jobs')->insert([
            'name' => 'sessions:prune',
            'initialized_at' => time(),
        ]);
    }

    public function down(Builder $schema): void
    {
        $schema->drop('scheduled_jobs');
    }
};
