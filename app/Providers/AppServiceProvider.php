<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Author;
use App\Models\Book;
use App\Models\Product;
use App\Models\Publisher;
use App\Observers\AuthorObserver;
use App\Observers\BookObserver;
use App\Observers\ProductObserver;
use App\Observers\PublisherObserver;
use App\Services\PayPalGateway;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PayPalGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Book::observe(BookObserver::class);
        Product::observe(ProductObserver::class);
        Author::observe(AuthorObserver::class);
        Publisher::observe(PublisherObserver::class);
    }
}
