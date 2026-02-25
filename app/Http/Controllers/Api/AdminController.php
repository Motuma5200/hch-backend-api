<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Approve a pending user (admin only)
     */
    public function approve(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->approved = true;
        $user->save();

        return response()->json([
            'message' => 'User approved',
            'user' => $user->only('id','name','email','role','organisation','approved'),
        ]);
    }

    /**
     * Reject (delete) a pending user
     */
    public function reject(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Only allow rejecting non-approved accounts
        if ($user->approved) {
            return response()->json(['message' => 'Cannot reject an already approved user'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User rejected and deleted']);
    }

    /**
     * List pending approvals
     */
    public function pending(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $pending = User::where('approved', false)
            ->whereIn('role', ['doctor', 'pharmacy_admin'])
            ->get()
            ->map(function ($u) {
                $hospital = null;
                if ($u->role === 'pharmacy_admin' && $u->hospital_id) {
                    $h = \App\Models\Hospital::find($u->hospital_id);
                    if ($h) $hospital = ['id' => $h->id, 'name' => $h->name, 'city' => $h->city ?? null];
                }

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'organisation' => $u->organisation,
                    'hospital' => $hospital,
                    'id_document' => $u->id_document,
                    'id_document_url' => $u->id_document ? asset('storage/' . $u->id_document) : null,
                ];
            });

        return response()->json($pending);
    }

    /**
     * List all users for admin
     */
    public function index(Request $request)
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $users = User::orderBy('id', 'desc')->get(['id', 'name', 'email', 'role', 'approved', 'organisation']);
        return response()->json($users);
    }

    /**
     * Delete a user (admin only)
     */
    public function destroy(Request $request, $id)
    {
        $authUser = $request->user();

        if (!$authUser || $authUser->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent admin deleting themselves
        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete yourself'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }
}
