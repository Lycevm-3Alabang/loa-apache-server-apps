<?php

namespace App\Http\Controllers;

use App\Models\EventAttendee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendeeController extends Controller
{
    /**
     * Display a listing of attendees for an event.
     */
    public function index(Request $request, string $eventId): JsonResponse
    {
        $query = EventAttendee::where('event_id', $eventId);

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply attendance filters
        if ($request->has('attended')) {
            $query->where('attended', $request->input('attended'));
        }

        if ($request->has('completed')) {
            $query->where('completed', $request->input('completed'));
        }

        // Apply pagination
        $limit = min($request->input('limit', 25), 100);
        $offset = $request->input('offset', 0);
        
        $attendees = $query->skip($offset)
                          ->take($limit)
                          ->get();

        $total = $query->count();

        return response()->json([
            'data' => $attendees,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => $total > ($offset + $limit)
            ]
        ]);
    }

    /**
     * Store a newly created attendee in storage.
     */
    public function store(Request $request, string $eventId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:event_attendees,event_id,' . $eventId . ',event_id,email',
            'attended' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        $attendee = EventAttendee::create(array_merge($request->all(), [
            'event_id' => $eventId,
            'organization_id' => $this->getOrganizationId()
        ]));

        return response()->json([
            'data' => $attendee
        ], 201);
    }

    /**
     * Update the specified attendee in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:event_attendees,id,' . $id . ',id,email',
            'attended' => 'nullable|boolean',
            'completed' => 'nullable|boolean',
            'metadata' => 'nullable|array'
        ]);

        $attendee->update($request->all());

        return response()->json([
            'data' => $attendee
        ]);
    }

    /**
     * Remove the specified attendee from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);
        $attendee->delete();

        return response()->noContent();
    }

    /**
     * Import attendees in bulk.
     */
    public function import(Request $request, string $eventId): JsonResponse
    {
        $request->validate([
            'attendees' => 'required|array',
            'attendees.*.name' => 'required|string',
            'attendees.*.email' => 'required|email',
            'mode' => 'nullable|string|in:merge,replace',
            'confirm' => 'required_if:mode,replace|boolean'
        ]);

        $mode = $request->input('mode', 'merge');
        $attendeesData = $request->input('attendees');

        // Process the import
        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($attendeesData as $index => $attendeeData) {
            try {
                // Basic validation
                if (empty($attendeeData['name']) || empty($attendeeData['email'])) {
                    $result['errors'][] = [
                        'row' => $index + 1,
                        'email' => $attendeeData['email'] ?? '',
                        'reason' => 'Missing name or email'
                    ];
                    $result['skipped']++;
                    continue;
                }

                if (!filter_var($attendeeData['email'], FILTER_VALIDATE_EMAIL)) {
                    $result['errors'][] = [
                        'row' => $index + 1,
                        'email' => $attendeeData['email'],
                        'reason' => 'Invalid email format'
                    ];
                    $result['skipped']++;
                    continue;
                }

                // Create or update attendee
                $attendee = EventAttendee::updateOrCreate(
                    [
                        'event_id' => $eventId,
                        'email' => $attendeeData['email']
                    ],
                    array_merge($attendeeData, [
                        'event_id' => $eventId,
                        'organization_id' => $this->getOrganizationId()
                    ])
                );

                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'row' => $index + 1,
                    'email' => $attendeeData['email'] ?? '',
                    'reason' => $e->getMessage()
                ];
                $result['skipped']++;
            }
        }

        return response()->json([
            'data' => $result
        ]);
    }

    /**
     * Get attendee delete preview.
     */
    public function deletePreview(string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);
        
        // In a real implementation, this would check if attendee has linked certificate
        return response()->json([
            'data' => [
                'attendee_id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'linked_certificate' => null,
                'deletes_certificate' => false
            ]
        ]);
    }

    /**
     * Get attendee file data.
     */
    public function fileData(string $id): JsonResponse
    {
        $attendee = EventAttendee::findOrFail($id);
        
        if ($attendee->metadata && isset($attendee->metadata['file_path'])) {
            // In a real implementation, this would return the actual file
            return response()->json([
                'data' => [
                    'generation_mode' => 'file'
                ]
            ]);
        }
        
        return response()->json([
            'data' => [
                'generation_mode' => 'template'
            ]
        ]);
    }

    /**
     * Get organization ID (placeholder implementation).
     */
    private function getOrganizationId(): string
    {
        // In a real implementation, this would be retrieved from the JWT or request
        // For now, we'll return a default organization ID for demo purposes
        return '123e4567-e89b-12d3-a456-426614174000';
    }
}