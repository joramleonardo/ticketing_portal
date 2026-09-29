<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientRatingsController extends Controller
{
    /**
     * Return completed tickets (for a given employee_code) that have NO rating yet.
     * - anti-join via NOT EXISTS
     */
    // public function unratedByEmployee(Request $request)
    // {
    //     $employeeCode = trim((string) $request->query('employee_code', ''));
    //     if ($employeeCode === '') {
    //         return response()->json(['tickets' => []]);
    //     }

    //     // If user typed "CO-21-08", also try "21-08"
    //     $suffix = preg_replace('/^[A-Za-z]+-/', '', $employeeCode);

    //     $tickets = DB::table('v2_ticket_details as t')
    //         ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 't.client_referenceNo')
    //         ->where(function ($q) use ($employeeCode, $suffix) {
    //             $q->where('c.client_empID', $employeeCode)
    //             ->orWhere('c.client_empID', $suffix);
    //         })
    //         ->where('t.ticket_status', 'Completed')
    //         ->whereNotExists(function ($q) {
    //             $q->from('v2_ticket_rating_details as rd')
    //             ->whereColumn('rd.client_referenceNo', 't.client_referenceNo');
    //         })
    //         ->orderByDesc('t.date_completed')
    //         ->select([
    //             't.client_referenceNo as ref_no',
    //             DB::raw("NULLIF(TRIM(t.ticket_assigned_staff), '') as assigned_staff"),
    //             't.date_completed',
    //         ])
    //         ->get();

    //     return response()->json(['tickets' => $tickets]);
    // }

public function unratedByEmployee(Request $request)
{
    $employeeCode = trim((string) $request->query('employee_code', ''));
    if ($employeeCode === '') {
        return response()->json(['tickets' => []]);
    }

    $window = $request->query('window', 'current_quarter'); // default: current quarter
    $today  = Carbon::now(); // respects app timezone
    $start = $end = null;

    if ($window === 'current_quarter') {
        $start = $today->copy()->startOfQuarter()->startOfDay();
        $end   = $today->copy()->endOfQuarter()->endOfDay();
    } elseif ($window === 'current_year') {
        $start = $today->copy()->startOfYear()->startOfDay();
        $end   = $today->copy()->endOfYear()->endOfDay();
    } elseif ($window === 'range') {
        // expects ?from=YYYY-MM-DD&to=YYYY-MM-DD
        $from = $request->query('from'); $to = $request->query('to');
        if ($from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end   = Carbon::parse($to)->endOfDay();
        }
    }
    // else: window = 'all' → no date filter

    // Your table stores employee code in c.client_empID
    $suffix = preg_replace('/^[A-Za-z]+-/', '', $employeeCode);

    $query = DB::table('v2_ticket_details as t')
        ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 't.client_referenceNo')
        ->where(function ($q) use ($employeeCode, $suffix) {
            $q->where('c.client_empID', $employeeCode)
              ->orWhere('c.client_empID', $suffix);
        })
        ->where('t.ticket_status', 'Completed')
        ->whereNotExists(function ($q) {
            $q->from('v2_ticket_rating_details as rd')
              ->whereColumn('rd.client_referenceNo', 't.client_referenceNo');
        });

    if ($start && $end) {
        $query->whereBetween('t.date_completed', [$start, $end]);
    }

    $tickets = $query->orderByDesc('t.date_completed')
        ->select([
            't.client_referenceNo as ref_no',
            DB::raw("NULLIF(TRIM(t.ticket_assigned_staff), '') as assigned_staff"),
            't.date_completed',
        ])
        ->get();

    return response()->json([
        'tickets' => $tickets,
        'window'  => $window,
        'start'   => optional($start)->toDateTimeString(),
        'end'     => optional($end)->toDateTimeString(),
    ]);
}
}
