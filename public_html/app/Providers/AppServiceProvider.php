<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Schema;
use View;
use Stripe\Stripe;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
                    Stripe::setVerifySslCerts(false);
        Schema::defaultStringLength(191);
        // View::composer(['layouts.eventmanager'], function ($view) {
        //     $exceeded = false;
        //     $user = auth()->user()->loadCount('zoomMeetings');
        //     if ($user->plan->allowed_events != 0 && $user->zoom_meetings_count >= $user->plan->allowed_events) {
        //         $exceeded = true;
        //     }
        //     $view->with(['exceeded' => $exceeded]);
        // });

        \Validator::extend('alpha_spaces', function ($attribute, $value) {

            // This will only accept alpha and spaces. 
            // If you want to accept hyphens use: /^[\pL\s-]+$/u.
            return preg_match('/^[\pL\s]+$/u', $value); 
    
        });

        \Validator::extend('starts_with_plus', function ($attribute, $value) {
            return \Str::startsWith($value, '+'); 
        });

        \Validator::extend('alpha_spaces_question_mark', function ($attribute, $value) {
            // This will only accept alpha and spaces. 
            // If you want to accept hyphens use: /^[\pL\s-]+$/u.
            return preg_match('/^[\pL\s?]+$/u', $value); 
        });

        \Validator::extend('alpha_numeric_spaces_question_mark', function ($attribute, $value) {
            // This will only accept alpha and spaces. 
            // If you want to accept hyphens use: /^[\pL\s-]+$/u.
            return preg_match('/^[\pL\s?]+$/u', $value) || preg_match('/[0-9]/', $value); 
        });

        \Validator::extend('contain_uppercase', function ($attribute, $value) {
            return preg_match('/[A-Z]/', $value); 
        });

        \Validator::extend('contain_lowercase', function ($attribute, $value) {
            return preg_match('/[a-z]/', $value); 
        });

        \Validator::extend('contain_digit', function ($attribute, $value) {
            return preg_match('/[0-9]/', $value); 
        });

        \Validator::extend('contain_special_character', function ($attribute, $value) {
            return preg_match('/[@$!%*#?&]/', $value); 
        });
    }
}
