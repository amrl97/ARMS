<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('departments/index', [
            'departments' => Department::withCount('users')
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('departments/create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')],
        ]);

        Department::create($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department created successfully.',
        ]);

        return to_route('departments.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        return Inertia::render('departments/view', [
            'department' => $department->loadCount('users'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        return Inertia::render('departments/edit', [
            'department' => $department,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
        ]);

        $department->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department updated successfully.',
        ]);

        return to_route('departments.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        if ($department->users()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'This department has users and cannot be deleted.',
            ]);
        }

        $department->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Department deleted successfully.',
        ]);

        return to_route('departments.index');
    }
}
