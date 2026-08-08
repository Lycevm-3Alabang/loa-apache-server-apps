<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    /**
     * Display a listing of the events.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('organizer', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply pagination
        $limit = min($request->input('limit', 25), 100);
        $offset = $request->input('offset', 0);
        
        $events = $query->skip($offset)
                       ->take($limit)
                       ->get();

        $total = $query->count();

        return response()->json([
            'data' => $events,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => $total > ($offset + $limit)
            ]
        ]);
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'certificate_title' => 'nullable|string',
            'certificate_number_pattern' => 'required|string',
            'valid_until' => 'nullable|date',
            'template_id' => 'nullable|uuid',
            'email_template_id' => 'nullable|uuid',
            'status' => 'nullable|in:draft,active,archive'
        ]);

        $event = Event::create($request->all());

        return response()->json([
            'data' => $event
        ], 201);
    }

    /**
     * Display the specified event.
     */
    public function show(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        
        return response()->json([
            'data' => $event
        ]);
    }

    /**
     * Update the specified event in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'organizer' => 'nullable|string',
            'certificate_title' => 'nullable|string',
            'certificate_number_pattern' => 'nullable|string',
            'valid_until' => 'nullable|date',
            'template_id' => 'nullable|uuid',
            'email_template_id' => 'nullable|uuid',
            'status' => 'nullable|in:draft,active,archive'
        ]);

        $event->update($request->all());

        return response()->json([
            'data' => $event
        ]);
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->noContent();
    }

    /**
     * Get event statistics.
     */
    public function stats(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        
        // In a real implementation, this would count attendees and certificates
        // For now, we return mock data based on what is in the schema 
        return response()->json([
            'data' => [
                'event_id' => $event->id,
                'attendees' => ['total' => 0, 'attended' => 0, 'completed' => 0],
                'certificates' => ['issued' => 0, 'active' => 0, 'revoked' => 0, 'expired' => 0],
                'expiring' => 0
            ]
        ]);
    }
}