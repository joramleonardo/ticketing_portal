<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Models\TicketStatus;
use App\Models\ActivityLog;
use App\Models\Ticket;

use App\Models\TicketEmployee;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\TicketActivityLog;


class TicketStatusController extends Controller
{
    public function getActivityLogs($reference_code)
    {
        $logs = TicketActivityLog::where('client_referenceNo', $reference_code)
            ->orderBy('activity_date', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            // From modal: Livestream | Technical Support
            'ticket_type' => [
                'sometimes', 'nullable', 'string', 'max:255',
                Rule::in(['Livestream', 'Technical Support']),
            ],

            // Optional assignee
            'ticket_assigned_staff' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ticket_attended_by'    => ['sometimes', 'nullable', 'string', 'max:255'],

            // New completion fields from the updated modal
            'ticket_nature_of_support' => [
                'sometimes', 'nullable',
                Rule::in(['Hardware', 'Software', 'Hardware & Software', 'Others']),
            ],
            'ticket_support_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ticket_remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],


            // Keep if your parent still updates status elsewhere
            'ticket_status' => [
                'sometimes', 'nullable',
                Rule::in(['New', 'Pending', 'In Progress', 'Completed']),
            ],
        ]);

        // Only update fields that were actually sent — including explicit nulls.
        // Using ->exists() (not array_filter) so "Clear" -> null is respected.
        $fields = [
            'ticket_type',
            'ticket_assigned_staff',
            'ticket_attended_by',
            'ticket_nature_of_support',
            'ticket_support_type',
            'ticket_remarks',
            'ticket_status',
        ];

        $updateData = [];
        foreach ($fields as $field) {
            if ($request->exists($field)) {
                $value = $validated[$field] ?? null; // if explicitly null, we will store null
                if (is_string($value)) {
                    $value = trim($value);
                }
                $updateData[$field] = $value;
            }
        }

        $detail = TicketDetail::findOrFail($id); // id = v2_ticket_details.ticket_id


        // ---- Mirror rule: if (current OR incoming) status is "In Progress",
        //      force ticket_attended_by = ticket_assigned_staff (even if null).
        $incomingStatus   = $updateData['ticket_status']     ?? null;
        $effectiveStatus  = $incomingStatus ?: $detail->ticket_status; // current if none sent
        $incomingAssigned = $updateData['ticket_assigned_staff'] ?? null;
        $currentAssigned  = $detail->ticket_assigned_staff;

        if (strcasecmp($effectiveStatus, 'In Progress') === 0) {
            // If request sends ticket_assigned_staff, mirror that;
            // else mirror current assigned staff on record.
            $updateData['ticket_attended_by'] = $request->exists('ticket_assigned_staff')
                ? $incomingAssigned
                : $currentAssigned;
        }

        // --- NEW: stamp who assigned (or cleared/reassigned) the staff
        // Condition: whenever the request explicitly touches ticket_assigned_staff.
        if ($request->exists('ticket_assigned_staff')) {
            $user = auth()->user();
            // Optional: only stamp when value actually changes. Uncomment if desired.
            // if ($incomingAssigned !== $currentAssigned) {
                $updateData['ticket_assigned_by']    = $user?->name ?? 'System';
                $updateData['ticket_assigned_by_id'] = $user?->id   ?? null; // if column exists
            // }
        }


        // Mass-assign safe update
        $detail->fill($updateData)->save();

