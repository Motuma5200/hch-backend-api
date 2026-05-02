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

        // Add specialization if needed, for now use organisation or default
        $doctors = $doctors->map(function ($doctor) {
            $doctor->specialization = $doctor->organisation ?: 'General Practitioner';
            return $doctor;
        });

        return response()->json($doctors);
    }

    public function getClients()
    {
        // Only doctors can access this
        if (auth()->user()->role !== 'doctor') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get users (clients) who have chatted with this doctor
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

        // Frontend uses this endpoint for both clients and doctors.
        // - Clients call with doctorId (param is doctor)
        // - Doctors call with clientId (param is client)
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

        // Verify doctor exists and is approved
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

        // Verify user exists
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Verify current user is a doctor
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
}
