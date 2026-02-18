<?php

use App\Mail\DueDateReminderMail;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('email-test', function() {

    $documents = RequiredDocument::with('complyingOffices')->where('due_date', now()->addDays(2)->toDateString())->get();

    foreach ($documents as $document) {

        $doc = $document->complyingOffices;
            
        $users = User::with('roles')
                ->whereIn('department_code', $doc->pluck('department_code')->toArray())
                ->when($document->is_confidential, function ($query) {
                    $query->role(['super_admin', 'department_head']);
                })
                ->get();
        
        foreach ($users as $user) {
            Mail::to($user->email)->send(new DueDateReminderMail($document));
        }
    }
});

