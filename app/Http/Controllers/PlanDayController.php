<?php

namespace App\Http\Controllers;

use App\Domain\Training\Enums\AiAnalysisStatus;
use App\Models\AiAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanDayController extends Controller
{
    public function __invoke(Request $request, string $plan, string $date): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['complete', 'move', 'link'])],
            'target_date' => ['required_if:action,move', 'date_format:Y-m-d', 'different:date'],
            'activity_id' => ['required_if:action,link', 'uuid', Rule::exists('activities', 'id')->where('user_id', $request->user()->id)],
        ]);

        DB::transaction(function () use ($request, $plan, $date, $data): void {
            $analysis = AiAnalysis::query()->where('user_id', $request->user()->id)
                ->where('type', 'weekly_plan')->where('status', AiAnalysisStatus::Completed)
                ->lockForUpdate()->findOrFail($plan);
            $output = $analysis->output;
            $days = $output['days'] ?? [];
            $index = array_search($date, array_column($days, 'date'), true);
            abort_if($index === false, 404);

            if ($data['action'] === 'complete') {
                $days[$index]['completed'] = ! ($days[$index]['completed'] ?? false);
            } elseif ($data['action'] === 'move') {
                $target = array_search($data['target_date'], array_column($days, 'date'), true);
                abort_if($target === false || $target === $index, 422);
                [$days[$index]['date'], $days[$target]['date']] = [$days[$target]['date'], $days[$index]['date']];
                usort($days, fn ($a, $b) => strcmp($a['date'], $b['date']));
            } else {
                abort_unless($days[$index]['type'] === 'ride', 422);
                foreach ($days as &$day) {
                    if (isset($day['linked_activity_ids'])) {
                        $day['linked_activity_ids'] = array_values(array_diff($day['linked_activity_ids'], [$data['activity_id']]));
                    }
                }
                unset($day);
                $days[$index]['linked_activity_ids'] = array_values(array_unique([
                    ...($days[$index]['linked_activity_ids'] ?? []), $data['activity_id'],
                ]));
            }
            $output['days'] = $days;
            $analysis->update(['output' => $output]);
        });

        return redirect()->to(route('coach.index', ['plan' => $plan, 'day' => $data['target_date'] ?? $date]).'#week');
    }
}