        return back()->with('success', 'Ticket updated successfully.');
    }

    public function attend(Request $request, $id)
    {
        $validated = $request->validate([
            'ticket_status' => 'nullable|in:New,Pending,In Progress,Completed',
        ]);

        // Find ticket detail
        $detail = TicketDetail::findOrFail($id);

        // Build the update payload
        $update = [];

        // ✅ Update the status
        if (!empty($validated['ticket_status'])) {
            $detail->update([
                'ticket_status' => $validated['ticket_status']
            ]);
        }

        // ✅ Authoritative server-side set
        $update['ticket_attended_by'] = auth()->user()->name;   // or ->id / ->username
        // (optional) if you track timestamp:
        // $update['ticket_attended_at'] = now();

        $detail->update($update);


        // ✅ Create an activity log
        TicketActivityLog::create([
            'client_referenceNo' => $detail->client_referenceNo,
            'activity_name'      => 'Ticket Attended',
            'activity_date'      => Carbon::now(), // instead of Carbon::today()
            'activity_user'      => auth()->user()->name, // or username field
        ]);

        return back()->with('success', 'Ticket attended successfully and now in progress!');

    }

    /** Complete (In Progress -> Completed) */
    public function complete(Request $request, $id)
    {
        $validated = $request->validate([
            'ticket_status'            => 'required|in:Completed',
            'ticket_type'              => 'nullable|string|max:255',
            'ticket_nature_of_support' => 'nullable|string|max:255',
            'ticket_support_type'      => 'nullable|string|max:255',
            'ticket_assisted_by'       => 'nullable|string',
            'ticket_actions_taken'     => 'nullable|string',
            'ticket_remarks'           => 'nullable|string',
        ]);

        DB::transaction(function () use ($id, $validated) {
            $detail = TicketDetail::lockForUpdate()->findOrFail($id);

            // Only allow transition from In Progress -> Completed
            if ($detail->ticket_status !== 'In Progress') {
                abort(422, 'Only tickets In Progress can be marked as completed.');
            }

            // Build the update payload (only provided fields + enforced status/date)
            $updateData = array_filter([
                'ticket_status'            => 'Completed',
                'ticket_type'              => $validated['ticket_type'] ?? null,
                'ticket_nature_of_support' => $validated['ticket_nature_of_support'] ?? null,
                'ticket_support_type'      => $validated['ticket_support_type'] ?? null,
                'ticket_assisted_by'       => $validated['ticket_assisted_by'] ?? null,
                'ticket_actions_taken'     => $validated['ticket_actions_taken'] ?? null,
                'ticket_remarks'           => $validated['ticket_remarks'] ?? null,
                'date_completed'           => Carbon::now(), // instead of Carbon::today()
            ], fn($v) => !is_null($v));

            $detail->update($updateData);

            TicketActivityLog::create([
                'client_referenceNo' => $detail->client_referenceNo,
                'activity_name'      => 'Ticket Completed',
                'activity_date'      => Carbon::now(), // instead of Carbon::today()
                'activity_user'      => auth()->user()->name,
            ]);
        });

        return back()->with('success', 'Ticket completed successfully!');
    }

    public function close(Request $request, $id)
    {
        $validated = $request->validate([
            'ticket_status'  => 'required|in:Closed',
            'ticket_remarks' => 'required|string', // remarks required for closing
        ]);

        DB::transaction(function () use ($id, $validated) {
            $detail = TicketDetail::lockForUpdate()->findOrFail($id);

            // Optionally restrict allowed transitions (here: allow close from In Progress or Pending)
            if (!in_array($detail->ticket_status, ['New','Pending', 'In Progress'])) {
                abort(422, 'Only New, Pending or In Progress tickets can be closed.');
            }

            $detail->update([
                'ticket_status'  => 'Closed',
                'ticket_remarks' => $validated['ticket_remarks'],
                'date_completed' => Carbon::now(), // instead of Carbon::today()
            ]);

            TicketActivityLog::create([
                'client_referenceNo' => $detail->client_referenceNo,
                'activity_name'      => 'Ticket Closed',
                'activity_date'      => Carbon::now(), // instead of Carbon::today()
                'activity_user'      => auth()->user()->name,
                'activity_details'   => $validated['ticket_remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Ticket closed successfully!');
    }

    public function storeNew(Request $request)
        {
            $validated = $request->validate([
                // Client
                'client_email'                       => 'required|email|max:191',
                // Ticket
                'ticket_type'                        => 'required|string|max:255',
                'ticket_problem_that_needed_support' => 'required|string',
            ]);

            $actorName = auth()->user()->name ?? 'System';


            $refNo = DB::transaction(function () use ($validated, $actorName) {
                // 1) Generate new reference number (YYYYMMDD-###)
                $refNo = $this->generateClientReferenceNo();

                // 2) Client details
                TicketClientDetail::create([
                    'client_email'       => strtolower(trim($validated['client_email'])),
                    'client_referenceNo' => $refNo,
                ]);

                // 3) Ticket details (status = In Progress; attended_by = logged-in user)
                TicketDetail::create([
                    'client_referenceNo'                 => $refNo,
                    'ticket_type'                        => $validated['ticket_type'],
                    'ticket_assigned_by'                 => 'Lloyd Mandapat',
                    'ticket_assigned_staff'              => $actorName,
                    'ticket_attended_by'                 => $actorName,
                    'ticket_status'                      => 'In Progress',
                    'ticket_problem_that_needed_support' => $validated['ticket_problem_that_needed_support'],
                    'date_requested'                     => Carbon::now(), // instead of Carbon::today()
                ]);

                // 4) Activity logs (Created, Assigned, Attended)
                $now = Carbon::now();
                TicketActivityLog::insert([
                    [
                        'client_referenceNo' => $refNo,
                        'activity_name'      => 'Ticket Created',
                        'activity_date'      => $now,
                        'activity_user'      => $actorName,
                    ],
                    [
                        'client_referenceNo' => $refNo,
                        'activity_name'      => 'Ticket Assigned',
                        'activity_date'      => $now,
                        'activity_user'      => $actorName,
                    ],
                    [
                        'client_referenceNo' => $refNo,
                        'activity_name'      => 'Ticket Attended',
                        'activity_date'      => $now,
                        'activity_user'      => $actorName,
                    ],
                ]);

                return $refNo;
            });

            // Flash refNo so you can display it without extra queries
            return back()->with([
                'success' => 'New ticket created (In Progress) with activity logs.',
                'refNo'   => $refNo,
            ]);
        }

        /** Generate a daily sequence client reference number like 20250819-001 */
        protected function generateClientReferenceNo(): string
        {
            $prefix = Carbon::today()->format('Ymd');

            // Get last sequence for today
            $last = DB::table('v2_ticket_client_details')
                ->select('client_referenceNo')
                ->where('client_referenceNo', 'like', "{$prefix}-%")
                ->orderBy('client_referenceNo', 'desc')
                ->lockForUpdate()
                ->first();

            $nextSeq = 1;
            if ($last && preg_match('/^'.$prefix.'-(\d{3})$/', $last->client_referenceNo, $m)) {
                $nextSeq = (int)$m[1] + 1;
            }

            return sprintf('%s-%03d', $prefix, $nextSeq);
        }

        public function dashboardStats(Request $request)
        {
            $month = $request->month;
            $year = $request->year;

            $query = TicketStatus::query();

            // Parse varchar date manually
            if ($month && $year) {
                $query->whereRaw("MONTH(STR_TO_DATE(entry_date, '%M %d %Y')) = ?", [$month])
                    ->whereRaw("YEAR(STR_TO_DATE(entry_date, '%M %d %Y')) = ?", [$year]);
            }

            $stats = [
                'unassigned' => (clone $query)->where('status', 'Unassigned')->count(),
                'pending' => (clone $query)->where('status', 'Pending')->count(),
                'in_progress' => (clone $query)->where('status', 'In Progress')->count(),
                'completed' => (clone $query)->where('status', 'Completed')->count(),
            ];

            return Inertia::render('Dashboard', [
                'ticketStats' => $stats,
                'filters' => [
                    'month' => $month,
                    'year' => $year,
                ]
            ]);
        }

    }
