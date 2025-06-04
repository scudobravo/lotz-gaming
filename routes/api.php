Route::get('/projects/{project}', function (App\Models\Project $project) {
    return response()->json($project);
}); 