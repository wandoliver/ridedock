<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Operations\Services\OperationsOverview;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AiUsageController extends Controller
{
    public function __invoke(Request $request, OperationsOverview $overview): View
    {
        $userId = $request->user()->id;
        return view('settings.ai-usage', [
            'usage' => $overview->usage($userId),
            'activityQuota' => $request->user()->aiQuota(true),
            'planQuota' => $request->user()->aiQuota(false),
            'groups' => $overview->attempts($userId)->select(['feature', 'model'])
                ->selectRaw('COUNT(*) AS attempts, SUM(input_tokens) AS input_tokens, SUM(output_tokens) AS output_tokens, SUM(cost_microusd) AS cost_microusd, SUM(CASE WHEN cost_microusd IS NULL THEN 1 ELSE 0 END) AS unknown_costs')
                ->groupBy(['feature', 'model'])->get(),
        ]);
    }
}
