<?php namespace App;

use App\Jobs\SendReminderMessage;
use Carbon\Carbon;

class PlanRepository
{
    const DEFAULT_TIME = '9PM';

    const DEFAULT_TIMEZONE = 'America/Chicago';

    /**
     * Get a Plan by its ID
     *
     * @param int $id
     *
     * @return \App\Plan
     */
    public function getById($id)
    {
        return Plan::with('rsvps')->find($id);
    }

    /**
     * Creates a plan belonging to a user, based on command data.
     *
     * @param \App\User $user
     * @param array     $data
     *
     * @return \App\Plan
     */
    public function createPlan(User $user, $data)
    {
        return tap($user->plans()->create([
            'scheduled_at' => $this->getPlansScheduledTime($user, $data['text']),
            'response_url' => $data['response_url'],
        ]), function (Plan $plan) {
            $scheduledAt = clone $plan->scheduled_at;
            dispatch(
                (new SendReminderMessage($plan, trans('messages.plan_reminder')))
                    ->delay($scheduledAt)
            );
            dispatch(
                (new SendReminderMessage($plan, trans('messages.plan_hour_reminder')))
                    ->delay($scheduledAt->subHour())
            );
        });
    }

    /**
     * Parses the plans scheduled time, using the default otherwise
     *
     * @param \App\User $user
     * @param string    $text
     *
     * @return \Carbon\Carbon
     */
    protected function getPlansScheduledTime(User $user, $text)
    {
        preg_match('/at (\d+(?>:\d+)?(?>am|pm|AM|PM)?)/i', $text, $matches);

        // Handle empty text here, which also covers incorrect input data
        if (empty($matches)) {
            $time = Carbon::parse(self::DEFAULT_TIME, self::DEFAULT_TIMEZONE);

            $time->timezone = 'UTC';

            return $time;
        }

        if (!ends_with($matches[1], ['am', 'pm', 'AM', 'PM'])) {
            $matches[1] = $matches[1] . 'pm';
        }

        $time = Carbon::parse($matches[1], $user->timezone);

        $time->timezone = 'UTC';

        return $time;
    }
}
