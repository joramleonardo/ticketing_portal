<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\TicketCreatedMail;

use App\Models\TicketDetail;         // v2_ticket_details
use App\Models\TicketClientDetail;   // v2_ticket_client_details
use App\Models\TicketActivityLog;
use App\Models\TicketEmployee;       // ticket_employees
use App\Models\TicketAttachmentDetail;               // ← ADD (model maps to v2_ticket_attachment_details)
use App\Models\TicketRatingDetail;                   // v2_ticket_rating_details (if you use it)

class ClientController extends Controller
{
    public function index()
    {
        return Inertia::render('TicketsV2/ClientMain', []);
    }

    public function store(Request $request)
    {
        $DEFAULT_NATURE  = 'Hardware';
        $DEFAULT_SUPPORT = 'Others';

        // Normalize email field whether sent as 'email' or 'client_email'
        if ($request->filled('email') && !$request->filled('client_email')) {
            $request->merge(['client_email' => $request->input('email')]);
        }

        $data = $request->validate([
            'client_email' => ['required', 'email', 'max:191'],
            'ticket_problem_that_needed_support' => ['required', 'string', 'min:5'],
            'attachments'   => ['sometimes', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ], [
            'client_email.required' => 'Please enter your email address.',
            'client_email.email'    => 'Please enter a valid email address.',
        ]);

        $email = trim($data['client_email']);
        $ref = null;

        DB::transaction(function () use ($data, $email, $DEFAULT_NATURE, $DEFAULT_SUPPORT, &$ref) {
            $ref = $this->generateClientReferenceNo();

            TicketDetail::create([
                'client_referenceNo'                 => $ref,
                'ticket_status'                      => 'New',
                'ticket_nature_of_support'           => $DEFAULT_NATURE,
                'ticket_support_type'                => $DEFAULT_SUPPORT,
                'ticket_problem_that_needed_support' => $data['ticket_problem_that_needed_support'],
                'ticket_actions_taken'               => '',
                'ticket_remarks'                     => '',
                'date_requested'                     => now(),
            ]);

            TicketClientDetail::create([
                'client_email'       => $email,
                'client_referenceNo' => $ref,
            ]);

            TicketActivityLog::insert([[
                'client_referenceNo' => $ref,
                'activity_name'      => 'Ticket Created',
                'activity_date'      => now(),
                'activity_user'      => $email,
            ]]);
        });

        $to      = $email;
        $problem = $data['ticket_problem_that_needed_support'] ?? null;
        $refNo   = $ref;

        if ($to) {
            DB::afterCommit(function () use ($to, $refNo, $problem) {
                try {
                    Mail::to($to)->queue(new TicketCreatedMail(
                        refNo: $refNo,
                        clientName: $to,
                        problem: $problem
                    ));
                } catch (\Throwable $ex) {
                    Log::warning('TicketCreatedMail queue failed', [
                        'ref' => $refNo, 'to' => $to, 'err' => $ex->getMessage()
                    ]);
                }
            });
        }

        // ⬇️ save attachments using the reference number
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (!$file->isValid()) continue;

                $path = $file->store("ticket_attachments/{$ref}", 'public');

                TicketAttachmentDetail::create([
                    'client_referenceNo' => $ref,
                    'original_name'      => $file->getClientOriginalName(),
                    'stored_path'        => $path,
                    'mime_type'          => $file->getClientMimeType(),
                    'size_bytes'         => $file->getSize(),
                    'is_image'           => str_starts_with($file->getClientMimeType(), 'image/'),
                    'uploaded_by'        => optional($request->user())->id,
                ]);
            }
        }

