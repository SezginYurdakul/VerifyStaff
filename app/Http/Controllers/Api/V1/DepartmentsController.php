<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDepartmentRequest;
use App\Http\Requests\Api\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentsController extends Controller
{
    /**
     * List all departments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Include worker count
        $query->withCount(['workers' => function ($q) {
            $q->where('status', 'active');
        }]);

        $departments = $query->orderBy('name')->get();

        return response()->json([
            'departments' => DepartmentResource::collection($departments),
            'total' => $departments->count(),
        ]);
    }

    /**
     * Create a new department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Set defaults
        $validated['late_threshold_minutes'] = $validated['late_threshold_minutes'] ?? 15;
        $validated['early_departure_threshold_minutes'] = $validated['early_departure_threshold_minutes'] ?? 15;
        $validated['regular_work_minutes'] = $validated['regular_work_minutes'] ?? 480;
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Convert code to uppercase
        $validated['code'] = strtoupper($validated['code']);

        $department = Department::create($validated);

        return response()->json([
            'message' => 'Department created successfully',
            'department' => new DepartmentResource($department),
        ], 201);
    }

    /**
     * Get a single department.
     */
    public function show(int $id): JsonResponse
    {
        $department = Department::withCount(['workers' => function ($q) {
            $q->where('status', 'active');
        }])->findOrFail($id);

        return response()->json([
            'department' => new DepartmentResource($department),
        ]);
    }

    /**
     * Update a department.
     */
    public function update(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validated = $request->validated();

        // Convert code to uppercase if provided
        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully',
            'department' => new DepartmentResource($department->fresh()),
        ]);
    }

    /**
     * Delete a department.
     */
    public function destroy(int $id): JsonResponse
    {
        $department = Department::withCount('users')->findOrFail($id);

        if ($department->users_count > 0) {
            return response()->json([
                'message' => 'Cannot delete department with assigned users. Reassign users first.',
                'users_count' => $department->users_count,
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }
}
