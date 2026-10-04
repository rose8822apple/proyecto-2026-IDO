<?php

namespace App\Providers;

use App\Models\Availability;
use App\Models\Person;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $notifications = collect()
                ->concat(Shift::query()->latest()->limit(8)->get(['id', 'title', 'created_at'])->map(fn (Shift $shift) => [
                    'category' => 'Nuevo turno',
                    'title' => $shift->title,
                    'icon' => 'bi-calendar-check',
                    'url' => route('shifts'),
                    'created_at' => $shift->created_at,
                ]))
                ->concat(Person::query()->latest()->limit(8)->get(['id', 'name', 'created_at'])->map(fn (Person $person) => [
                    'category' => 'Personal añadido',
                    'title' => $person->name,
                    'icon' => 'bi-person-plus',
                    'url' => route('people'),
                    'created_at' => $person->created_at,
                ]))
                ->concat(Site::query()->latest()->limit(8)->get(['id', 'name', 'created_at'])->map(fn (Site $site) => [
                    'category' => 'Sede añadida',
                    'title' => $site->name,
                    'icon' => 'bi-hospital',
                    'url' => route('sites'),
                    'created_at' => $site->created_at,
                ]))
                ->concat(Availability::query()->with('person:id,name')->latest()->limit(8)->get(['id', 'person_id', 'created_at'])->map(fn (Availability $availability) => [
                    'category' => 'Nueva disponibilidad',
                    'title' => $availability->person?->name ?? 'Personal sin nombre',
                    'icon' => 'bi-clock-history',
                    'url' => route('availability'),
                    'created_at' => $availability->created_at,
                ]))
                ->sortByDesc(fn (array $notification) => $notification['created_at']->timestamp)
                ->take(8)
                ->values();
            $seenThrough = session('notifications.seen_through');
            $unreadCount = $seenThrough === null
                ? $notifications->count()
                : Shift::query()->where('id', '>', $seenThrough['shifts'] ?? 0)->count()
                    + Person::query()->where('id', '>', $seenThrough['people'] ?? 0)->count()
                    + Site::query()->where('id', '>', $seenThrough['sites'] ?? 0)->count()
                    + Availability::query()->where('id', '>', $seenThrough['availability'] ?? 0)->count();

            $view->with('notifications', $notifications);
            $view->with('notificationUnreadCount', $unreadCount);
        });
    }
}