        return back()->with('success', 'Ticket created')->with('refNo', $ref);
    }

    /** Generate a daily sequence client reference number like 20250819-001 */
    protected function generateClientReferenceNo(): string
    {
        $prefix = Carbon::today()->format('Ymd');

        // Must be called inside a transaction for lockForUpdate() to work
        $last = DB::table('v2_ticket_client_details')
            ->select('client_referenceNo')
            ->where('client_referenceNo', 'like', "{$prefix}-%")
            ->orderBy('client_referenceNo', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/^'.$prefix.'-(\d{3})$/', $last->client_referenceNo, $m)) {
            $nextSeq = (int) $m[1] + 1;
        }

        return sprintf('%s-%03d', $prefix, $nextSeq);
    }

    public function listAttachments(string $ref)
    {
        $items = TicketAttachmentDetail::where('client_referenceNo', $ref)
            ->latest('id')
            ->get()
            ->map(fn($a) => [
                'id'    => $a->id,
                'name'  => $a->original_name,
                'url'   => Storage::disk('public')->url($a->stored_path),
                'mime'  => $a->mime_type,
                'size'  => (int) $a->size_bytes,
                'is_image' => (bool) $a->is_image,
                'created_at' => optional($a->created_at)->toDateTimeString(),
            ]);

        return response()->json($items);
    }

    public function downloadAttachment(string $ref, TicketAttachmentDetail $attachment)
    {
        // simple guard: be sure this attachment belongs to the ticket ref
        abort_unless($attachment->client_referenceNo === $ref, 403);

        return Storage::disk('public')->download(
            $attachment->stored_path,
            $attachment->original_name
        );
    }

    public function trackByRef(string $ref): JsonResponse
    {
        $ref = trim($ref);

        // Pull core ticket + client email
        $ticket = DB::table('v2_ticket_details as td')
            ->leftJoin('v2_ticket_client_details as cd', 'cd.client_referenceNo', '=', 'td.client_referenceNo')
            ->select([
                'td.client_referenceNo',
                'td.ticket_status',
                'td.ticket_problem_that_needed_support',
                'td.ticket_assigned_staff',
                'td.updated_at',
                DB::raw('cd.client_email as client_email'),
                DB::raw('cd.client_name as client_name'),
            ])
            ->where('td.client_referenceNo', $ref)
            ->first();

        if (!$ticket) {
            return response()->json([
                'ticket' => null,
                'message' => 'Reference not found.'
            ], 404);
        }

        // Activity dates (fallbacks if you log them here)
        $logs = DB::table('v2_ticket_activity_logs')
            ->where('client_referenceNo', $ref)
            ->select('activity_name', 'activity_date')
            ->orderBy('activity_date')
            ->get();

        // Helper: first date that matches any of the provided activity names
        $firstDate = function (array $names) use ($logs) {
            $row = $logs->first(fn ($r) => in_array($r->activity_name, $names, true));
            return $row?->activity_date;
        };

        // Map your actual activity_name values
        $createdAt   = $firstDate(['Ticket Created']);
        $assignedAt  = $firstDate(['Ticket Assigned']);
        $attendedAt  = $firstDate(['Ticket Attended']);
        $completedAt = $firstDate(['Ticket Completed']);

        $assignedToName = $ticket->ticket_assigned_staff;

        return response()->json([
            'ticket' => [
                'client_referenceNo' => $ticket->client_referenceNo,
                'client_email'       => $ticket->client_email,
                'client_name'        => $ticket->client_name,
                'ticket_status'      => $ticket->ticket_status,
                'ticket_problem'     => $ticket->ticket_problem_that_needed_support,
                'assigned_to_name'   => $assignedToName,

                // feed the UI directly from activity logs
                'created_at'         => $createdAt,
                'assigned_at'        => $assignedAt,
                'attended_at'        => $attendedAt,
                'completed_at'       => $completedAt,

                // for “Last Updated” in the summary card
                'updated_at'         => $ticket->updated_at,
            ],
        ]);
    }

    public function ticketShow(string $ref)
    {
        // Basic ticket + client
        $d = DB::table('v2_ticket_details as d')
            ->join('v2_ticket_client_details as c', 'c.client_referenceNo', '=', 'd.client_referenceNo')
            ->where('d.client_referenceNo', $ref)
            ->select([
                'd.client_referenceNo',
                'd.ticket_status',
                'd.ticket_type',
                'd.ticket_nature_of_support',
                'd.ticket_support_type',
                'd.ticket_problem_that_needed_support',
                'd.date_requested',
                'c.client_email',
                'c.client_name',
            ])->first();

        if (!$d) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Dates from activity logs (if you use them)
        $attended = TicketActivityLog::where('client_referenceNo', $ref)
            ->where('activity_name', 'Ticket Attended')
            ->orderByDesc('activity_date')->value('activity_date');

        $completed = TicketActivityLog::where('client_referenceNo', $ref)
            ->where('activity_name', 'Ticket Completed')
            ->orderByDesc('activity_date')->value('activity_date');

        return response()->json([
            'client_referenceNo' => $d->client_referenceNo,
            'ticket_status'      => $d->ticket_status,
            'ticket_type'        => $d->ticket_type,
            'ticket_nature_of_support' => $d->ticket_nature_of_support,
            'ticket_support_type'      => $d->ticket_support_type,
            'ticket_problem_that_needed_support' => $d->ticket_problem_that_needed_support,
            'date_requested'     => optional($d->date_requested)->toDateTimeString() ?? (string)$d->date_requested,
            'date_attended'      => $attended ? Carbon::parse($attended)->toDateTimeString() : null,
            'date_completed'     => $completed ? Carbon::parse($completed)->toDateTimeString() : null,
            'client_email'       => $d->client_email,
            'client_name'        => $d->client_name,
        ]);
    }

    public function ticketMessages(string $ref)
    {
        // If you use v2_ticket_messages
        $rows = DB::table('v2_ticket_messages')
            ->where('client_referenceNo', $ref)
            ->orderBy('id')
            ->get()
            ->map(function ($r) {
                return [
                    'id'          => $r->id,
                    'sender_type' => $r->sender_type, // 'client' or 'staff'
                    'sender_name' => $r->sender_name,
                    'message'     => $r->message,
                    'created_at'  => optional($r->created_at)->toDateTimeString(),
                ];
            });

        return response()->json($rows);
    }

    public function postTicketMessage(Request $request, string $ref)
    {
        $data = $request->validate([
            'message'     => 'required|string|min:1|max:2000',
            'sender_type' => 'required|in:client,staff',
            'sender_name' => 'sometimes|nullable|string|max:255',
        ]);

        // Optional: guard that ticket exists
        $exists = DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists();
        abort_unless($exists, 404, 'Ticket not found');

        DB::table('v2_ticket_messages')->insert([
            'client_referenceNo' => $ref,
            'sender_type' => $data['sender_type'],
            'sender_name' => $data['sender_name'] ?? null,
            'message'     => $data['message'],
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Log to activity log (optional)
        TicketActivityLog::insert([[
            'client_referenceNo' => $ref,
            'activity_name'      => $data['sender_type'] === 'client' ? 'Client Message' : 'Staff Message',
            'activity_date'      => now(),
            'activity_user'      => $data['sender_name'] ?? ucfirst($data['sender_type']),
        ]]);

        return response()->json(['ok' => true]);
    }

    public function ratingShow(string $ref)
    {
        $row = DB::table('v2_ticket_rating_details')
            ->where('client_referenceNo', $ref)
            ->first();

        if (!$row) return response()->json(null);

        return response()->json([
            'rating_id' => $row->rating_id ?? null,
            'client_referenceNo' => $row->client_referenceNo,
            'rating_status' => $row->rating_status,
            'rating_result' => $row->rating_result,
            'rating_comment_suggestion' => $row->rating_comment_suggestion,
            'created_at' => optional($row->created_at)->toDateTimeString(),
            'updated_at' => optional($row->updated_at)->toDateTimeString(),
        ]);
    }

    public function ratingStore(Request $request, string $ref)
    {
        // guard ticket existence
        abort_unless(DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists(), 404, 'Ticket not found');

        $valid = $request->validate([
            'rating_result' => 'required|in:Excellent,Very Good,Good,Poor,Very Poor',
            'rating_comment_suggestion' => 'nullable|string|max:2000',
        ]);

        $now = now();
        $existing = DB::table('v2_ticket_rating_details')->where('client_referenceNo', $ref)->first();

        if ($existing) {
            DB::table('v2_ticket_rating_details')
                ->where('client_referenceNo', $ref)
                ->update([
                    'rating_status' => 'Received',
                    'rating_result' => $valid['rating_result'],
                    'rating_comment_suggestion' => $valid['rating_comment_suggestion'] ?? null,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('v2_ticket_rating_details')->insert([
                'client_referenceNo' => $ref,
                'rating_status' => 'Received',
                'rating_result' => $valid['rating_result'],
                'rating_comment_suggestion' => $valid['rating_comment_suggestion'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Optional: log as activity
        TicketActivityLog::insert([[
            'client_referenceNo' => $ref,
            'activity_name'      => 'Client Rating Submitted',
            'activity_date'      => $now,
            'activity_user'      => 'Client',
        ]]);

        return response()->json(['ok' => true]);
    }

    public function ratePage(string $ref)
    {
        abort_unless(
            DB::table('v2_ticket_details')->where('client_referenceNo', $ref)->exists(),
            404
        );

        return Inertia::render('TicketsV2/ClientComponents/RateTicketPage', [
            'refNo' => $ref,
        ]);
    }






}
