<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return new class extends Migration {
    public function up(Builder $schema): void
    {
        $schema->create('sessions', function (Blueprint $table): void {
            $table->string('id', 128)->primary();
            $table->text('payload');
            $table->bigInteger('expires_at')->index();
        });
    }

    public function down(Builder $schema): void
    {
        $schema->drop('sessions');
    }
};
