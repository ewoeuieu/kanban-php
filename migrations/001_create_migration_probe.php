<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        $schema->create('migration_probes', function (Blueprint $table): void {
            $table->id();
            $table->string('message');
        });

        $schema->getConnection()->table('migration_probes')->insert(['message' => 'Eloquent работает']);
    }

    public function down(Builder $schema): void
    {
        $schema->drop('migration_probes');
    }
};
