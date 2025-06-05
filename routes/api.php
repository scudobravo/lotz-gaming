<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/projects/{project}', function (App\Models\Project $project) {
    return response()->json($project);
}); 