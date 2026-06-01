<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function getDoctors()
    {
        $doctors = User::where('role', 'doctor')
            ->where('approved', true)
            ->select('id', 'name', 'organisation', 'hospital_id')
            ->get();

        $doctors = $doctors->map(function ($doctor) {
            $doctor->specialization = $doctor->organisation ?: 'General Practitioner';
            return $doctor;
        });

        return response()->json($doctors);
    }

    public function getClients()
    {
        if (auth()->user()->role !== 'doctor') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $clients = User::where('role', '!=', 'doctor')
            ->where(function ($query) {
                $query->whereHas('sentMessages', function ($query) {
                    $query->where('doctor_id', auth()->id());
                })
                ->orWhereHas('receivedMessages', function ($query) {
                    $query->where('doctor_id', auth()->id());
                });
            })
            ->select('id', 'name', 'email')
            ->distinct()
            ->get();

        return response()->json($clients);
    }

    public function getChatMessages($otherUserId)
    {
        $user = auth()->user();

        if ($user->role === 'doctor') {
            $doctorId = $user->id;
            $userId = $otherUserId;
        } else {
            $userId = $user->id;
            $doctorId = $otherUserId;
        }

        $messages = ChatMessage::where('user_id', $userId)
            ->where('doctor_id', $doctorId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function sendChatMessage(Request $request, $doctorId)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userId = auth()->id();

        $doctor = User::where('id', $doctorId)
            ->where('role', 'doctor')
            ->where('approved', true)
            ->first();

        if (!$doctor) {
            return response()->json(['error' => 'Doctor not found'], 404);
        }

        $message = ChatMessage::create([
            'user_id' => $userId,
            'doctor_id' => $doctorId,
            'message' => $request->message,
            'sender_type' => 'client',
        ]);

        return response()->json($message, 201);
    }

    public function sendDoctorMessage(Request $request, $userId)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $doctorId = auth()->id();

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (auth()->user()->role !== 'doctor') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message = ChatMessage::create([
            'user_id' => $userId,
            'doctor_id' => $doctorId,
            'message' => $request->message,
            'sender_type' => 'doctor',
        ]);

        return response()->json($message, 201);
    }

    /**
     * NEW: Edit an existing chat message cleanly with role protection
     */
    public function editChatMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Find the record using your ChatMessage Model
        $message = ChatMessage::find($id);

        if (!$message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        $user = auth()->user();

        if ($user->role === 'doctor') {
            if ($message->doctor_id !== $user->id || $message->sender_type !== 'doctor') {
                return response()->json(['error' => 'Unauthorized action'], 403);
            }
        } else {
            if ($message->user_id !== $user->id || $message->sender_type !== 'client') {
                return response()->json(['error' => 'Unauthorized action'], 403);
            }
        }

        // Commit update changes 
        $message->message = $request->message;
        $message->save();

        return response()->json($message, 200);
    }
}