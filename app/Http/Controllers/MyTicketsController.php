<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\CsmSheetChecker;

use App\Models\TicketClientDetail;
use App\Models\TicketDetail;

class MyTicketsController extends Controller
{
    /**
     * Convert "john doe" -> "+john* +doe*" for BOOLEAN MODE
     */
    private function toBooleanQuery(string $q): string
    {
        $clean = preg_replace('/[+\-><\(\)~*\"@]+/', ' ', $q) ?? '';
        $tokens = preg_split('/\s+/', trim($clean)) ?: [];
        $tokens = array_filter($tokens, fn($t) => mb_strlen($t) >= 3);
        if (empty($tokens)) return '';
        return collect($tokens)->map(fn($t) => '+' . $t . '*')->implode(' ');
    }

    /**
     * List tickets assigned to the logged-in user (ticket_assigned_staff).
     */
    public function index(Request $request, CsmSheetChecker $checker)
    {
        $perPage  = (int) $request->input('perPage', 10);
        $search   = trim((string) $request->input('search', ''));
        $status   = $request->input('status'); // New | Pending | In Progress | Completed | null
        $division = trim((string) $request->input('division', ''));

        $searchLen = mb_strlen($search);
        $booleanQ  = $this->toBooleanQuery($search);

        // Map numeric codes to labels
        $DIVISION_MAP = [
            '1' => 'FAD',
            '2' => 'IRAD',
            '3' => 'CRPD',
            '4' => 'OD-MISPS',
        ];
        $KNOWN_DIVISIONS = array_merge(array_keys($DIVISION_MAP), array_values($DIVISION_MAP));

        // IMPORTANT: Adjust this if you store staff by user_id instead of name
        $assignedValue = auth()->user()->name; // change to auth()->id() if the column stores IDs

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

            // ✅ Only tickets assigned to the logged-in user
            ->whereHas('detail', function ($q) use ($assignedValue) {
                $q->where('ticket_assigned_staff', $assignedValue);
                // If the column stores user_id instead of name, use:
                // $q->where('ticket_assigned_staff', auth()->id());
            })

            // fast status filter
            ->when($status, function ($q) use ($status) {
                $q->whereHas('detail', fn ($d) => $d->where('ticket_status', $status));
            })

            // division filter (supports numeric code or label)
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

            // search
            ->when($search !== '', function ($q) use ($search, $searchLen, $booleanQ) {
                if ($searchLen >= 3 && $booleanQ !== '') {
                    $q->where(function ($sub) use ($search, $booleanQ) {
                        // FULLTEXT on parent…
                        $sub->whereFullText(
                            ['client_referenceNo', 'client_name', 'client_division_agency'],
                            $booleanQ,
                            ['mode' => 'boolean']
                        )
                        ->orWhere('client_email', 'like', '%' . $search . '%')
                        // …or FULLTEXT on child
                        ->orWhereHas('detail', function ($d) use ($booleanQ) {
                            $d->whereFullText(
                                ['client_referenceNo', 'ticket_support_type', 'ticket_problem_that_needed_support'],
                                $booleanQ,
                                ['mode' => 'boolean']
                            );
                        });
                    });
                } else {
                    // Short queries (1–2 chars)
                    $term = $search . '%';
                    $q->where(function ($sub) use ($term) {
                        $sub->where('client_referenceNo', 'like', $term)
                            ->orWhere('client_email', 'like', $term)
                            ->orWhere('client_name', 'like', $term);
                    });
                }
            })
            ->latest('created_at'); // index-friendly

        $tickets = $query->paginate($perPage)->withQueryString();

        // ---------- simple per-status stats for header cards ----------
        $rawCounts = TicketDetail::query()
            ->selectRaw('ticket_status, COUNT(*) as cnt')
            ->where('ticket_assigned_staff', $assignedValue)
            ->groupBy('ticket_status')
            ->pluck('cnt', 'ticket_status');

        $ticketStats = [
            'total'        => (int) $rawCounts->sum(),
            'new'          => (int) ($rawCounts['New'] ?? 0),
            'pending'      => (int) ($rawCounts['Pending'] ?? 0),
            'in_progress'  => (int) ($rawCounts['In Progress'] ?? 0),
            'completed'    => (int) ($rawCounts['Completed'] ?? 0),
        ];
        // --------------------------------------------------------------

        $latestRating = DB::table('v2_ticket_rating_details as r1')
                ->select('r1.client_referenceNo', DB::raw('MAX(r1.created_at) as max_created'))
                ->groupBy('r1.client_referenceNo');

        $pendingTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->joinSub($latestRating, 'lr', function ($join) {
                $join->on('lr.client_referenceNo', '=', 'd.client_referenceNo');
            })
            ->join('v2_ticket_rating_details as r', function ($join) {
                $join->on('r.client_referenceNo', '=', 'd.client_referenceNo')
                    ->on('r.created_at', '=', 'lr.max_created');
            })
            ->where('r.rating_status', 'Pending')
            ->where('d.ticket_assigned_staff', $assignedValue) // only my tickets
            ->orderBy('d.created_at', 'desc')
            ->select([
                'd.client_referenceNo',
                'c.client_email',
                'c.client_name',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
            ])
            ->get();

        $myNewTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'Pending')
            ->orderBy('d.date_requested', 'desc')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_email',
                'c.client_name',
                'c.client_type',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
            ])
            ->get();

        $myInProgTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'In Progress')
            ->orderBy('d.date_requested', 'desc')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_email',
                'c.client_name',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
            ])
            ->get();

        $myCompletedTickets = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.ticket_assigned_staff', $assignedValue)
            ->where('d.ticket_status', 'Completed')
            ->orderBy('d.date_completed', 'desc')
            ->select([
                'd.ticket_id',
                'd.client_referenceNo',
                'c.client_email',
                'c.client_name',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_attended_by',
                'd.ticket_assigned_by',
                'd.ticket_assigned_staff',
                'd.ticket_status',
                'd.ticket_problem_that_needed_support',
                'd.ticket_actions_taken',
                'd.ticket_remarks',
                'd.date_requested',
                'd.date_completed',
            ])
            ->get();

        // Build one hash set of all references present in the Google Sheet
        $inSheet = $checker->referenceSet(); // ['20240119-001' => true, ...]

        // Keep only tickets whose ref is NOT in the sheet
        $noCsmTickets = $myCompletedTickets->filter(function ($t) use ($inSheet) {
            return !isset($inSheet[$t->client_referenceNo]);
        })->values();



        return Inertia::render('TicketsV2/MyTicketsV2', [
            'tickets' => $tickets,
            'ticketStats' => $ticketStats,
            'filters' => [
                'search'   => $search,
                'status'   => $status,
                'division' => $division,
                'perPage'  => $perPage,
            ],

            'pendingTickets' => $pendingTickets,
            'myNewTickets' => $myNewTickets,
            'myInProgTickets' => $myInProgTickets,
            'noCsmTickets'    => $noCsmTickets,
        ]);
    }
}
