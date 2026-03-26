<?php

use App\Mail\DueDateReminderMail;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');

});

Route::get('email-test', function() {

    $documents = RequiredDocument::with('complyingOffices')->where('due_date', now()->addDays(2)->toDateString())->get();

    foreach ($documents as $document) {

        $doc = $document->complyingOffices;
            
        $users = User::with('roles')
                ->whereIn('department_code', $doc->pluck('department_code')->toArray())
                ->when($document->is_confidential, function ($query) {
                    // Only users with ViewConfidential:RequiredDocument permission
                    return $query->whereHas('permissions', function ($q) {
                        $q->where('name', 'ViewConfidential:RequiredDocument');
                    });
                })
                ->get();
        
        foreach ($users as $user) {
            Mail::to($user->email)->send(new DueDateReminderMail($document));
        }
    }
    return 'Emails sent!';
});


