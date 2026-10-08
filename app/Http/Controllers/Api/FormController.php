<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\MembershipPlan;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    public function contact(Request $request, NotificationService $notifications, CustomerAccountService $accounts)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:4', 'max:5000'],
        ]);
        $user = $this->account($request, $data, $accounts);
        $this->save('contact', $data, $user);
        $notifications->adminAfterResponse('New contact message — '.$data['name'], $data);

        return response()->json(['ok' => true]);
    }

    public function enroll(Request $request, NotificationService $notifications, CustomerAccountService $accounts)
    {
        if ($request->filled('website')) {
            return response()->json(['ok' => true, 'note' => 'Request received. The desk will follow up.']);
        }
        $data = $request->validate([
            'program' => ['required', 'exists:programs,slug'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'age' => ['nullable', 'string'],
            'comments' => ['nullable', 'string'],
        ]);
        $user = $this->account($request, $data, $accounts);
        $program = Program::where('slug', $data['program'])->firstOrFail();
        ProgramRegistration::create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'received',
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'answers' => ['address' => $data['address'] ?? null, 'age' => $data['age'] ?? null],
            'notes' => $data['comments'] ?? null,
        ]);
        $this->save('dj_enrollment', $data, $user);
        $notifications->adminAfterResponse('New DJ School enrollment request — '.$data['name'], $data);
        $notifications->welcomeAfterResponse($data['email'], $data['name'], 'dj', $program->name);

        return response()->json(['ok' => true, 'note' => 'Request received. Check your email to complete DJ Registration.']);
    }

    public function djLesson(Request $request, NotificationService $notifications, CustomerAccountService $accounts)
    {
        if ($request->filled('website')) {
            return response()->json(['ok' => true, 'note' => 'Inquiry received. The desk will follow up to confirm placement.']);
        }
        $data = $request->validate([
            'studentName' => ['required', 'string'], 'email' => ['required', 'email'],
            'experienceLevel' => ['required'], 'lessonInterest' => ['required'],
            'phone' => ['nullable'], 'age' => ['nullable'], 'parentName' => ['nullable'],
            'parentContact' => ['nullable'], 'software' => ['nullable', 'array'],
            'equipment' => ['nullable'], 'learnGoals' => ['nullable', 'array'],
            'genres' => ['nullable'], 'mainGoals' => ['nullable', 'array'],
            'otherGoal' => ['nullable'], 'preferredDays' => ['nullable', 'array'],
            'preferredTime' => ['nullable'], 'hasEvent' => ['nullable'],
            'eventDate' => ['nullable', 'date'], 'eventDetails' => ['nullable'],
        ]);
        $normalized = ['name' => $data['studentName'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null] + $data;
        $user = $this->account($request, $normalized, $accounts);
        $data['studentName'] = $normalized['name'];
        $data['email'] = $normalized['email'];
        $data['phone'] = $normalized['phone'];
        $this->save('dj_lesson_inquiry', $normalized, $user);
        $notifications->adminAfterResponse('New Private DJ Lesson inquiry — '.$data['studentName'], $normalized);

        return response()->json(['ok' => true, 'note' => 'Inquiry received. The desk will follow up to confirm placement.']);
    }

    public function soundtunnel(Request $request, NotificationService $notifications, CustomerAccountService $accounts)
    {
        if ($request->filled('website')) {
            return response()->json(['ok' => true]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:150'],
            'eventName' => ['required', 'string', 'max:190'],
            'eventType' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date', 'after:today'],
            'alternateDate' => ['nullable', 'date', 'after:today'],
            'startTime' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'string', 'max:40'],
            'headcount' => ['required', 'integer', 'min:1', 'max:250'],
            'audience' => ['required', 'string', 'max:60'],
            'ticketed' => ['required', 'in:Yes,No'],
            'ticketPrice' => ['nullable', 'string', 'max:100'],
            'services' => ['nullable', 'array', 'max:8'],
            'services.*' => ['string', 'max:100'],
            'alcohol' => ['required', 'in:Yes,No,Undecided'],
            'budget' => ['nullable', 'string', 'max:60'],
            'productionNotes' => ['nullable', 'string', 'max:3000'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'referral' => ['nullable', 'string', 'max:100'],
            'terms' => ['accepted'],
        ]);
        $user = $this->account($request, $data, $accounts);
        $this->save('soundtunnel_rental', $data, $user);
        $notifications->adminAfterResponse('New SoundTunnel rental request — '.$data['name'], $data);
        $notifications->welcomeAfterResponse($data['email'], $data['name'], 'soundtunnel', $data['eventType']);

        return response()->json(['ok' => true, 'note' => 'Application received. Check your email for the next registration step.']);
    }

    public function djSchool(Request $request, NotificationService $notifications, CustomerAccountService $accounts)
    {
        if ($request->filled('website')) {
            return response()->json(['ok' => true, 'note' => 'Request received. Check your email to complete DJ Registration.']);
        }

        $programs = [
            'DJ Fundamentals - Beginner' => 'dj-fundamentals',
            'DJ Performance Lab - Intermediate' => 'dj-performance-lab',
            'Private DJ Lessons - 1-on-1' => 'private-dj-lessons',
            'Not Sure' => null,
        ];
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:80'],
            'zip' => ['required', 'string', 'max:20'],
            'age' => ['required', Rule::in(['Under 18', '18-24', '25-34', '35-44', '45-54', '55+'])],
            'parentName' => ['required_if:age,Under 18', 'nullable', 'string', 'max:150'],
            'parentEmail' => ['required_if:age,Under 18', 'nullable', 'email', 'max:190'],
            'parentPhone' => ['required_if:age,Under 18', 'nullable', 'string', 'max:40'],
            'program' => ['required', Rule::in(array_keys($programs))],
            'experience' => ['required', Rule::in([
                "Brand New - I've never used DJ equipment",
                "Beginner - I've practiced a little",
                'Developing - I understand the basics',
                'Intermediate - I can already mix/play sets',
                "Experienced - I'm looking to sharpen specific skills",
            ])],
            'hasEquipment' => ['required', 'in:Yes,No'],
            'equipment' => ['required_if:hasEquipment,Yes', 'nullable', 'string', 'max:500'],
            'software' => ['required_if:hasEquipment,Yes', 'nullable', Rule::in(['Serato', 'Rekordbox', 'VirtualDJ', 'Engine DJ', 'None yet', 'Other'])],
            'skills' => ['required', 'array', 'min:1', 'max:16'],
            'skills.*' => ['string', 'max:80'],
            'bestTimes' => ['required', 'array', 'min:1', 'max:5'],
            'bestTimes.*' => [Rule::in(['Morning', 'Afternoon', 'Evening', 'Weekends', 'Flexible / Open Availability'])],
            'bestDays' => ['required', 'array', 'min:1', 'max:8'],
            'bestDays.*' => [Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturdays', 'Sunday', 'Other'])],
            'preparingEvent' => ['required', 'in:Yes,No'],
            'eventDetails' => ['required_if:preparingEvent,Yes', 'nullable', 'string', 'max:2000'],
            'goals' => ['required', 'string', 'min:10', 'max:5000'],
            'referral' => ['required', Rule::in(['Instagram', 'Facebook', 'Google Search', 'Family/Friends', 'Event', 'DJ/Instructor Referral', 'Our Website', 'Other'])],
            'additional' => ['nullable', 'string', 'max:5000'],
            'acknowledgment' => ['accepted'],
        ]);
        $data['acknowledgment'] = 'Accepted';

        $user = $this->account($request, $data, $accounts);
        $this->save('dj_school_inquiry', $data, $user);
        $slug = $programs[$data['program']];
        if ($slug && $program = Program::where('slug', $slug)->first()) {
            ProgramRegistration::create([
                'user_id' => $user->id,
                'program_id' => $program->id,
                'status' => 'received',
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'answers' => $data,
                'notes' => $data['goals'],
            ]);
        }

        $notifications->adminAfterResponse('New DJ School inquiry — '.$data['name'], $data);
        $notifications->welcomeAfterResponse($data['email'], $data['name'], 'dj', $data['program']);

        return response()->json(['ok' => true, 'note' => 'Request received. Check your email to complete DJ Registration.']);
    }

    public function membershipStatus()
    {
        $limit = 50;
        $count = DB::table('customer_memberships')->where('membership_plan_id', MembershipPlan::where('slug', 'founder')->value('id'))->whereIn('status', ['active', 'trialing', 'grace'])->count();

        return response()->json(['max' => $limit, 'taken' => $count, 'remaining' => max(0, $limit - $count)]);
    }

    public function membership(Request $request, NotificationService $notifications, CustomerAccountService $accounts)
    {
        if ($request->filled('website')) {
            return response()->json(['ok' => true, 'plan' => 'community', 'note' => "You're on the list."]);
        }
        $data = $request->validate(['plan' => ['required'], 'name' => ['required'], 'email' => ['required', 'email'], 'phone' => ['nullable']]);
        if ($data['plan'] === 'founding') {
            return response()->json(['error' => 'Founding Member signups go through checkout.'], 400);
        }
        $user = $this->account($request, $data, $accounts);
        if (in_array($data['plan'], ['creative_network', 'pro'], true)) {
            $this->save('membership_waitlist', $data, $user);
            $notifications->adminAfterResponse('New membership waitlist signup — '.$data['name'], $data);

            return response()->json(['ok' => true, 'plan' => $data['plan'], 'note' => "You're on the waitlist. The desk will reach out when this tier opens."]);
        }

        return response()->json(['ok' => true, 'plan' => 'community', 'note' => "You're on the community list. No charge."]);
    }

    private function save(string $type, array $data, User $user): FormSubmission
    {
        return FormSubmission::create([
            'user_id' => $user->id, 'type' => $type, 'name' => $data['name'],
            'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null,
            'event_date' => $data['date'] ?? $data['eventDate'] ?? null,
            'message' => $data['message'] ?? $data['comments'] ?? $data['goals'] ?? $data['eventDetails'] ?? null,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function account(Request $request, array &$data, CustomerAccountService $accounts): User
    {
        $user = $accounts->resolve($request->user(), [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['zip'] ?? null,
        ]);

        $data['email'] = $user->email;
        if ($request->user()) {
            $data['name'] = $user->name;
            $data['phone'] = $user->phone ?? ($data['phone'] ?? null);
            $data['city'] = $user->city ?? ($data['city'] ?? null);
            $data['state'] = $user->state ?? ($data['state'] ?? null);
            if (array_key_exists('zip', $data)) {
                $data['zip'] = $user->postal_code ?? $data['zip'];
            }
        }

        return $user;
    }
}
