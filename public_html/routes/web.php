<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Illuminate\Support\Facades\Artisan;


Route::get('/clear-cache-2', function() {
    return "testing Cache is cleared now";
});

Route::get('/clear-cache', function() {
    //Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    Artisan::call('cache:clear');
    //Artisan::call('config:cache');
    return "Cache is cleared now";
});

Route::get('/clear-view', function() {
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    Artisan::call('cache:clear');
    //Artisan::call('config:clear');
    //Artisan::call('cache:clear');
    return "View is cleared now new";
});

Route::get('/view-all-routes', function() {
    Artisan::call('route:list');
    return Artisan::output();
});

Route::get('/app-version', function() {
    echo app()->version();
});


Route::get('/limitedpricing', 'GuestController@limited_pricing')->name('frontend.limitedpricing');

Route::get('/test-route-list', 'GuestController@testRouteList');
Route::get('/test-mail', 'GuestController@testMail');
Route::get('/unsubscribe', 'GuestController@unsubscribee');
Route::get('/cron', 'GuestController@cron');
Route::middleware('visitlog', 'visit_log')->group(function () {
    Route::post('/add_active_user', 'ActiveUserController@save')->name('active.users');
    Route::get('/get_active_user', 'ActiveUserController@getUsers')->name('active.get');
    Auth::routes(['verify' => true]);
    Route::post('redirect/{provider}', 'Auth\SocialLoginController@redirect')->name('social.redirect')->where('provider', 'facebook|google|linkedin');
    Route::get('callback/{provider}', 'Auth\SocialLoginController@callback')->name('social.callback')->where('provider', 'facebook|google|linkedin');

    Route::get('/', 'GuestController@index')->name('frontend');
    Route::get('/pricing', 'GuestController@pricing')->name('frontend.pricing');
    Route::get('/platform', 'GuestController@platform')->name('frontend.platform');
    Route::get('/features', 'GuestController@feature')->name('frontend.feature');
    Route::get('/blog', 'GuestController@blog')->name('frontend.blog');
    Route::get('/blog/{slug}', 'GuestController@blogs')->name('frontend.blogs');
    Route::get('/terms', 'GuestController@terms')->name('frontend.terms');
    Route::get('/privacy', 'GuestController@privacy')->name('frontend.privacy');
    Route::get('/zoomintegration', 'GuestController@zoom')->name('frontend.zoom');
    Route::get('/support', 'GuestController@support')->name('frontend.support');
    Route::get('/contact', 'GuestController@contact')->name('frontend.contact');
    Route::get('/help', 'GuestController@help')->name('frontend.help');
    Route::get('{pubic_url}', 'GuestController@event')->name('frontend.event');
    Route::post('/event/register', 'GuestController@register')->name('frontend.register');
    Route::post('/subscribe', 'GuestController@subscribe')->name('subscribe');

    Route::post('zoom/remove', 'Auth\ZoomController@remove');
    Route::middleware('auth')->namespace('Auth')->group(function () {
        Route::get('account/dashboard', 'ProfileController@dashboard')->name('eventmanager.dashboard');
        Route::middleware('checkverify')->group(function () {
            Route::get('account/profile', 'ProfileController@index')->name('eventmanager.profile');
            Route::post('zoom/redirect', 'ZoomController@redirect')->name('zoom.add');
            Route::get('zoom/callback', 'ZoomController@callback')->name('zoom.callback');

            Route::post('stripe/redirect', 'StripeController@redirect')->name('stripe.add');
            Route::get('stripe/callback', 'StripeController@callback')->name('stripe.callback');
            Route::post('stripe/remove', 'StripeController@remove')->name('stripe.remove');

            Route::get('account/pay/{plan}', 'PaymentController@index')->name('eventmanager.payment');
            Route::post('account/plan/unsubscribe', 'PaymentController@unsubscribe')->name('eventmanager.plan.unsubscribe');
            Route::post('account/pay/{plan}', 'PaymentController@subscribe');


            Route::post('account/profile', 'ProfileController@update');
            Route::get('account/payout', 'ProfileController@payout')->name('eventmanager.payout');
            Route::post('account/payout', 'ProfileController@payoutPost');
            Route::name('eventmanager.')->prefix('account')->group(function () {
                Route::get('event/guest/export/{id}', 'EventController@exportGuests')->name('event.exportGuests');
                Route::get('event/duplicate/{id}', 'EventController@duplicate')->name('event.duplicate');
                Route::get('event/reminder/{id}', 'EventController@reminder')->name('event.reminder');
                Route::post('event/reminder/{id}', 'EventController@reminderSave');
                Route::delete('event/{event}/reminder/{reminder}', 'EventController@removeReminder')->name('event.reminder.destroy');
                Route::get('event/guest/{id}', 'EventController@guest')->name('event.guest');
                Route::post('event/guest/{id}', 'EventController@invite');
                Route::delete('event/{event}/guest/{guest}', 'EventController@removeGuest')->name('event.guest.destroy');
                Route::post('event/guest/status/{id}', 'EventController@status')->name('event.guest.status');
                Route::post('event/updateemail/{id}', 'EventController@updateemail')->name('event.updateemail');
                Route::resource('event', 'EventController');
            });
        });
    });
});


Route::prefix('admin')->namespace('Admin')->name('admin.')->group(function () {
    Auth::routes(['register' => false]);

    Route::middleware('admin:admin')->group(function () {
        Route::get('dashboard', 'DashboardController@index')->name('dashboard');
        Route::view('users', 'admin.users')->name('users');
        Route::view('payments', 'admin.orders')->name('orders');
        Route::resource('admin', 'AdminController')->except('show');
        Route::get('user/export', 'UserController@export')->name('user.export');
        Route::resource('user', 'UserController')->only('index', 'destroy');
        Route::resource('plan', 'PlanController')->except('show', 'edit', 'update');
        Route::resource('blog', 'BlogController')->except('show');
        Route::resource('image', 'ImageController')->except('show');
        Route::get('newsletter/export', 'NewsletterController@export')->name('newsletter.export');
        Route::resource('newsletter', 'NewsletterController')->except('edit', 'update');
        Route::resource('email', 'EmailController')->only('index', 'edit', 'update');
        Route::get('email-record', 'EmailRecordController@index')->name('email_record.index');
        Route::post('email-record/delete', 'EmailRecordController@delete')->name('email_record.delete');
        Route::post('email-record/delete-all','EmailRecordController@deleteAll')->name('email_record.delete_all');
        Route::get('export-all-mail-log','EmailRecordController@exportAll')->name('export-all-mail-log');
        Route::get('/testing', 'EmailRecordController@ipaddress');
        Route::get('/analytics', 'AnalyticsController@index')->name('analytics.show');
        Route::get('/analytics/details', 'AnalyticsController@details')->name('analytics.details');
        Route::post('/analytics/details/delete', 'AnalyticsController@delete')->name('analytics.delete');
    });
});


