<?php

namespace App\Providers;

use App\Domain\Cadastre\Source\ConfiguredPgsqlParcelDataSource;
use App\Domain\Cadastre\Source\ParcelDataSource;
use Illuminate\Support\ServiceProvider;

final class CadastralServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ParcelDataSource::class, ConfiguredPgsqlParcelDataSource::class);
    }
}
