<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


use App\Models\TicketEmployee;
use App\Models\TicketClientDetail;
use App\Models\TicketDetail;
use App\Models\Ticket;
use App\Models\TicketActivityLog;

class TicketV2Controller extends Controller
{
    /** Convert "john doe" -> "+john* +doe*" for BOOLEAN MODE */
    private function toBooleanQuery(string $q): string
    {
        $clean = preg_replace('/[+\-><\(\)~*\"@]+/', ' ', $q) ?? '';
        $tokens = preg_split('/\s+/', trim($clean)) ?: [];
        $tokens = array_filter($tokens, fn($t) => mb_strlen($t) >= 3);
        if (empty($tokens)) return '';
        return collect($tokens)->map(fn($t) => '+' . $t . '*')->implode(' ');
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('perPage', 10);
        $search  = trim((string) $request->input('search', ''));
        $status  = $request->input('status'); // New | Pending | In Progress | Completed | null
        $division = trim((string) $request->input('division', '')); // e.g. "1","2","3","4","FAD","OTHERS",""

        $searchLen = mb_strlen($search);
        $booleanQ  = $this->toBooleanQuery($search);

        // Map numeric codes to labels
        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP)); // ["1","2","3","4","FAD","IRAD","CRPD","OD-MISPS"]


        $query = TicketClientDetail::query()
            // only what the table shows
            ->select([
                'client_id',
                'client_referenceNo',
                'client_email',
                'client_name',
                'client_type',
                'client_division_agency',
                'created_at',
            ])
            ->with(['detail' => function ($q) {
                $q->select([
                    'ticket_id',
                    'client_referenceNo', // join key
                    'ticket_type',
                    'ticket_nature_of_support',
                    'ticket_support_type',
                    'ticket_assigned_by',
                    'ticket_assigned_staff',
                    'ticket_attended_by',
                    'ticket_assisted_by',
                    'ticket_status',
                    'ticket_problem_that_needed_support',
                    'ticket_actions_taken',
                    'ticket_remarks',
                    'date_requested',
                    'date_completed',
                    'created_at',
                ]);
            }])

            // fast status filter (uses v2td_status_idx)
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })

            // division filter
            ->when($division !== '', function ($q) use ($division, $DIVISION_MAP, $KNOWN_DIVISIONS) {
                if (strcasecmp($division, 'OTHERS') === 0) {
                    // Null/empty or not in the known code/label set
                    $q->where(function ($qq) use ($KNOWN_DIVISIONS) {
                        $qq->whereNull('client_division_agency')
                        ->orWhere('client_division_agency', '')
                        ->orWhereNotIn('client_division_agency', $KNOWN_DIVISIONS);
                    });
                } else {
                    // Accept either numeric code ("1") or label ("FAD")
                    $label = $DIVISION_MAP[$division] ?? $division;
                    $q->where(function ($qq) use ($division, $label) {
                        $qq->where('client_division_agency', $division)
                        ->orWhere('client_division_agency', $label);
                    });
                }
            })

            // search
            ->when($search !== '', function ($q) use ($search, $searchLen, $booleanQ) {
                if ($searchLen >= 3 && $booleanQ !== '') {
                    $q->where(function ($sub) use ($search, $booleanQ) {
                        // FULLTEXT on parent…
                        $sub->whereFullText(
                            ['client_referenceNo','client_name','client_division_agency'],
                            $booleanQ,
                            ['mode' => 'boolean']
                        )
                        ->orWhere('client_email', 'like', '%' . $search . '%')
                        // …or FULLTEXT on child (allowed columns only)
                        ->orWhereHas('detail', function ($d) use ($booleanQ) {
                            $d->whereFullText(
                                ['client_referenceNo','ticket_support_type','ticket_problem_that_needed_support'],
                                $booleanQ,
                                ['mode' => 'boolean']
                            );
                        });
                    });
                } else {
                    // Short queries (1–2 chars): index-friendly left-anchored LIKE
                    $term = $search.'%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('client_referenceNo', 'like', $term)
                            ->orWhere('client_email', 'like', $term)
                            ->orWhere('client_name', 'like', $term);
                    });
                }
            })
            ->latest('created_at'); // uses vtcd_created_idx

        $tickets = $query->paginate($perPage)->withQueryString();

        return Inertia::render('TicketsV2/All', [
            'tickets' => $tickets,
            'filters' => [
                'search'  => $search,
                'status'  => $status,
                'division' => $division,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function create()
    {
    return Inertia::render('TicketsV2/CreateTicket');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_email' => 'required|email|max:191',

            // Ticket Details
            'ticket_type' => 'required|in:Technical Support,Livestream',
            'ticket_nature_of_support' => 'required|in:Hardware,Software,Hardware & Software,System Modification,Others',
            'ticket_support_type' => 'nullable|string',
            'ticket_attended_by' => 'nullable|string',
            'ticket_assisted_by' => 'nullable|string',
            'ticket_problem_that_needed_support' => 'nullable|string',
            'ticket_actions_taken' => 'nullable|string',
            'ticket_remarks' => 'nullable|string',

            // Dates
            'date_requested' => 'required|date',
            'date_completed' => 'nullable|date'
        ]);

        // Extract these early and store in variables
        $clientEmail = strtolower(trim($validated['client_email']));
        $attendedBy = $request->input('ticket_attended_by');
        $dateRequested = $request->input('date_requested');
        $dateCompleted = $request->input('date_completed');

        $datePrefix = \Carbon\Carbon::parse($dateRequested)->format('Ymd');
        $countForDate = TicketDetail::where('client_referenceNo', 'like', $datePrefix . '-%')->count() + 1;
        $reference = $datePrefix . '-' . str_pad($countForDate, 3, '0', STR_PAD_LEFT);

        // Save client details with email only
        TicketClientDetail::create([
            'client_email' => $clientEmail,
            'client_referenceNo' => $reference,
        ]);

        // ✅ Force NA when nature is "Others"
        $supportType = $request->ticket_nature_of_support === 'Others'
            ? 'NA'
            : ($request->ticket_support_type ?? null);

        TicketDetail::create([
            'client_referenceNo' => $reference,
            'ticket_type' => $request->ticket_type,
            'ticket_nature_of_support' => $request->ticket_nature_of_support,
            'ticket_support_type' => $supportType,
            'ticket_attended_by' => $attendedBy,
            'ticket_assisted_by' => $request->ticket_assisted_by,
            'ticket_problem_that_needed_support' => $request->ticket_problem_that_needed_support,
            'ticket_actions_taken' => $request->ticket_actions_taken,
            'ticket_remarks' => $request->ticket_remarks,
            'ticket_status' => 'Completed',
            'date_requested' => $dateRequested,
            'date_completed' => $dateCompleted,
        ]);

        // Save activity logs
        $logs = [
            [
                'activity_name' => 'Ticket Created',
                'activity_date' => $dateRequested,
                'activity_user' => $clientEmail,
            ],
            [
                'activity_name' => 'Ticket Assigned',
                'activity_date' => $dateRequested,
                'activity_user' => $attendedBy,
            ],
            [
                'activity_name' => 'Ticket Attended',
                'activity_date' => $dateRequested,
                'activity_user' => $attendedBy,
            ],
            [
                'activity_name' => 'Ticket Completed',
                'activity_date' => $dateCompleted,
                'activity_user' => $attendedBy,
            ],
        ];

        foreach ($logs as $log) {
            TicketActivityLog::create([
                'client_referenceNo' => $reference,
                'activity_name' => $log['activity_name'],
                'activity_date' => $log['activity_date'],
                'activity_user' => $log['activity_user'],
            ]);
        }

        return redirect('/tickets-v2/data')->with('success', 'Ticket created!');
    }

    public function getEmployees()
    {
        $employees = TicketEmployee::select(
            'id',
            \DB::raw("CONCAT(lname, ', ', fname) AS full_name"),
            'employee_code',
            'division_id',
            'sex'
        )
        ->orderBy('lname')
        ->get();

        return response()->json($employees);
    }

    public function destroy($id)
    {
        if (!Auth::check() || Auth::user()->role !== 'Admin') {
            abort(403, 'Only Admin can delete tickets.');
        }

        DB::transaction(function () use ($id) {
            // Load the ticket and its reference number
            $ticket = TicketClientDetail::with('detail')->findOrFail($id);
            $ref    = $ticket->client_referenceNo;

            // 1) delete logs for this reference
            TicketActivityLog::where('client_referenceNo', $ref)->delete();

            // 2) delete child ticket detail first (FK safety)
            optional($ticket->detail)->delete();

            // 3) delete parent
            $ticket->delete();
        });

        return back()->with('success', 'Ticket and related logs deleted.');
    }

    public function myTickets(Request $request)
    {
        $perPage  = (int) $request->input('perPage', 10);
        $search   = trim((string) $request->input('search', ''));
        $status   = $request->input('status');
        $division = trim((string) $request->input('division', ''));

        $searchLen = mb_strlen($search);
        $booleanQ  = $this->toBooleanQuery($search);

        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));

        $query = TicketClientDetail::query()
            ->select([
                'client_id',
                'client_referenceNo',
                'client_email',
                'client_name',
                'client_type',
                'client_division_agency',
                'created_at',
            ])
            ->with(['detail' => function ($q) {
                $q->select([
                    'ticket_id',
                    'client_referenceNo',
                    'ticket_type',
                    'ticket_nature_of_support',
                    'ticket_support_type',
                    'ticket_assigned_by',
                    'ticket_assigned_staff',
                    'ticket_attended_by',
                    'ticket_assisted_by',
                    'ticket_status',
                    'ticket_problem_that_needed_support',
                    'ticket_actions_taken',
                    'ticket_remarks',
                    'date_requested',
                    'date_completed',
                    'created_at',
                ]);
            }])

            // ✅ Only tickets assigned to logged-in user
            ->whereHas('detail', function ($q) {
                $q->where('ticket_assigned_staff', auth()->user()->name);
                // or auth()->id() if you store staff by ID instead of name
            })

            // status filter
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })

            // division filter
            ->when($division !== '', function ($q) use ($division, $DIVISION_MAP, $KNOWN_DIVISIONS) {
                if (strcasecmp($division, 'OTHERS') === 0) {
                    $q->where(function ($qq) use ($KNOWN_DIVISIONS) {
                        $qq->whereNull('client_division_agency')
                        ->orWhere('client_division_agency', '')
                        ->orWhereNotIn('client_division_agency', $KNOWN_DIVISIONS);
                    });
                } else {
                    $label = $DIVISION_MAP[$division] ?? $division;
                    $q->where(function ($qq) use ($division, $label) {
                        $qq->where('client_division_agency', $division)
                        ->orWhere('client_division_agency', $label);
                    });
                }
            })

            // search filter
            ->when($search !== '', function ($q) use ($search, $searchLen, $booleanQ) {
                if ($searchLen >= 3 && $booleanQ !== '') {
                    $q->where(function ($sub) use ($search, $booleanQ) {
                        $sub->whereFullText(
                            ['client_referenceNo','client_name','client_division_agency'],
                            $booleanQ,
                            ['mode' => 'boolean']
                        )
                        ->orWhere('client_email', 'like', '%' . $search . '%')
                        ->orWhereHas('detail', function ($d) use ($booleanQ) {
                            $d->whereFullText(
                                ['client_referenceNo','ticket_support_type','ticket_problem_that_needed_support'],
                                $booleanQ,
                                ['mode' => 'boolean']
                            );
                        });
                    });
                } else {
                    $term = $search.'%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('client_referenceNo', 'like', $term)
                            ->orWhere('client_email', 'like', $term)
                            ->orWhere('client_name', 'like', $term);
                    });
                }
            })
            ->latest('created_at');

        $tickets = $query->paginate($perPage)->withQueryString();

        return Inertia::render('TicketsV2/MyTickets', [
            'tickets' => $tickets,
            'filters' => [
                'search'  => $search,
                'status'  => $status,
                'division'=> $division,
                'perPage' => $perPage,
            ],
        ]);
    }


}
