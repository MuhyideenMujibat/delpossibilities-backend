<?php

namespace App\Http\Controllers;

use App\Models\Permission;

class PermissionController extends Controller
{
    // Read-only on purpose: permissions are a fixed set tied to real
    // access-control checks in the route middleware, not a freeform list an
    // admin can add to from the UI.
    public function index()
    {
        return response()->json(Permission::orderBy('label')->get());
    }
}
