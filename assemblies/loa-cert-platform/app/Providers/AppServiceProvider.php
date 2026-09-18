<?php

namespace App\Providers;

use App\Interfaces\CertificateStorage;
use App\Services\DiskCertificateStorage;
use App\Services\MetadataCertificateStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(CertificateStorage::class, function ($app) {
            $useMetadata = $app['config']->get('cert-platform.use_metadata_serving', true);

            if ($useMetadata) {
                return $app->make(MetadataCertificateStorage::class);
            }

            return $app->make(DiskCertificateStorage::class);
        });
    }

    public function boot()
    {
        //
    }
}